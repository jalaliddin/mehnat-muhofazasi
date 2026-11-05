# Savollar bazalari — amalga oshirish rejasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Savollarni qayta ishlatiladigan bazalarga ajratish va har bir imtihon turida "qaysi bazadan nechta savol" tarkibini belgilash; test boshlanganda savollar shu tarkib bo'yicha tasodifiy tanlanadi.

**Architecture:** Yangi `question_banks` va `exam_type_question_bank` (pivot, `question_count`) jadvallari; `questions.exam_type_id` → `question_bank_id` (ma'lumot migratsiyasi bilan). Test boshlanganda (`Public\ExamSessionController::start`) har bazadan `inRandomOrder()->limit(count)`. Yozish faqat admin. Vue: "Savollar bazalari" sahifalari + imtihon turida "Test tarkibi" dialogi.

**Tech Stack:** Laravel 13 / PHP 8.3, MySQL, PhpSpreadsheet (mavjud), Vue 3 + Vuetify 3.

**Spec:** `docs/superpowers/specs/2026-11-05-savollar-bazalari-design.md`

## Global Constraints

- Ish `feature/savollar-bazalari` branch'ida (`main` dan). Har commit'da faqat o'z fayllaringizni `git add <path>` bilan qo'shing.
- Backend testlari: `cd backend/muhofaza && php artisan test --colors=never` (MySQL `muhofaza_test`). Har task oxirida **butun** to'plam yashil bo'lishi shart.
- Frontend: `cd frontend/muhofaza && npm run build` → `✓ built`. Qo'lda tekshiruv — scratchpad'dagi Playwright (`~/.npm/_npx/*/node_modules/playwright`, `channel: 'chrome'`), dev serverlar `php artisan serve --port=8000` va `npx vite --port 5173`.
- Yozish endpoint'lari faqat `role:admin`; o'qish — barcha autentifikatsiyalangan foydalanuvchilar.
- `question_count`: integer, 1–500.
- Foydalanuvchiga ko'rinadigan xabarlar (aynan):
  - `Bu imtihon turi uchun test tarkibi belgilanmagan. Imtihon turlari sahifasida "Test tarkibi"ni to'ldiring.`
  - `«{nom}» bazasida savollar yetarli emas: {bor} ta bor, {kerak} ta kerak.`
  - `Baza testlarda ishlatilmoqda: {tur nomlari, vergul bilan}`
  - `Bu test uchun savollar topilmadi` (mavjud)
- Spec'dan farq: spec 2.4 migratsiyani "tranzaksiyada" deydi, lekin MySQL'da DDL implicit commit qiladi — migratsiya ketma-ket qadamlar bilan yoziladi (ma'lumot ko'chirish qadami o'zi `DB::transaction` ichida).
- Plan darajasidagi qaror (spec'da yo'q): hozir `QuestionsView` tepasidagi imtihon turi sozlamalari ("Test vaqti", "O'tish balli") "Test tarkibi" dialogiga ko'chadi — savollar sahifasi endi bazaga tegishli.

## Review Focus

1. Production migratsiyasi: savolsiz imtihon turlari uchun baza yaratilmaydi; baza nomi band bo'lsa ` (ID n)` qo'shiladi (Task 1 testlari).
2. Deploydan **oldin** yaratilgan, hali boshlanmagan sessiyalar (`total_points` eski hisobda) — `start` uni tushgan savollar bo'yicha qayta yozishi kerak (Task 2 testi).
3. Havoladan keyin bazaning hamma savoli o'chirilsa — `start` 422 `Bu test uchun savollar topilmadi`, 500 emas (Task 2 testi).
4. Bitta baza ikki testda: bir turning tarkibini saqlash ikkinchisiga tegmasligi kerak (Task 4 testi).
5. Imtihon turi o'chirilsa — tarkib qatorlari o'chadi, baza va savollar qoladi (Task 1 testi).

---

### Task 0: Branch

- [ ] `git checkout main && git checkout -b feature/savollar-bazalari`

---

### Task 1: Sxema, ma'lumot migratsiyasi, modellar

**Files:**
- Create: `backend/muhofaza/database/migrations/2026_11_05_100000_create_question_banks.php`
- Create: `backend/muhofaza/app/Models/QuestionBank.php`, `backend/muhofaza/app/Models/ExamTypeQuestionBank.php` (pivot model, `$table = 'exam_type_question_bank'`)
- Modify: `backend/muhofaza/app/Models/ExamType.php`, `backend/muhofaza/app/Models/Question.php`
- Modify: `backend/muhofaza/tests/Concerns/CertificateFixtures.php` (`makeExam`, `makeBank`)
- Test: `backend/muhofaza/tests/Feature/QuestionBanks/QuestionBankMigrationTest.php` (**`DatabaseMigrations`** trait — DDL RefreshDatabase tranzaksiyasini buzadi)
- Test: `backend/muhofaza/tests/Feature/QuestionBanks/QuestionBankModelTest.php` (`RefreshDatabase`)

**Interfaces:**
- Produces:
  - Jadvallar spec 2.1–2.3 bo'yicha aynan.
  - `QuestionBank`: `fillable [name, description]`, `questions(): HasMany`, `examTypes(): BelongsToMany` (`exam_type_question_bank`, `withPivot('question_count')`, `withTimestamps()`).
  - `ExamType::questionBanks(): BelongsToMany` (xuddi shunday pivot), `ExamType::questionTotal(): int` (pivot `question_count` yig'indisi).
  - **Vaqtinchalik** `ExamType::questions(): HasManyThrough` (`Question`, `ExamTypeQuestionBank`, `exam_type_id`, `question_bank_id`, `id`, `question_bank_id`) — mavjud `generate/start/show/index` Task 2 gacha ishlab tursin. Task 2 da olib tashlanadi.
  - `Question`: fillable'da `exam_type_id` → `question_bank_id`; `bank(): BelongsTo`.
  - Fixtures: `makeBank(string $name = null, int $questionCount = 2, int $points = 1): QuestionBank` (savollar `options` A/B, `correct_option` `'A'`); `makeExam(...)` endi `makeBank()` qiladi va tarkibga `question_count = 2` bilan ulaydi (savollar soni avvalgidek 2).

- [ ] **Step 1: Failing tests.** `QuestionBankMigrationTest` — har test boshida `$m = require database_path('migrations/2026_11_05_100000_create_question_banks.php'); $m->down();` keyin eski shaklda ma'lumot yozadi (`DB::table('questions')->insert([... 'exam_type_id' => ...])`):

```php
public function test_up_moves_questions_into_bank_named_after_exam_type(): void
{
    // tur A: 3 savol, tur B: 0 savol
    $m->up();
    $bank = QuestionBank::where('name', 'Mehnat muhofazasi')->firstOrFail();
    $this->assertSame(3, $bank->questions()->count());
    $this->assertSame(1, QuestionBank::count());                       // B uchun baza yo'q
    $this->assertSame(3, ExamType::find($a)->questionBanks()->first()->pivot->question_count);
    $this->assertSame(0, ExamType::find($b)->questionBanks()->count());
    $this->assertFalse(Schema::hasColumn('questions', 'exam_type_id'));
}

public function test_up_suffixes_duplicate_bank_names(): void
{
    // ikkita tur bir xil nom "Test" bilan, ikkalasida savol bor
    $m->up();
    $this->assertEqualsCanonicalizing(['Test', "Test (ID {$second})"], QuestionBank::pluck('name')->all());
}

public function test_down_restores_exam_type_id(): void
{
    $m->up();
    $m->down();
    $this->assertTrue(Schema::hasColumn('questions', 'exam_type_id'));
    $this->assertSame(3, DB::table('questions')->where('exam_type_id', $a)->count());
    $this->assertFalse(Schema::hasTable('question_banks'));
    $m->up(); // keyingi rollback uchun holatni tiklash
}
```

  `QuestionBankModelTest` (`RefreshDatabase`, `CertificateFixtures`):

```php
public function test_question_total_sums_composition(): void
{
    $type = $this->makeExam()->examType;
    $type->questionBanks()->attach($this->makeBank(null, 5)->id, ['question_count' => 4]);
    $this->assertSame(6, $type->fresh()->questionTotal());           // 2 + 4
}

public function test_deleting_exam_type_keeps_bank_and_questions(): void
{
    $exam = $this->makeExam();
    $bank = $exam->examType->questionBanks()->first();
    $exam->examType->delete();
    $this->assertSame(0, DB::table('exam_type_question_bank')->count());
    $this->assertSame(2, $bank->fresh()->questions()->count());
}
```

- [ ] **Step 2:** `php artisan test --filter=QuestionBank --colors=never` → FAIL (migratsiya fayli/klasslar yo'q).
- [ ] **Step 3:** Migratsiya — spec 2.4 algoritmi. `up()` bitta `DB::transaction` emas (MySQL DDL implicit commit qiladi) — ketma-ket qadamlar: jadvallar → nullable ustun → ma'lumot ko'chirish (`DB::table`, Eloquent emas) → `question_bank_id` NOT NULL (`->change()`) + FK → `exam_type_id` FK va ustunni o'chirish. `down()` — spec 2.4 teskarisi. Modellar va fixtures.
- [ ] **Step 4:** `php artisan test --colors=never` → hammasi PASS (mavjud sertifikat testlari ham — vaqtinchalik `questions()` tufayli). `php artisan migrate` ishchi bazada → DONE; `mysql ... -e "select * from question_banks"` → "Mehnar muhofazasi" (3 savol) bazasi.
- [ ] **Step 5: Commit** — `feat(question-banks): sxema, ma'lumot migratsiyasi, modellar`.

---

### Task 2: Savollarni tarkib bo'yicha tanlash

**Files:**
- Modify: `backend/muhofaza/app/Http/Controllers/Api/ExamSessionController.php` (`generate`)
- Modify: `backend/muhofaza/app/Http/Controllers/Api/Public/ExamSessionController.php` (`start`, `show`)
- Modify: `backend/muhofaza/app/Http/Controllers/Api/ExamTypeController.php` (`index`: `withCount('questions')` → `with('questionBanks')` + `question_total`)
- Modify: `backend/muhofaza/app/Models/ExamType.php` (vaqtinchalik `questions()` ni olib tashlash)
- Modify: `backend/muhofaza/tests/Concerns/CertificateFixtures.php` (`makeSession`: `total_points` → 0)
- Test: `backend/muhofaza/tests/Feature/QuestionBanks/QuestionSelectionTest.php`

**Interfaces:**
- Consumes: `ExamType::questionBanks()`, `questionTotal()`, fixtures (Task 1).
- Produces: `generate` validatsiya xabarlari (Global Constraints); `start` javobi o'zgarmaydi (`questions` ro'yxati); `GET /exam-types` elementlarida `question_total`.

- [ ] **Step 1: Failing tests** (`RefreshDatabase`, `CertificateFixtures`, `Sanctum`):

```php
private function examWith(array $banks): PeriodicExam   // [[$questionCount, $take, $points], ...]
{ /* makeExam() ning default tarkibini detach qilib, berilgan bazalarni ulaydi */ }

public function test_start_draws_configured_count_from_each_bank(): void
{
    $exam = $this->examWith([[10, 3, 1], [10, 2, 5]]);
    $s = $this->makeSession($exam, $this->makeEmployee($exam));
    $q = $this->postJson("/api/public/exam-sessions/{$s->token}/start")->assertOk()->json('questions');
    $this->assertCount(5, $q);
    $banks = ExamSessionQuestion::where('exam_session_id', $s->id)->with('question')->get()->countBy('question.question_bank_id');
    $this->assertSame([3, 2], $banks->values()->sort()->reverse()->values()->all());   // 3 tasi 1-bazadan, 2 tasi 2-bazadan
    $this->assertSame(3 * 1 + 2 * 5, $s->fresh()->total_points);
}

public function test_start_overwrites_total_points_of_session_created_before_deploy(): void
{
    $exam = $this->examWith([[4, 2, 1]]);
    $s = $this->makeSession($exam, $this->makeEmployee($exam));
    $s->update(['total_points' => 99]);
    $this->postJson("/api/public/exam-sessions/{$s->token}/start")->assertOk();
    $this->assertSame(2, $s->fresh()->total_points);
}

public function test_start_takes_all_remaining_when_bank_shrank(): void
{
    $exam = $this->examWith([[5, 4, 1]]);
    $s = $this->makeSession($exam, $this->makeEmployee($exam));
    $exam->examType->questionBanks()->first()->questions()->limit(3)->get()->each->delete();
    $this->assertCount(2, $this->postJson("/api/public/exam-sessions/{$s->token}/start")->assertOk()->json('questions'));
}

public function test_start_with_empty_bank_returns_422(): void
{
    $exam = $this->examWith([[2, 2, 1]]);
    $s = $this->makeSession($exam, $this->makeEmployee($exam));
    $exam->examType->questionBanks()->first()->questions()->delete();
    $this->postJson("/api/public/exam-sessions/{$s->token}/start")->assertStatus(422)->assertJsonPath('message', 'Bu test uchun savollar topilmadi');
}

public function test_generate_requires_composition(): void
{
    $exam = $this->makeExam();
    $exam->examType->questionBanks()->detach();
    $this->makeEmployee($exam);
    Sanctum::actingAs($this->makeUser());
    $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])->assertStatus(422)
        ->assertJsonPath('message', 'Bu imtihon turi uchun test tarkibi belgilanmagan. Imtihon turlari sahifasida "Test tarkibi"ni to\'ldiring.');
}

public function test_generate_rejects_bank_with_too_few_questions(): void
{
    $exam = $this->examWith([[8, 10, 1]]);
    $exam->examType->questionBanks()->first()->update(['name' => 'Elektr xavfsizligi']);
    $this->makeEmployee($exam);
    Sanctum::actingAs($this->makeUser());
    $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])->assertStatus(422)
        ->assertJsonPath('message', '«Elektr xavfsizligi» bazasida savollar yetarli emas: 8 ta bor, 10 ta kerak.');
}

public function test_show_reports_composition_total_before_start(): void
{
    $exam = $this->examWith([[10, 3, 1], [10, 2, 1]]);
    $s = $this->makeSession($exam, $this->makeEmployee($exam));
    $this->getJson("/api/public/exam-sessions/{$s->token}")->assertJsonPath('total_questions', 5);
}

public function test_exam_types_index_returns_question_total(): void
{
    $exam = $this->examWith([[10, 3, 1], [10, 2, 1]]);
    Sanctum::actingAs($this->makeUser());
    $row = collect($this->getJson('/api/exam-types')->assertOk()->json())->firstWhere('id', $exam->exam_type_id);
    $this->assertSame(5, $row['question_total']);
}
```

  (`generate` xatolari mavjud uslubda `abort(422, $message)` — JSON `message` bo'lib qaytadi.)
- [ ] **Step 2:** FAIL → **Step 3:** spec 3.1–3.3: `generate` — `questionBanks()->withCount('questions')` bilan tekshiruvlar, `total_points` 0; `start` — bazalar bo'yicha tanlash, `total_points` tranzaksiyada; `show`; `index`; vaqtinchalik `questions()` va undan foydalanishlarni olib tashlash (`grep -rn "questions()" app` → faqat `QuestionBank::questions()`).
- [ ] **Step 4:** `php artisan test --colors=never` → hammasi PASS.
- [ ] **Step 5: Commit** — `feat(question-banks): test savollarini tarkib bo'yicha tanlash`.

---

### Task 3: Bazalar API va savollarni bazaga ko'chirish

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/QuestionBankController.php`
- Modify: `backend/muhofaza/app/Http/Controllers/Api/QuestionController.php`, `backend/muhofaza/app/Http/Controllers/Api/QuestionImportController.php`
- Modify: `backend/muhofaza/routes/api.php` (savol yozish/import/template → `role:admin` guruhiga; bazalar route'lari)
- Test: `backend/muhofaza/tests/Feature/QuestionBanks/QuestionBankApiTest.php`

**Interfaces:**
- Produces (spec 4-bo'lim jadvali, aynan):
  - `GET /question-banks` → `[{id, name, description, questions_count, exam_types: [{id, name, question_count}]}]` nom bo'yicha; `GET /question-banks/{id}` — bitta shunday obyekt.
  - `POST/PUT/DELETE /question-banks` (admin); `DELETE` ishlatilsa 422 `Baza testlarda ishlatilmoqda: …`.
  - `GET /questions?question_bank_id=X` (`required|exists:question_banks,id`); `POST/PUT/DELETE /questions`, `GET /questions/template`, `POST /questions/import` — admin, `question_bank_id` bilan.

- [ ] **Step 1: Failing tests:**
  - `test_admin_crud_bank` — POST `{name:'Elektr xavfsizligi'}` → 201; takroriy nom → 422 `name`; PUT nomni o'zgartiradi; GET `questions_count` va `exam_types[0].question_count` bilan.
  - `test_delete_bank_in_use_returns_422` — `makeExam()` bazasini DELETE → 422, xabar `Baza testlarda ishlatilmoqda: Mehnat muhofazasi`.
  - `test_delete_unused_bank_deletes_its_questions` — `makeBank(null, 3)` DELETE → 200, `Question::count()` 0 (o'sha bazadan).
  - `test_operator_can_read_but_not_write` — operator: `GET /question-banks`, `GET /question-banks/{id}`, `GET /questions?question_bank_id=` → 200; `POST/PUT/DELETE /question-banks`, `POST /questions`, `PUT/DELETE /questions/{id}`, `POST /questions/import`, `GET /questions/template` → 403.
  - `test_questions_index_filters_by_bank` — ikki baza, index faqat birinchisini qaytaradi.
  - `test_store_question_into_bank` — POST `{question_bank_id, question_text, options:[{key:A,...},{key:B,...}], correct_option:'A'}` → 201, `question_bank_id` saqlangan.
  - `test_import_writes_into_bank` — testda PhpSpreadsheet bilan 2 qatorli xlsx yarating (sarlavha + 2 savol, A–G ustunlar mavjud shablondagidek), `UploadedFile` bilan POST `{question_bank_id, file}` → `imported: 2`, ikkala savol shu bazada.
- [ ] **Step 2:** FAIL → **Step 3:** controller, `QuestionController`/`QuestionImportController` da `exam_type_id` → `question_bank_id`, route'lar → **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `feat(question-banks): bazalar API, savollar va import bazaga`.

---

### Task 4: Test tarkibi API

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/ExamTypeQuestionBankController.php`
- Modify: `backend/muhofaza/routes/api.php`
- Test: `backend/muhofaza/tests/Feature/QuestionBanks/CompositionApiTest.php`

**Interfaces:**
- Produces: `GET /exam-types/{id}/question-banks` → `[{question_bank_id, name, question_count, available}]` (hamma); `PUT` `{banks:[{question_bank_id, question_count}]}` → `sync`, javob = GET (admin). Validatsiya: `banks` `present|array`; `banks.*.question_bank_id` `required|distinct|exists:question_banks,id`; `banks.*.question_count` `required|integer|min:1|max:500`.

- [ ] **Step 1: Failing tests:**
  - `test_put_replaces_composition_and_returns_available` — ikki baza (10 va 3 savol), PUT `[{b1,4},{b2,5}]` → 200, javobda `[{…,question_count:4,available:10},{…,question_count:5,available:3}]` (saqlashga ruxsat, `available` kamroq bo'lsa ham).
  - `test_put_rejects_duplicate_bank` → 422 `banks.1.question_bank_id`.
  - `test_put_rejects_zero_count` → 422 `banks.0.question_count`.
  - `test_put_empty_list_clears_composition` → 200, `questionTotal()` 0.
  - `test_saving_one_type_leaves_other_type_using_same_bank` (Review Focus 4) — bitta baza ikki turga ulangan; birinchi tur PUT `[]` → ikkinchisining pivot qatori joyida.
  - `test_operator_reads_but_cannot_update` — GET 200, PUT 403.
- [ ] **Step 2:** FAIL → **Step 3** → **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `feat(question-banks): test tarkibi API`.

---

### Task 5: Frontend — "Savollar bazalari" sahifalari

**Files:**
- Create: `frontend/muhofaza/src/views/exams/QuestionBanksView.vue`
- Modify: `frontend/muhofaza/src/views/exams/QuestionsView.vue` (bazaga moslash; "Test vaqti / O'tish balli" kartasini olib tashlash)
- Delete: `frontend/muhofaza/src/views/exams/QuestionsOverviewView.vue`
- Modify: `frontend/muhofaza/src/router/index.js` (`exams/question-banks`, `exams/question-banks/:id`; `exams/questions` va `exams/types/:id/questions` → redirect `/exams/question-banks`)
- Modify: `frontend/muhofaza/src/components/layout/AppSidebar.vue` ("Savollar" → "Savollar bazalari", `mdi-database-outline`, `/exams/question-banks`)

**Interfaces:**
- Consumes: Task 3 API.

- [ ] **Step 1:** `QuestionBanksView.vue` — `UsersView.vue` uslubida: qidiruv, jadval (`Nomi`, `Savollar soni` chip, `Testlarda` — `exam_types` dan "Nom (N ta)" ro'yxati yoki `—`, `Amallar`), qator bosilsa `/exams/question-banks/{id}`; admin uchun "Yangi baza" + tahrirlash/o'chirish dialoglari (`ConfirmDialog`; 422 xabari snackbar'da). Operator uchun yozish tugmalari ko'rinmaydi (`authStore.isAdmin`).
- [ ] **Step 2:** `QuestionsView.vue` — `route.params.id` = baza id; sarlavha `Savollar — {bank.name}` (`GET /question-banks/{id}`); barcha so'rovlarda `exam_type_id` → `question_bank_id`; sozlamalar kartasi va `saveSettings` olib tashlanadi; qo'shish/tahrirlash/o'chirish/shablon/import tugmalari faqat admin uchun.
- [ ] **Step 3:** Router, sidebar, `QuestionsOverviewView.vue` ni o'chirish. `npm run build` → `✓ built`.
- [ ] **Step 4: Qo'lda (Playwright):** admin — baza yaratish → sahifasiga o'tish → savol qo'shish → Excel shablonni yuklab olish va 2 savolli fayl import → `Savollar soni` 3; takroriy nom → xato; testda ishlatilayotgan bazani o'chirish → `Baza testlarda ishlatilmoqda…` xabari. Operator — ro'yxat va savollar ko'rinadi, yozish tugmalari yo'q. Konsol xatolari yo'q.
- [ ] **Step 5: Commit** — `feat(question-banks): savollar bazalari sahifalari (UI)`.

---

### Task 6: Frontend — "Test tarkibi" dialogi

**Files:**
- Modify: `frontend/muhofaza/src/views/exams/ExamTypesView.vue`

**Interfaces:**
- Consumes: `GET/PUT /exam-types/{id}/question-banks` (Task 4), `GET /question-banks` (Task 3), mavjud `PUT /exam-types/{id}` (`duration_minutes`, `passing_score`), `GET /exam-types` dagi `question_total` (Task 2).

- [ ] **Step 1:** Qatordagi `mdi-help-circle-outline` "Savollar" tugmasi → `mdi-format-list-checks` "Test tarkibi" (tooltip) va jadvalga `Savollar` ustuni (`question_total` → "N ta savol", 0 bo'lsa kulrang "Tarkib yo'q").
- [ ] **Step 2:** Dialog (spec 3-qism mockup): tepada `Test vaqti (daqiqa)` va `O'tish balli (%)` (QuestionsView'dan ko'chirilgan); jadval qatorlari — `v-select` baza (boshqa qatorlarda tanlanganlar `items` dan chiqariladi), `Nechta` (number, 1–500), `Bazada {available} ta`, `question_count > available` bo'lsa `mdi-alert` + tooltip `Bazada {available} ta savol bor`; `+ Baza qo'shish`; pastda `Jami: {sum} ta savol`. Saqlash: avval `PUT /exam-types/{id}` (vaqt, o'tish balli), keyin `PUT /exam-types/{id}/question-banks`; xatolar snackbar'da; muvaffaqiyatda dialog yopiladi va ro'yxat yangilanadi. Operator: hamma maydon `disabled`, "Saqlash" yo'q.
- [ ] **Step 3:** `npm run build` → `✓ built`.
- [ ] **Step 4: Qo'lda (Playwright, to'liq oqim):** ikki baza (masalan 5 va 4 savol) → imtihon turi tarkibi 3 + 2 → saqlash → ro'yxatda "5 ta savol" → davriy imtihonda havolalar yaratish → test sahifasida "Savollar soni: 5" → testni boshlash → 5 ta savol. Tarkibda 10 > 4 qilib havola yaratish → `«…» bazasida savollar yetarli emas: 4 ta bor, 10 ta kerak.` Vaqtinchalik ma'lumotlarni (xodim, bazalar) oxirida o'chiring.
- [ ] **Step 5: Commit** — `feat(question-banks): test tarkibi dialogi (UI)`.

---

### Task 7: Yakuniy tekshiruv va hujjat

**Files:**
- Modify: `README.md` (yangi "Savollar bazalari" bo'limi: tushuncha, test tarkibi, mavjud savollar migratsiyasi, operator faqat o'qiydi; "Sertifikatlar" bo'limidan keyin)

- [ ] **Step 1:** `php artisan test --colors=never` → hammasi PASS; `npm run build` → `✓ built`.
- [ ] **Step 2:** Ishchi bazada mavjud test (imtihon 10) hamon ishlaydi: yangi xodim uchun havola → test boshlanadi va 3 savol (migratsiyadan keyingi "hamma savol" tarkibi).
- [ ] **Step 3: Commit** — `docs: savollar bazalari bo'limi`.

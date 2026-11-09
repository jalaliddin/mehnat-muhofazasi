# Sertifikat turlari, qo'lda raqam va test variantlari — amalga oshirish rejasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Davriy imtihonda tanlanadigan 4 ta tahrirlanadigan sertifikat turi; sertifikat № ni qo'lda kiritish (bittalab va ommaviy, ketma-ket to'ldirish bilan) va raqamsiz PDF'ni bloklash; test sahifasida harfsiz, keng variantlar.

**Architecture:** `certificate_types` jadvali + `periodic_exams.certificate_type_id`; `exam_types.training_hours` olib tashlanadi. `CertificateService::issue()` endi raqamsiz va PDF'siz yaratadi; `setNumber()`/`setNumbers()` raqamni saqlab PDF chizadi (`certificates/generated/{id}.pdf`). Shablon sarlavha va kurs matnini `data` dan oladi. Vue: turlar jadvali, raqam dialogi, ommaviy chop etish dialogi, test variantlari.

**Tech Stack:** Laravel 13 / PHP 8.3, MySQL, dompdf, Vue 3 + Vuetify 3, Node 22 (`node --test`).

**Spec:** `docs/superpowers/specs/2026-11-09-sertifikat-turlari-raqam-design.md`

## Global Constraints

- Ish `feature/sertifikat-turlari` branch'ida (`main` dan). Commit'larda faqat o'z fayllaringiz (`git add <path>`).
- Backend: `cd backend/muhofaza && php artisan test --colors=never` — har task oxirida **butun** to'plam yashil. Frontend: `cd frontend/muhofaza && npm run build` → `✓ built`. Qo'lda tekshiruv — scratchpad Playwright (`~/.npm/_npx/*/node_modules/playwright`, `channel: 'chrome'`), dev serverlar 8000/5173; ishchi bazadagi ma'lumotlarni o'zgartirsangiz, oxirida qaytaring yoki vaqtinchalik yarating va o'chiring.
- Boshlang'ich turlar (aynan, spec 2.1 jadvali): `36 soatlik malaka oshirish` (36, `Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida`, `malaka oshirish kursida`), `36 soatlik o'qish` (36, `Mehnatni muhofaza qilish sohasida o'qiganlik haqida`, `o'quv kursida`), `72 soatlik malaka oshirish` (72, …`malaka oshirganlik haqida`, `malaka oshirish kursida`), `288 soatlik qayta tayyorlov` (288, `Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida`, `qayta tayyorlov kursida`).
- Shablon standart matnlari (tur yo'q / eski sertifikat): sarlavha `Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida`, kurs `malaka oshirish kursida`.
- Raqam: trim, 1–50 belgi, `certificates.number` unique (MySQL collation — katta-kichik harf farqlanmaydi).
- Xabarlar (aynan): `Avval sertifikat raqamini kiriting` · `Bu raqam boshqa sertifikatga berilgan` · `Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi` · `Tur davriy imtihonlarda ishlatilmoqda` · `Chop etish uchun raqamlangan, imzolanmagan sertifikat yo'q` · `Sertifikat turi tanlanmagan` · `Birinchi raqamda son bo'lishi kerak` · `Sertifikatingiz yaratildi. Mas'ul shaxslar raqam berib imzolagandan so'ng shu havolada yuklab olishingiz mumkin.` · `Sertifikat tayyorlanmoqda — imzo kutilmoqda` · `Raqam yo'q`.
- Fayl yo'llari: generated `certificates/generated/{id}.pdf`; signed `certificates/signed/{id}-{Str::random(8)}.{ext}` (raqamda `/` va bo'sh joy bo'lishi mumkin). Yuklab olish nomi: `sertifikat-{raqam, [^A-Za-z0-9._-] → '-'}.pdf`.
- Plan qarori (spec'ga qo'shimcha): `CertificatePdf::html(iterable): string` — `render()` shu HTML'ni dompdf'ga beradi; PDF matnini testlash HTML orqali (dompdf oqimlari siqilgan).

## Review Focus

1. Katta-kichik harf farqi bilan takror raqam (`MM-0451` bor, `mm-0451` kiritiladi) → 422 `Bu raqam boshqa sertifikatga berilgan`, 500 emas (Task 2 testi).
2. Ommaviy oynada ikki sertifikat raqamlarini almashtirish (A↔B) → muvaffaqiyatli (Task 2 testi).
3. Raqamda `/` (`12/2026`) → PDF `{id}.pdf` ga yoziladi, yuklab olish nomi `sertifikat-12-2026.pdf` (Task 2 testi).
4. Sertifikat chiqqandan keyin davriy imtihon turi o'zgartirilsa → "Qayta yaratish" yangi sarlavha bilan chizadi (Task 1 testi).
5. Ommaviy ro'yxatda imzolangan sertifikat → 422, boshqalari ham saqlanmaydi (Task 2 testi).

---

### Task 0: Branch

- [ ] `git checkout main && git checkout -b feature/sertifikat-turlari`

---

### Task 1: Sertifikat turlari — sxema, ma'lumot surati, shablon

**Files:**
- Create: `backend/muhofaza/database/migrations/2026_11_09_000001_create_certificate_types.php`
- Create: `backend/muhofaza/app/Models/CertificateType.php`
- Modify: `backend/muhofaza/app/Models/PeriodicExam.php`, `backend/muhofaza/app/Models/ExamType.php`
- Modify: `backend/muhofaza/app/Services/CertificateService.php` (`buildData`, `missingFieldWarnings`)
- Modify: `backend/muhofaza/app/Services/CertificatePdf.php` (`html()`), `backend/muhofaza/resources/views/certificates/certificate.blade.php`
- Modify: `backend/muhofaza/app/Http/Controllers/Api/ExamTypeController.php` (`training_hours` qoidalarini olib tashlash)
- Modify: `backend/muhofaza/tests/Concerns/CertificateFixtures.php`; mavjud testlardagi `training_hours` ishlatilishi (`CertificateServiceTest`, `CertificateSettingsApiTest`)
- Test: `backend/muhofaza/tests/Feature/CertificateTypes/CertificateTypeMigrationTest.php` (`DatabaseMigrations`), `backend/muhofaza/tests/Feature/CertificateTypes/CertificateTypeContentTest.php`

**Interfaces:**
- Produces:
  - `CertificateType` (`fillable [name, hours, title_text, course_text]`, `periodicExams()`); `PeriodicExam::certificateType()`; `PeriodicExam` fillable `certificate_type_id`.
  - `buildData()` qo'shimcha kalitlari: `certificate_type_name`, `title_text`, `course_text`; `training_hours` = tur `hours` (yo'q bo'lsa `null`).
  - `missingFieldWarnings()`: `Soat hajmi kiritilmagan` o'rniga `Sertifikat turi tanlanmagan`.
  - `CertificatePdf::html(iterable $certificates): string` (render shu HTML dan).
  - Fixtures: `makeCertificateType(string $name = null, int $hours = 72, string $title = 'Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida', string $course = 'malaka oshirish kursida'): CertificateType`; `makeExam()` standart holda shunday turni `certificate_type_id` ga yozadi; `typeAttrs` dan `training_hours` olib tashlanadi; `makeCertificate()` standart `data` ga `certificate_type_name/title_text/course_text` qo'shiladi.

- [ ] **Step 1: Failing tests.**
  - Migratsiya (`DatabaseMigrations`, setUp'da migratsiyani `down()`; eski shaklda `exam_types.training_hours` bilan ma'lumot):
    - `test_up_seeds_four_types` — 4 nom (Global Constraints) va soatlari.
    - `test_up_maps_training_hours_to_type` — `training_hours=72` turining ikkala davriy imtihoni → `72 soatlik malaka oshirish`; `36` → `36 soatlik malaka oshirish`; `288` → `288 soatlik qayta tayyorlov`; `50` → `null`; so'ng `Schema::hasColumn('exam_types','training_hours')` false; `certificates.number` nullable (`DB::table('certificates')->insert([... 'number' => null ...])` muvaffaqiyatli).
    - `test_up_is_rerunnable` — `up()` ikki marta ketma-ket (ikkinchisi xatosiz, turlar soni 4).
  - Kontent:

```php
public function test_data_snapshot_takes_hours_and_texts_from_type(): void
{
    $type = $this->makeCertificateType("288 soatlik qayta tayyorlov", 288,
        "Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida", 'qayta tayyorlov kursida');
    $exam = $this->makeExam(null, ['certificate_type_id' => $type->id]);
    $data = app(CertificateService::class)->buildData($this->makeResult($exam));
    $this->assertSame(288, $data['training_hours']);
    $this->assertSame("288 soatlik qayta tayyorlov", $data['certificate_type_name']);
    $this->assertSame('qayta tayyorlov kursida', $data['course_text']);
}

public function test_pdf_html_uses_type_texts(): void
{
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => [
        'full_name' => 'X', 'training_hours' => 288, 'certificate_type_name' => '288 soatlik qayta tayyorlov',
        'title_text' => "Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida", 'course_text' => 'qayta tayyorlov kursida',
    ]]);
    $html = app(CertificatePdf::class)->html([$cert]);
    $this->assertStringContainsString("qayta tayyorlovdan o'tganlik haqida", $html);
    $this->assertStringContainsString('288 soat hajmida qayta tayyorlov kursida', $html);
}

public function test_pdf_html_falls_back_to_default_texts(): void
{
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => ['full_name' => 'X']]);
    $html = app(CertificatePdf::class)->html([$cert]);
    $this->assertStringContainsString('malaka oshirganlik haqida', $html);
    $this->assertStringContainsString('soat hajmida malaka oshirish kursida', $html);
}

public function test_changing_exam_type_then_regenerating_updates_title(): void   // Review Focus 4
{
    $exam = $this->makeExam();
    $result = $this->makeResult($exam);
    $svc = app(CertificateService::class);
    $svc->issue($result);
    $exam->update(['certificate_type_id' => $this->makeCertificateType("36 soatlik o'qish", 36,
        "Mehnatni muhofaza qilish sohasida o'qiganlik haqida", "o'quv kursida")->id]);
    $this->assertSame("o'quv kursida", $svc->issue($result->fresh())->data['course_text']);
}

public function test_warns_when_certificate_type_missing(): void
{
    $exam = $this->makeExam(null, ['certificate_type_id' => null]);
    $this->assertContains('Sertifikat turi tanlanmagan', app(CertificateService::class)->missingFieldWarnings($exam));
    $this->assertNotContains('Soat hajmi kiritilmagan', app(CertificateService::class)->missingFieldWarnings($exam));
}
```

  (HTML'da apostrof Blade tomonidan `&#039;` ga aylanadi — testda `html_entity_decode($html, ENT_QUOTES)` qilib solishtiring.)
- [ ] **Step 2:** FAIL (fayl/klass yo'q).
- [ ] **Step 3:** Migratsiya — spec 2.3, har qadam `hasTable/hasColumn/getForeignKeys` bilan qayta ishga tushiriladigan (oldingi `create_question_banks` migratsiyasi uslubida); ma'lumot qadami tranzaksiyada. Model, fixtures, `buildData` (`$exam->certificateType`), `missingFieldWarnings`, `ExamTypeController` qoidalari, shablon: sarlavha `{{ $d['title_text'] ?? standart }}`, matn `dastur bo‘yicha {soat} soat hajmida {course_text} o‘qidi:`. Mavjud testlardagi `training_hours` ishlatilishini moslang (`CertificateSettingsApiTest::test_exam_type_update_stores_program_and_hours` → faqat `program_name`; `CertificateServiceTest` snapshot kalitlari va ogohlantirishlar).
- [ ] **Step 4:** `php artisan test --colors=never` → hammasi PASS. `php artisan migrate` ishchi bazada → DONE; `select name, hours from certificate_types` → 4 qator.
- [ ] **Step 5: Commit** — `feat(certificate-types): turlar, tur bo'yicha sertifikat matni`.

---

### Task 2: Qo'lda raqam — servis va API

**Files:**
- Modify: `backend/muhofaza/app/Services/CertificateService.php` (`issue`, `createWithNumbers` → faqat `registration_number`; `setNumber`, `setNumbers`; `storeSigned` yo'li)
- Modify: `backend/muhofaza/app/Http/Controllers/Api/CertificateController.php` (`updateNumber`, `updateNumbers`, `download`, `print`, `index` per_page ≤ 500)
- Modify: `backend/muhofaza/routes/api.php` (`PUT /certificates/numbers` — `{id}` route'laridan **oldin**; `PUT /certificates/{id}/number`)
- Modify: `backend/muhofaza/tests/Concerns/CertificateFixtures.php` (`issueNumbered`)
- Modify: mavjud sertifikat testlari (avtomatik raqamga tayanganlar)
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificateNumberTest.php`

**Interfaces:**
- Consumes: Task 1 (`data` kalitlari).
- Produces:
  - `CertificateService::setNumber(Certificate $certificate, string $number): Certificate` (trim; imzolangan → `CertificateException`; band → `CertificateException('Bu raqam boshqa sertifikatga berilgan')` — `UniqueConstraintViolationException` ham shunga aylantiriladi; PDF → `certificates/generated/{id}.pdf`, boshqa nomli eski `generated_path` faylini o'chiradi).
  - `CertificateService::setNumbers(array $items): void` — `$items = [['certificate' => Certificate, 'number' => string], ...]`; tranzaksiya: avval hammasining `number = null`, keyin yangilari; so'ng PDF'lar.
  - `PUT /api/certificates/{id}/number` `{number}` → sertifikat JSON; `PUT /api/certificates/numbers` `{items:[{id, number}]}` → `{updated: N}`; validatsiya xatolari `items.{i}.number` kalitida.
  - Fixture: `issueNumbered(ExamResult $result, ?string $number = null): Certificate` = `issue()` + `setNumber($number ?? 'T-'.$id)`.

- [ ] **Step 1: Failing tests** (`CertificateNumberTest`, `Storage::fake('local')`, `Sanctum`):
  - `test_issue_creates_certificate_without_number_or_pdf` — `number` null, `generated_path` null, `registration_number` 1.
  - `test_online_submit_returns_null_number` — `OnlineTestCertificateTest` dagi oqim, `certificate.number` null (mavjud `…-0001` tasdiqni shu bilan almashtiring).
  - `test_set_number_renders_pdf_by_id` — `PUT …/number {number: ' 0451 '}` → 200, `number` `0451`, `Storage` da `certificates/generated/{id}.pdf` `%PDF` bilan.
  - `test_set_number_rejects_taken_number_case_insensitively` (Review Focus 1) — biri `MM-0451`, ikkinchisiga `mm-0451` → 422 `Bu raqam boshqa sertifikatga berilgan`.
  - `test_set_number_rejects_signed` → 422 `Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi`.
  - `test_number_with_slash_downloads_with_safe_name` (Review Focus 3) — `12/2026` → download 200, `Content-Disposition` da `sertifikat-12-2026.pdf`.
  - `test_download_without_number_returns_422` → `Avval sertifikat raqamini kiriting`.
  - `test_bulk_numbers_saves_all` — 3 ta → 200 `{updated: 3}`, hammasida PDF.
  - `test_bulk_numbers_can_swap` (Review Focus 2) — A=`1`, B=`2` → `[{A,'2'},{B,'1'}]` → 200, qiymatlar almashgan.
  - `test_bulk_numbers_rejects_duplicate_inside_list` → 422 `items.1.number`, hech biri o'zgarmagan.
  - `test_bulk_numbers_rejects_number_taken_outside_list` → 422 `items.0.number` = `Bu raqam boshqa sertifikatga berilgan`.
  - `test_bulk_numbers_rejects_signed_and_saves_nothing` (Review Focus 5) → 422, boshqasi ham raqamsiz.
  - `test_operator_cannot_number_other_org` — `PUT …/number` va `PUT …/numbers` → 403.
  - `test_print_includes_only_numbered_unsigned` — raqamli 1 + raqamsiz 1 → PDF 1 sahifa (`/Type /Page` sanog'i); hech biri raqamli bo'lmasa 404 `Chop etish uchun raqamlangan, imzolanmagan sertifikat yo'q`.
  - `test_index_allows_per_page_500` — `per_page=500` → `per_page` 500.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Servis va controller. `storeSigned` yo'li `certificates/signed/{id}-{random8}.{ext}`. Mavjud testlarni moslang: download/print/delete/public/storeSigned testlarida `issue()` → `issueNumbered()`; `test_numbers_restart_each_year…` → `test_registration_numbers_continue` (faqat ro'yxat raqami 1,2,3); `test_issue_writes_generated_pdf` → `generated_path` null ekanini tasdiqlaydi (yoki o'chiriladi — `test_issue_creates_certificate_without_number_or_pdf` qoplaydi); `test_issue_uses_certificate_created_concurrently…` raqami `2026-0099` qatori o'zgarmaydi (u to'g'ridan-to'g'ri insert).
- [ ] **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `feat(certificates): qo'lda raqam, raqamsiz PDF bloklanadi`.

---

### Task 3: Sertifikat turlari API va davriy imtihon maydoni

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/CertificateTypeController.php`
- Modify: `backend/muhofaza/routes/api.php`, `backend/muhofaza/app/Http/Controllers/Api/PeriodicExamController.php` (`certificate_type_id` nullable|exists; `index`/`show` javobiga `certificateType` yuklash)
- Test: `backend/muhofaza/tests/Feature/CertificateTypes/CertificateTypeApiTest.php`

**Interfaces:**
- Produces: `GET /certificate-types` (hamma, nom bo'yicha) → `[{id,name,hours,title_text,course_text}]`; `POST/PUT/DELETE` (admin) — spec 3.2 qoidalari, o'zbekcha xabarlar: `name.unique` → `Bu nomdagi tur allaqachon mavjud`, `required` → `Maydon to'ldirilishi shart`.

- [ ] **Step 1: Failing tests:** `test_admin_crud`, `test_duplicate_name_uzbek_message`, `test_delete_used_type_returns_422` (`Tur davriy imtihonlarda ishlatilmoqda`), `test_operator_reads_but_cannot_write` (GET 200; POST/PUT/DELETE 403), `test_periodic_exam_update_accepts_certificate_type` (PUT `/periodic-exams/{id}` `{certificate_type_id}` → saqlanadi; mavjud bo'lmagan id → 422).
- [ ] **Step 2:** FAIL → **Step 3** → **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `feat(certificate-types): turlar API, davriy imtihonda tur`.

---

### Task 4: Frontend — turlar jadvali, davriy imtihonda tur, "Soat hajmi" ni olib tashlash

**Files:**
- Modify: `frontend/muhofaza/src/views/settings/CertificateSettingsView.vue` ("Sertifikat turlari" bo'limi: jadval + dialog + o'chirish)
- Modify: `frontend/muhofaza/src/views/exams/PeriodicExamsView.vue` ("Sertifikat turi" `v-select`, clearable; `defaultForm`/tahrirlash xaritasi `certificate_type_id`)
- Modify: `frontend/muhofaza/src/views/exams/ExamTypesView.vue` ("Soat hajmi" maydoni, `training_hours` forma/payload'dan olib tashlanadi)

- [ ] **Step 1:** O'zgarishlar (spec 4-bo'lim).
- [ ] **Step 2:** `npm run build` → `✓ built`.
- [ ] **Step 3: Qo'lda (Playwright):** sozlamalarda 4 tur ko'rinadi; yangi tur qo'shish/tahrirlash/o'chirish; ishlatilayotgan turni o'chirish → snackbar `Tur davriy imtihonlarda ishlatilmoqda`; davriy imtihonda tur tanlab saqlash → qayta ochilganda tanlangan; imtihon turi formasida "Soat hajmi" yo'q. Vaqtinchalik tur oxirida o'chiriladi.
- [ ] **Step 4: Commit** — `feat(certificate-types): turlar va tur tanlash (UI)`.

---

### Task 5: Frontend — raqam dialogi va ommaviy chop etish

**Files:**
- Create: `frontend/muhofaza/src/utils/certificateNumbers.js`, `frontend/muhofaza/src/utils/certificateNumbers.test.js`
- Create: `frontend/muhofaza/src/components/certificates/CertificateNumberDialog.vue`, `frontend/muhofaza/src/components/certificates/BulkPrintDialog.vue`
- Modify: `frontend/muhofaza/src/views/certificates/CertificatesView.vue`, `frontend/muhofaza/src/views/exams/ExamDetailView.vue`
- Modify: `frontend/muhofaza/src/views/public/TestSessionView.vue` (sertifikat xabarlari), `frontend/muhofaza/src/views/public/CertificateVerifyView.vue` (`number` null → `—`)

**Interfaces:**
- Produces: `fillSequential(first: string, values: string[]): string[]` — `values` dagi bo'sh (trim'dan keyin) elementlarni tartib bilan `first`, keyingi… raqamlar bilan to'ldiradi, to'ldirilganlarni o'zgartirmaydi; `first` da son bo'lmasa `Error('Birinchi raqamda son bo\'lishi kerak')`.
- Consumes: Task 2 API.

- [ ] **Step 1: Failing test** (`node --test src/utils/certificateNumbers.test.js`, `node:test` + `node:assert/strict`):

```js
test('increments keeping prefix and zero padding', () => {
  assert.deepEqual(fillSequential('MM-0451', ['', '', '']), ['MM-0451', 'MM-0452', 'MM-0453'])
})
test('skips filled values and keeps them', () => {
  assert.deepEqual(fillSequential('0451', ['', '0377', '']), ['0451', '0377', '0452'])
})
test('keeps suffix and grows width on overflow', () => {
  assert.deepEqual(fillSequential('A-99/2026', ['', '']), ['A-99/2026', 'A-100/2026'])
})
test('throws without digits', () => {
  assert.throws(() => fillSequential('ABC', ['']), /Birinchi raqamda son bo'lishi kerak/)
})
```

  Algoritm: `^(.*?)(\d+)(\D*)$` (oxirgi son guruhi); `String(n + i).padStart(digits.length, '0')`.
- [ ] **Step 2:** `node --test …` → FAIL (modul yo'q) → **Step 3:** util → **Step 4:** PASS.
- [ ] **Step 5:** `CertificateNumberDialog` (props: sertifikat; `open(cert)`; "Saqlash va yuklab olish" → `PUT number` → `downloadFile(...)`; 422 xatosi maydon ostida; emit `saved`). `CertificatesView`: № ustuni `Raqam yo'q` chipi; 📄 imzolanmagan → dialog, imzolangan → to'g'ridan-to'g'ri. `BulkPrintDialog` (`open(examId)`; ro'yxat `GET /certificates?periodic_exam_id&status=pending_signature&per_page=500`; "Birinchi raqam" + "Ketma-ket to'ldirish" (`fillSequential`); bo'sh katak bo'lsa saqlash o'chiq; `PUT /certificates/numbers` → `downloadFile('/certificates/print', …)`; `items.{i}.number` xatolari qator ostida; bo'sh ro'yxat → `Imzolanmagan sertifikat yo'q`). `ExamDetailView`: chop etish tugmasi dialogni ochadi; natijalar chipi raqamsiz bo'lsa `Raqam yo'q · Imzo kutilmoqda`. `TestSessionView`/`CertificateVerifyView` matnlari (Global Constraints).
- [ ] **Step 6:** `npm run build` → `✓ built`.
- [ ] **Step 7: Qo'lda (Playwright, vaqtinchalik ma'lumot):** raqamsiz sertifikatda 📄 → dialog → `MM-0451` → PDF yuklanadi, ro'yxatda № `MM-0451`; boshqasiga `mm-0451` → maydon ostida xato; imtihon sahifasida chop etish → ro'yxat, "Birinchi raqam" `MM-0452` → ketma-ket → saqlash → `sertifikatlar-{id}.pdf` yuklanadi; test natija ekranida yangi xabar; QR sahifasida raqamsiz → `—`.
- [ ] **Step 8: Commit** — `feat(certificates): raqam dialogi va ommaviy chop etish (UI)`.

---

### Task 6: Test sahifasi — harfsiz, keng variantlar

**Files:**
- Modify: `frontend/muhofaza/src/views/public/TestSessionView.vue` (`v-radio` label `opt.text`; har variant alohida ramkali, butun qator bosiladigan blok — `min-height: 52px`, `margin-bottom: 10px`, tanlangani `primary` ramka + och fon; scoped style)

- [ ] **Step 1:** O'zgarish; `npm run build` → `✓ built`.
- [ ] **Step 2: Qo'lda (Playwright, 375px va 1366px):** variant matnlarida `A.`/`B.` yo'q (`getByText(/^[A-D]\. /)` soni 0); ikki qo'shni variant orasidagi masofa ≥ 10px (`boundingBox`); variant matnini (doirachani emas) bosish uni tanlaydi; tanlanganda ramka rangi o'zgaradi; testni topshirish oqimi buzilmagan (to'g'ri javoblar bilan → o'tdi). Skrinshotni ko'zdan kechiring.
- [ ] **Step 3: Commit** — `feat(test): harfsiz, keng javob variantlari`.

---

### Task 7: Yakuniy tekshiruv va hujjat

**Files:**
- Modify: `README.md` ("Sertifikatlar" bo'limi: turlar, qo'lda raqam, ommaviy chop etish; deploydan keyin turlarni tekshirish va davriy imtihonlarda tanlash)

- [ ] **Step 1:** `php artisan test --colors=never` → PASS; `node --test src/utils/certificateNumbers.test.js` → PASS; `npm run build` → `✓ built`.
- [ ] **Step 2:** Ishchi bazada mavjud sertifikatlar (`2026-0001`, `2026-0002`) raqami saqlangan, yuklab olinadi.
- [ ] **Step 3: Commit** — `docs: sertifikat turlari va qo'lda raqam`.

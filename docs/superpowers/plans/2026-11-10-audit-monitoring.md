# Audit roli va real vaqt monitoring — amalga oshirish rejasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faqat "Monitoring" ni ko'radigan `auditor` roli; test javoblari har tanlovda serverga saqlanadi; Monitoring sahifasi faol testlarni (5 s) va batafsil javoblarni (3 s) polling bilan ko'rsatadi; havola yaratishda ishtirokchi bo'lmaganlarning boshlanmagan havolalari o'chadi.

**Architecture:** `users.role` enum'iga `auditor`, `exam_session_questions.answered_at`. `DenyAuditor` middleware mavjud himoyalangan route'larni auditorga yopadi; yangi `MonitoringController` (scope — `organization_id`). Ochiq `PUT …/answer` va `GET …/questions` (test davom ettirish). Vue: `MonitoringView` + yon panel, rolga qarab router/sidebar.

**Tech Stack:** Laravel 13 / PHP 8.3, MySQL, Vue 3 + Vuetify 3.

**Spec:** `docs/superpowers/specs/2026-11-10-audit-monitoring-design.md`

## Global Constraints

- Ish `feature/audit-monitoring` branch'ida (`main` dan). Commit'larda faqat o'z fayllaringiz.
- Backend: `cd backend/muhofaza && php artisan test --colors=never` — har task oxirida butun to'plam yashil. Frontend: `npm run build` → `✓ built`. Qo'lda tekshiruv — scratchpad Playwright (`~/.npm/_npx/*/node_modules/playwright`, `channel: 'chrome'`), dev serverlar 8000/5173; vaqtinchalik ma'lumotlar oxirida o'chiriladi.
- Xabarlar (aynan): `Auditor faqat Monitoring bo'limini ko'radi` · `Test boshlanmagan yoki yakunlangan` · `Jonli · har 5 soniyada yangilanadi` · `Hozir test yechayotgan xodim yo'q` · `Javob berilmagan` · `Yangilab bo'lmadi, qayta urinilmoqda` · `Bo'sh qolsa — barcha tashkilotlar`.
- Scope qoidasi (Monitoring): foydalanuvchida `organization_id` bo'lsa (operator yoki auditor) — faqat `periodic_exams.organization_id` shu; bo'lmasa — hammasi. Admin — hammasi.
- Polling: ro'yxat 5000 ms, batafsil 3000 ms; `onUnmounted` va panel yopilganda `clearInterval`.
- Plan qarori (spec'ga qo'shimcha): `GET /api/monitoring/exams` — Monitoring filtri uchun scope bo'yicha imtihonlar ro'yxati.
- Vaqt: `remaining_seconds = max(0, started_at + duration_minutes·60 − now)` — serverda hisoblanadi.

## Review Focus

1. Brauzer yopilib, vaqti o'tgan, lekin hali `in_progress` holatidagi sessiya "Hozir yechilmoqda" da chiqmasligi kerak (Task 3 testi).
2. Topshirishdan keyin kechikib kelgan avtosaqlash so'rovi → 422, natija o'zgarmaydi (Task 2 testi).
3. Auditor eski xatcho'p bilan `/employees` ochsa — `/monitoring` ga yo'naltiriladi; API 403 (Task 1 testi + Task 7 Playwright).
4. Qolgan vaqt server soatidan hisoblanadi (`Carbon::setTestNow` bilan aniq qiymat) (Task 3 testi).
5. Tashkilotli auditor boshqa tashkilot sessiyasini batafsil ochsa → 403 (Task 3 testi).

---

### Task 0: Branch

- [ ] `git checkout main && git checkout -b feature/audit-monitoring`

---

### Task 1: Auditor roli va kirish nazorati

**Files:**
- Create: `backend/muhofaza/database/migrations/2026_11_10_000001_add_auditor_role_and_answered_at.php`
- Create: `backend/muhofaza/app/Http/Middleware/DenyAuditor.php`
- Modify: `backend/muhofaza/bootstrap/app.php` (alias `deny.auditor`), `backend/muhofaza/routes/api.php` (asosiy `auth:sanctum` guruhiga `deny.auditor`), `backend/muhofaza/app/Models/User.php` (`isAuditor()`), `backend/muhofaza/app/Http/Controllers/Api/UserController.php` (`in:admin,operator,auditor`)
- Modify: `backend/muhofaza/app/Models/ExamSessionQuestion.php` (`answered_at` fillable + `datetime` cast)
- Test: `backend/muhofaza/tests/Feature/Audit/AuditorAccessTest.php`

**Interfaces:**
- Produces: `User::isAuditor(): bool`; middleware alias `deny.auditor`; `exam_session_questions.answered_at`.

- [ ] **Step 1: Failing tests** (`RefreshDatabase`, `CertificateFixtures`, `Sanctum`):
  - `test_admin_can_create_auditor_without_organization` — POST `/api/users` `{name, username, email, password, role: 'auditor'}` → 201, `role` `auditor`, `organization_id` null.
  - `test_auditor_can_read_own_profile` — `GET /api/auth/me` → 200.
  - `test_auditor_is_denied_everything_else` — `GET /api/employees`, `/api/periodic-exams`, `/api/certificates`, `/api/users`, `/api/notifications/unread-count`, `POST /api/exam-sessions/generate` → hammasi 403 va `message` = `Auditor faqat Monitoring bo'limini ko'radi`.
  - `test_operator_and_admin_unaffected` — operator `GET /api/employees` → 200.
- [ ] **Step 2:** FAIL → **Step 3:** migratsiya (spec 2: `DB::statement` bilan enum; `answered_at` `hasColumn` bilan; `down()` auditor→operator, enum qaytariladi, ustun olinadi), middleware, alias, route guruhi `Route::middleware(['auth:sanctum', 'deny.auditor'])` (`auth/*` guruhi alohida qoladi — `me`/`logout` ochiq), `User::isAuditor`, validatsiya → **Step 4:** butun to'plam PASS; `php artisan migrate` ishchi bazada DONE.
- [ ] **Step 5: Commit** — `feat(audit): auditor roli va kirish nazorati`.

---

### Task 2: Javobni darhol saqlash va testni davom ettirish (API)

**Files:**
- Modify: `backend/muhofaza/app/Http/Controllers/Api/Public/ExamSessionController.php` (`answer`, `questions`)
- Modify: `backend/muhofaza/routes/api.php` (`public/exam-sessions` guruhi)
- Test: `backend/muhofaza/tests/Feature/Audit/AnswerAutosaveTest.php`

**Interfaces:**
- Produces: `PUT /api/public/exam-sessions/{token}/answer` `{exam_session_question_id, selected_option}` → `{saved: true}`; `GET /api/public/exam-sessions/{token}/questions` → `{started_at, duration_minutes, questions:[{exam_session_question_id, question_order, question_text, options, selected_option}]}`.

- [ ] **Step 1: Failing tests** (`makeSession` + `POST …/start` bilan boshlangan sessiya):
  - `test_answer_is_saved_with_timestamp` — `selected_option` `A`, `answered_at` not null.
  - `test_changing_answer_keeps_last` — A keyin B → `B`.
  - `test_answer_on_pending_or_completed_session_returns_422` — xabar `Test boshlanmagan yoki yakunlangan`; `submit` dan keyin yuborilgan javob ham 422 va `exam_results.score` o'zgarmagan (Review Focus 2).
  - `test_answer_after_deadline_returns_422_and_expires_session` — `Carbon::setTestNow(now()->addMinutes(31))` → 422, sessiya `expired`.
  - `test_answer_rejects_foreign_question_and_unknown_option` — boshqa sessiya savoli → 422; `selected_option: 'Z'` → 422.
  - `test_questions_endpoint_returns_saved_answers_in_progress` — javob saqlangach `GET …/questions` → shu savolda `selected_option` `A`, `started_at` bor; `pending` sessiyada → 422.
- [ ] **Step 2:** FAIL → **Step 3:** metodlar (mavjud `findSessionAndExpire` dan foydalaning; variant tekshiruvi — `shuffled_options` dagi `key` lar) → **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `feat(test): javoblarni darhol saqlash va testni davom ettirish`.

---

### Task 3: Monitoring API

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/MonitoringController.php`
- Modify: `backend/muhofaza/routes/api.php` (alohida `Route::middleware('auth:sanctum')->prefix('monitoring')` guruhi — `deny.auditor` siz)
- Test: `backend/muhofaza/tests/Feature/Audit/MonitoringApiTest.php`

**Interfaces:**
- Consumes: Task 1 (`auditor`), Task 2 (`answered_at`).
- Produces: spec 5.1 javob shakllari aynan (`GET /api/monitoring/active`, `/finished`, `/sessions/{id}`) + plan qo'shimchasi `GET /api/monitoring/exams` → `[{id, title}]` (scope bo'yicha davriy imtihonlar, `exam_date` kamayish tartibida) — auditor `/periodic-exams` ga kira olmagani uchun "Yakunlangan" filtri shundan foydalanadi. Controller ichida `private function scoped(Builder $query, User $user): Builder` va `private function authorizeSession(User $user, ExamSession $session): void` (403).

- [ ] **Step 1: Failing tests** (fixture: `makeSession`, `start`, `PUT …/answer`, `submit` orqali holatlar):
  - `test_active_lists_only_running_sessions` — `in_progress` 1 ta (1 javob), `pending` 1, `completed` 1, vaqti o'tgan `in_progress` 1 (`started_at` 40 daqiqa oldin, duration 30) → faqat birinchisi; `answered` 1, `total` 2 (Review Focus 1).
  - `test_active_remaining_seconds_uses_server_clock` — `Carbon::setTestNow('2026-11-10 10:00:00')`, `started_at` `09:50:00`, duration 30 → `remaining_seconds` 1200 (Review Focus 4).
  - `test_scoping_by_organization` — A va B tashkilotlarida bittadan faol sessiya: A ga biriktirilgan auditor → 1 ta; tashkilotsiz auditor → 2 ta; A operatori → 1 ta; admin → 2 ta.
  - `test_session_detail_live_values` — 2 savol, biri to'g'ri (A) javob → `summary.answered` 1, `correct` 1, `score_percent` 50.0, savolda `is_correct` true va `correct_option` `A`, ikkinchisida `selected_option` null.
  - `test_session_detail_after_submit_uses_saved_values_and_grade` — topshirilgach `status` `completed`, `grade` bor, `remaining_seconds` null.
  - `test_session_detail_other_org_forbidden` — A auditori B sessiyasi → 403 (Review Focus 5).
  - `test_finished_filters` — ikki imtihon, `periodic_exam_id` filtri 1 ta; `search` ism bo'yicha; `date_from` kelajak sana → 0.
  - `test_exams_list_is_scoped` — A auditori `GET /api/monitoring/exams` → faqat A imtihonlari.
- [ ] **Step 2:** FAIL → **Step 3** → **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `feat(audit): monitoring API`.

---

### Task 4: Eski havolalarni tozalash

**Files:**
- Modify: `backend/muhofaza/app/Http/Controllers/Api/ExamSessionController.php` (`generate`)
- Test: `backend/muhofaza/tests/Feature/PeriodicExams/ParticipantsTest.php` (qo'shimcha test)

- [ ] **Step 1: Failing test** `test_generate_removes_pending_links_of_non_participants` — ishtirokchi P; ishtirokchi bo'lmagan X (`pending` sessiya), Y (`in_progress`), Z (`completed`) → `generate` (employee_ids siz) → X sessiyasi yo'q, Y va Z qoladi, P sessiyasi bor.
- [ ] **Step 2:** FAIL → **Step 3:** `employee_ids` so'rovda berilmagan holatda `ExamSession::where('periodic_exam_id')->where('status','pending')->whereNotIn('employee_id', $participantIds)->delete()` → **Step 4:** butun to'plam PASS.
- [ ] **Step 5: Commit** — `fix(exam-sessions): imtihonga kirmaganlarning boshlanmagan havolalari o'chiriladi`.

---

### Task 5: Frontend — test sahifasida avtosaqlash va davom ettirish

**Files:**
- Modify: `frontend/muhofaza/src/views/public/TestSessionView.vue`

- [ ] **Step 1:** `v-radio-group` `@update:model-value` → `publicApi.put(…/answer, {exam_session_question_id, selected_option})` (xato jim). `show` javobi `in_progress` bo'lsa `GET …/questions` → `questions`, `answers` (saqlangan `selected_option` lar), `deadline = started_at + duration` dan taymer.
- [ ] **Step 2:** `npm run build` → `✓ built`.
- [ ] **Step 3: Qo'lda (Playwright, vaqtinchalik xodim):** testni boshlab 1 javob tanlash → `PUT …/answer` 200 (network) → sahifani `reload` → test davom etadi, tanlangan variant belgilangan, taymer davom etadi (≤ boshlang'ichdan) → topshirish natija beradi.
- [ ] **Step 4: Commit** — `feat(test): avtosaqlash va qayta ochilganda davom ettirish (UI)`.

---

### Task 6: Frontend — Monitoring sahifasi

**Files:**
- Create: `frontend/muhofaza/src/views/monitoring/MonitoringView.vue`, `frontend/muhofaza/src/components/monitoring/SessionDetailDrawer.vue`
- Modify: `frontend/muhofaza/src/router/index.js` (`monitoring`), `frontend/muhofaza/src/components/layout/AppSidebar.vue` (IMTIHONLAR guruhiga `mdi-monitor-eye` "Monitoring"), `frontend/muhofaza/src/components/layout/AppHeader.vue` (sarlavha `Monitoring`)

**Interfaces:**
- Consumes: Task 3 API.
- Produces: `SessionDetailDrawer` — `open(sessionId: number)`, ichki 3 s polling (`status === 'in_progress'` bo'lsa), yopilganda to'xtaydi.

- [ ] **Step 1:** `MonitoringView` — spec 5.2: tablar, "Hozir yechilmoqda" jadvali (jarayon `v-progress-linear` + `answered/total`, qolgan vaqt har soniyada mahalliy kamayadi — har pollingda serverdan qayta sinxronlanadi, < 60 s qizil; oxirgi faollik nisbiy: `N soniya oldin`/`N daqiqa oldin`), bo'sh holat matni, `Jonli · har 5 soniyada yangilanadi`, xato belgisi; "Yakunlangan" — `v-data-table-server` (filtrlar: imtihon tanlovi `GET /api/monitoring/exams` dan; sana oralig'i, qidiruv 300 ms debounce), ball va `GradeBadge`.
- [ ] **Step 2:** `SessionDetailDrawer` — spec 5.2 kartochkalar (tanlangan to'g'ri — yashil, noto'g'ri — qizil, to'g'ri javob `mdi-check` bilan, javobsiz — kulrang `Javob berilmagan`), yuqorida xulosa (`answered/total`, `correct`, `score_percent`%, qolgan vaqt yoki baho).
- [ ] **Step 3:** `npm run build` → `✓ built`.
- [ ] **Step 4: Qo'lda (Playwright, ikki kontekst):** admin Monitoring'ni ochadi; ikkinchi kontekstda vaqtinchalik xodim testni boshlab 1 javob tanlaydi → ≤ 6 s ichida ro'yxatda `1/3`; qatorni bosish → panelda tanlangan variant rangli; xodim yana javob beradi → ≤ 4 s ichida panelda yangilanadi; topshirgach "Yakunlangan" tabida ko'rinadi.
- [ ] **Step 5: Commit** — `feat(audit): monitoring sahifasi (UI)`.

---

### Task 7: Frontend — auditor uchun rolga qarab interfeys

**Files:**
- Modify: `frontend/muhofaza/src/stores/auth.js` (`isAuditor`), `frontend/muhofaza/src/router/index.js` (guard: auditor → faqat `/monitoring`), `frontend/muhofaza/src/views/LoginView.vue` (auditor → `/monitoring`), `frontend/muhofaza/src/components/layout/AppSidebar.vue` (auditor — faqat "Monitoring"; `/retakes` va `notifStore.startPolling()` chaqirilmaydi), `frontend/muhofaza/src/components/layout/AppHeader.vue` (auditor — qo'ng'iroq belgisi yo'q), `frontend/muhofaza/src/views/settings/UsersView.vue` (rol "Auditor", chip rangi, tashkilot maydoni operator va auditor uchun; auditor uchun ixtiyoriy, hint `Bo'sh qolsa — barcha tashkilotlar`)

- [ ] **Step 1:** O'zgarishlar.
- [ ] **Step 2:** `npm run build` → `✓ built`.
- [ ] **Step 3: Qo'lda (Playwright):** admin "Foydalanuvchilar"da tashkilotsiz auditor yaratadi → shu auditor bilan kirish → `/monitoring` ochiladi; sidebar'da faqat "Monitoring"; `/employees` ga o'tish → `/monitoring`; konsolda 403 xatolari yo'q (bildirishnoma/qayta topshirish so'rovlari yuborilmagan). Vaqtinchalik auditor oxirida o'chiriladi.
- [ ] **Step 4: Commit** — `feat(audit): auditor interfeysi (UI)`.

---

### Task 8: Yakuniy tekshiruv va hujjat

**Files:**
- Modify: `README.md` ("Audit va monitoring" bo'limi: auditor roli, tashkilot qoidasi, Monitoring sahifasi, 3–5 s yangilanish, avtosaqlash)

- [ ] **Step 1:** `php artisan test --colors=never` → PASS; `npm run build` → `✓ built`.
- [ ] **Step 2: Commit** — `docs: audit va monitoring`.

# Sertifikatlar — amalga oshirish rejasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Testdan o'tgan o'quvchiga QR kodli PDF sertifikat yaratish; imzoli skanni yuklash; o'quvchi va QR orqali tekshirish/yuklab olish.

**Architecture:** Laravel tomonda `certificates` jadvali + `CertificateService` (raqamlash, ma'lumot surati, fayllar) + `CertificatePdf` (Blade → dompdf, QR PNG). Admin/operator API scope bilan, ochiq API `public_code` orqali. Vue tomonda 3 yangi sahifa va mavjud 5 ta sahifaga qo'shimchalar.

**Tech Stack:** Laravel 13 / PHP 8.3, MySQL, `barryvdh/laravel-dompdf` ^3.1, `chillerlan/php-qrcode` ^5.0, Vue 3 + Vuetify 3, axios.

**Spec:** `docs/superpowers/specs/2026-11-05-sertifikatlar-design.md`

## Global Constraints

- Ish `feature/sertifikatlar` branch'ida. `main` da commit qilinmagan nom o'zgartirish fayllari bor (README.md, .env.docker, .env.example, DatabaseSeeder.php, index.html, AppSidebar.vue, LoginView.vue, nginx/default.conf) — **har bir commit'da faqat o'z fayllaringizni `git add <path>` bilan qo'shing**, `git add -A` / `git commit -a` ishlatmang. AppSidebar.vue Task 9/10 da o'zgaradi — u faylni commit qilishdan oldin foydalanuvchidan so'rang yoki nom o'zgarishini ham shu commit'ga qo'shilishini ogohlantiring.
- Backend testlari: `cd backend/muhofaza && php artisan test --filter=<Test>`; test bazasi `muhofaza_test` (MySQL, root/root, 127.0.0.1).
- Fayllar faqat `Storage::disk('local')` (`storage/app/private`) da: `certificates/generated/{number}.pdf`, `certificates/signed/{number}-{Str::random(8)}.{ext}`.
- № formati `sprintf('%d-%04d', $year, $sequence)` (masalan `2026-0001`); ro'yxat raqami ko'rinishi `str_pad($n, 6, '0', STR_PAD_LEFT)`.
- `public_code` = `Str::random(32)`. QR qiymati = `rtrim(config('app.url'), '/') . '/sertifikat/' . $public_code`.
- "O'tgan natija" = `exam_results.is_passed = true`.
- Operator scope (mavjud kod bilan bir xil): `$user->role === 'operator' && $user->organization_id && $exam->organization_id !== $user->organization_id` → `abort(403)`; ro'yxatlarda `whereHas('periodicExam', fn ($q) => $q->where('organization_id', $user->organization_id))`.
- Foydalanuvchiga ko'rinadigan xabarlar (aynan shu matn):
  - `Imzolangan sertifikatni qayta yaratib bo'lmaydi`
  - `Natija qoniqarsiz — sertifikat berilmaydi`
  - `Sertifikat topilmadi`
  - `Sertifikat hali imzolanmagan`
  - `Chop etish uchun imzolanmagan sertifikat yo'q`
  - `Fayl topilmadi`
  - Ogohlantirishlar: `Kurs sanalari kiritilmagan`, `Soat hajmi kiritilmagan`, `Sertifikat sozlamalari to'ldirilmagan`
- Spec'dan ikki ongli farq: (1) model factory'lar o'rniga `tests/Concerns/CertificateFixtures.php` trait (8 ta modelga `HasFactory` qo'shmaslik uchun); (2) PDF chizish `CertificateService` ichida emas, alohida `CertificatePdf` klassida.

## Review Focus

1. Juda uzun F.I.O. yoki muassasa nomi — sertifikat baribir **bitta** sahifada qolishi kerak (Task 2 testi).
2. O'zbek belgilari `o‘ g‘ ʼ “ ”` PDF da to'g'ri chiqishi kerak (Task 2, `pdftotext` bilan qo'lda tekshiruv).
3. `APP_URL` oxirida `/` bo'lsa ham QR havolasida `//` bo'lmasligi kerak (Task 2 testi).
4. Diskdagi fayl yo'qolgan (volume tozalangan) bo'lsa yuklab olish 500 emas, 404 `Fayl topilmadi` qaytarishi kerak (Task 6 testi).
5. Imzoli nusxa PNG/JPG bo'lsa ochiq yuklab olish to'g'ri `Content-Type` va `inline` bilan berilishi kerak (Task 8 testi).

---

### Task 0: Branch

- [ ] **Step 1:** `git checkout -b feature/sertifikatlar` (commit qilinmagan fayllar o'z holicha qoladi).

---

### Task 1: Sxema, modellar, test muhiti

**Files:**
- Modify: `backend/muhofaza/phpunit.xml` (`DB_CONNECTION` → `mysql`, `DB_DATABASE` → `muhofaza_test`)
- Create: `backend/muhofaza/database/migrations/2026_11_05_000001_add_certificate_fields_to_exam_tables.php`
- Create: `backend/muhofaza/database/migrations/2026_11_05_000002_create_certificate_settings_table.php`
- Create: `backend/muhofaza/database/migrations/2026_11_05_000003_create_certificates_table.php`
- Create: `backend/muhofaza/app/Models/Certificate.php`, `backend/muhofaza/app/Models/CertificateSetting.php`
- Modify: `backend/muhofaza/app/Models/ExamResult.php`, `ExamType.php`, `PeriodicExam.php`
- Create: `backend/muhofaza/tests/Concerns/CertificateFixtures.php`
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificateModelTest.php`

**Interfaces:**
- Produces:
  - Ustunlar spec 3.1–3.3 bo'yicha aynan (`certificates`: `exam_result_id` unique cascade, `employee_id` cascade, `periodic_exam_id` cascade, `year`, `sequence`, `unique(year, sequence)`, `number` string(20) unique, `registration_number` unsigned int unique, `public_code` string(32) unique, `data` json, `generated_path`/`signed_path` nullable, `signed_at` nullable, `signed_by` FK users nullOnDelete).
  - `Certificate`: `$casts = ['data' => 'array', 'signed_at' => 'datetime']`, `$appends = ['status']`, `getStatusAttribute(): string` (`'signed'`|`'pending_signature'`), `registrationLabel(): string`, relations `examResult()`, `employee()`, `periodicExam()`, `signer()`; `booted()` → `deleting` ikkala faylni `local` diskdan o'chiradi.
  - `CertificateSetting::current(): CertificateSetting` — `firstOrCreate(['id' => 1])`.
  - `ExamResult::certificate(): HasOne`; `booted()` → `deleting` da `$this->certificate?->delete()`.
  - `ExamType` fillable + `program_name`, `training_hours`; `PeriodicExam` fillable + `course_start_date`, `course_end_date`, casts `date`.
  - Trait `Tests\Concerns\CertificateFixtures`:
    - `makeOrganization(?string $code = null): Organization`
    - `makeUser(string $role = 'admin', ?Organization $org = null): User`
    - `makeExam(?Organization $org = null, array $examAttrs = [], array $typeAttrs = []): PeriodicExam` — imtihon turini 2 ta savol bilan yaratadi (`points` 1, `correct_option` `'A'`, `options` `[{"key":"A",...},{"key":"B",...}]` — mavjud `QuestionController` dagi formatga qarang)
    - `makeEmployee(PeriodicExam $exam, string $name = 'Aliyev Vali G‘aniyevich'): Employee`
    - `makeResult(PeriodicExam $exam, string $grade = 'good', ?Employee $employee = null, ?Carbon $createdAt = null): ExamResult` — `ExamResult::applyGradeStatus` orqali `is_passed`
    - `makeCertificate(ExamResult $result, array $attrs = []): Certificate` — servissiz to'g'ridan-to'g'ri yozadi; `year`/`sequence`/`registration_number` uchun statik hisoblagich

- [ ] **Step 1:** `mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS muhofaza_test"`; `phpunit.xml` ni o'zgartiring; `php artisan test` → mavjud `ExampleTest` lar o'tadi (Feature `ExampleTest` `/` ga GET qiladi — agar u avvaldan yiqilsa, shunday qoldiring va qayd eting).

- [ ] **Step 2: Failing test** — `CertificateModelTest` (`RefreshDatabase`, `CertificateFixtures`):

```php
public function test_status_is_pending_until_signed_path_is_set(): void
{
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()));
    $this->assertSame('pending_signature', $cert->status);
    $cert->update(['signed_path' => 'certificates/signed/x.pdf']);
    $this->assertSame('signed', $cert->fresh()->status);
}

public function test_registration_label_is_zero_padded(): void
{
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['registration_number' => 123]);
    $this->assertSame('000123', $cert->registrationLabel());
}

public function test_deleting_certificate_removes_its_files(): void
{
    Storage::fake('local');
    Storage::disk('local')->put('certificates/generated/a.pdf', 'x');
    Storage::disk('local')->put('certificates/signed/a.png', 'y');
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()), [
        'generated_path' => 'certificates/generated/a.pdf',
        'signed_path' => 'certificates/signed/a.png',
    ]);
    $cert->delete();
    Storage::disk('local')->assertMissing(['certificates/generated/a.pdf', 'certificates/signed/a.png']);
}

public function test_deleting_exam_result_deletes_certificate_and_files(): void
{
    Storage::fake('local');
    Storage::disk('local')->put('certificates/generated/b.pdf', 'x');
    $result = $this->makeResult($this->makeExam());
    $this->makeCertificate($result, ['generated_path' => 'certificates/generated/b.pdf']);
    $result->delete();
    $this->assertSame(0, Certificate::count());
    Storage::disk('local')->assertMissing('certificates/generated/b.pdf');
}

public function test_setting_current_is_a_single_row(): void
{
    $this->assertSame(CertificateSetting::current()->id, CertificateSetting::current()->id);
    $this->assertSame(1, CertificateSetting::count());
}
```

- [ ] **Step 3:** `php artisan test --filter=CertificateModelTest` → FAIL (class/jadval yo'q).
- [ ] **Step 4:** Migratsiyalar, modellar va trait'ni yozing. `down()` metodlari ustunlarni/jadvallarni olib tashlaydi.
- [ ] **Step 5:** `php artisan test --filter=CertificateModelTest` → PASS. Ishchi bazada ham: `php artisan migrate` → 3 migratsiya muvaffaqiyatli.
- [ ] **Step 6: Commit**

```bash
git add backend/muhofaza/phpunit.xml backend/muhofaza/database/migrations/2026_11_05_* backend/muhofaza/app/Models backend/muhofaza/tests/Concerns backend/muhofaza/tests/Feature/Certificates
git commit -m "feat(certificates): sxema, modellar va test muhiti"
```

---

### Task 2: PDF shabloni va `CertificatePdf`

**Files:**
- Modify: `backend/muhofaza/composer.json`, `composer.lock` (`composer require barryvdh/laravel-dompdf:^3.1 chillerlan/php-qrcode:^5.0`)
- Create: `backend/muhofaza/resources/certificates/emblem.png`
- Create: `backend/muhofaza/resources/views/certificates/certificate.blade.php`
- Create: `backend/muhofaza/app/Services/CertificatePdf.php`
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificatePdfTest.php`

**Interfaces:**
- Consumes: `Certificate` (`number`, `registrationLabel()`, `public_code`, `data`) — Task 1.
- Produces:
  - `CertificatePdf::render(iterable $certificates): string` — PDF baytlari, har sertifikat alohida sahifa.
  - `CertificatePdf::verifyUrl(string $publicCode): string` (static).
  - `CertificatePdf::dateParts(?string $ymd): ?array` (static) → `['year' => '2026', 'day' => '07', 'month' => 'noyabr']` yoki `null`.

- [ ] **Step 1:** Paketlarni o'rnating. Gerbni tayyorlang: manba `/tmp/claude-1000/-home-eversoft-Projects-muhofaza-urtg-uz/1e738455-ee2a-47fd-b660-3cc249fb5948/scratchpad/docx/word/media/image1.png` (yo'q bo'lsa: `unzip -o ~/Desktop/sertifikat.docx word/media/image1.png -d <scratchpad>`). PHP GD bilan 400px kenglikka kichraytiring (alpha saqlansin: `imagealphablending(false)`, `imagesavealpha(true)`), natija `resources/certificates/emblem.png`, < 150 KB.

- [ ] **Step 2: Failing test** — `CertificatePdfTest`:

```php
private function pageCount(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
}

public function test_renders_single_page_pdf(): void
{
    $pdf = app(CertificatePdf::class)->render([$this->makeCertificate($this->makeResult($this->makeExam()))]);
    $this->assertStringStartsWith('%PDF', $pdf);
    $this->assertSame(1, $this->pageCount($pdf));
}

public function test_renders_one_page_per_certificate(): void
{
    $exam = $this->makeExam();
    $certs = collect(range(1, 3))->map(fn () => $this->makeCertificate($this->makeResult($exam)));
    $this->assertSame(3, $this->pageCount(app(CertificatePdf::class)->render($certs)));
}

public function test_long_name_and_institution_stay_on_one_page(): void
{
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => [
        'full_name' => str_repeat('Abdurahmonov ', 10),
        'institution_name' => str_repeat('"JAHON MEHNAT MUHOFAZASI" MCHJ ', 5),
        'program_name' => str_repeat('Mehnatni muhofaza qilish ', 6),
    ]]);
    $this->assertSame(1, $this->pageCount(app(CertificatePdf::class)->render([$cert])));
}

public function test_missing_fields_render_as_blanks(): void
{
    $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => ['full_name' => 'X']]);
    $this->assertSame(1, $this->pageCount(app(CertificatePdf::class)->render([$cert])));
}

public function test_verify_url_trims_trailing_slash(): void
{
    config(['app.url' => 'https://mehnatmuhofazasi.uz/']);
    $this->assertSame('https://mehnatmuhofazasi.uz/sertifikat/abc', CertificatePdf::verifyUrl('abc'));
}

public function test_date_parts(): void
{
    $this->assertSame(['year' => '2026', 'day' => '07', 'month' => 'noyabr'], CertificatePdf::dateParts('2026-11-07'));
    $this->assertNull(CertificatePdf::dateParts(null));
}
```

- [ ] **Step 3:** `php artisan test --filter=CertificatePdfTest` → FAIL.

- [ ] **Step 4: `CertificatePdf` ni yozing.**
  - dompdf: `Pdf::loadView('certificates.certificate', [...])->setPaper('a4', 'portrait')->setOption('defaultFont', 'DejaVu Serif')->output()`; `isRemoteEnabled` o'chiq qoladi — rasmlar faqat base64 data URI.
  - QR (php-qrcode v5): `new QROptions(['outputType' => QROutputInterface::GDIMAGE_PNG, 'outputBase64' => true, 'scale' => 6, 'eccLevel' => EccLevel::M])`, `(new QRCode($o))->render(self::verifyUrl($code))` → data URI.
  - Oylar: `yanvar, fevral, mart, aprel, may, iyun, iyul, avgust, sentabr, oktabr, noyabr, dekabr`.

- [ ] **Step 5: Blade shablonini yozing** (`$certificates` ro'yxati; har biri `page-break-after`, oxirgisidan tashqari). Ko'rinish — spec 4.3. Matn **aynan** shablon PDF dagidek (imlosi bilan):
  - `Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida` / `SERTIFIKAT` (rang `#1F5E63`) / `№ {number}`
  - F.I.O. qalin chiziq ustida, ostida `(familiyasi, ismi, otasining ismi)`
  - `{Y} yil «{d}» {oy} dan` … `{Y} yil «{d}» {oy} gacha`
  - `{institution_name}da` tagiga chizilgan, ostida `(malaka oshirish va qayta tayyorlash ta’lim muassasasining nomi)`
  - `{program_name}` qalin, tagiga chizilgan, ostida `(xodimlar malakasini oshirish dasturining nomi)`
  - `dastur bo‘yicha {training_hours} soat hajmida malaka oshirish kursida o‘qidi:`
  - `Muhr o‘rni :`
  - Imzo jadvali (3 qator): `Imtixon komissiyasi raisi` / `Ta’lim muassasi rahbari` / `Mehnatni Muhofaza qilish bo‘yicha (bosh) davlat texnik inspektori`; o'rtada chiziq va `(imzo)`; o'ngda F.I.O. tagiga chizilgan va `(F.I.O)`
  - `Shaxar (tuman) {city}` … `Sana {dd.mm.yyyy}`
  - `Ro‘yxatdan o‘tish raqami {registrationLabel}` va o'ngda ramkali katakda QR
  - Bo'sh qiymatlar: № → `_______`, sana → `«___» ___________`, soat → `____`, matnli maydonlar → `______________`.
  - Ramka: `position: fixed` div, tashqi `3px solid #1F5E63` + ichki `1px solid #1F5E63`, ~10mm chekka. Flex/grid ishlatilmaydi — jadval va `text-align`.

- [ ] **Step 6:** `php artisan test --filter=CertificatePdfTest` → PASS.
- [ ] **Step 7: Ko'z bilan tekshirish.** `php artisan tinker` orqali to'liq to'ldirilgan sertifikatni (F.I.O. `G‘ofurov Oʻtkir Shavkatovich`, muassasa `“JAHON MEHNAT MUHOFAZASI” MCHJ`) scratchpad'ga yozing; `pdftotext` natijasida `G‘ofurov`, `Oʻtkir`, `bo‘yicha`, `SERTIFIKAT` bor; `pdftoppm -png -r 60` bilan rasmga o'tkazib, `~/Desktop/sertificate_tizim.pdf` ning rasmi bilan solishtiring (tartib, ramka, gerb, QR katagi). Farqlarni tuzating.
- [ ] **Step 8: Commit** — `composer.json composer.lock resources/certificates resources/views/certificates app/Services/CertificatePdf.php tests/Feature/Certificates/CertificatePdfTest.php` → `feat(certificates): PDF shabloni va QR`.

---

### Task 3: `CertificateService` — yaratish, raqamlash, imzoli fayl

**Files:**
- Create: `backend/muhofaza/app/Exceptions/CertificateException.php` (`extends \DomainException`)
- Create: `backend/muhofaza/app/Services/CertificateService.php`
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificateServiceTest.php`

**Interfaces:**
- Consumes: `CertificatePdf::render()` (Task 2), modellar (Task 1).
- Produces:
  - `issue(ExamResult $result): Certificate` — yangi bo'lsa `wasRecentlyCreated === true`; o'tmagan natija → `CertificateException('Natija qoniqarsiz — sertifikat berilmaydi')`; imzolangan → `CertificateException("Imzolangan sertifikatni qayta yaratib bo'lmaydi")`.
  - `buildData(ExamResult $result): array` — spec 3.4 kalitlari, aynan shu tartibda; sanalar `Y-m-d` string; `program_name` bo'sh bo'lsa `examType->name`.
  - `storeSigned(Certificate $certificate, UploadedFile $file, User $by): Certificate`
  - `missingFieldWarnings(PeriodicExam $exam): array` — Global Constraints dagi 3 ogohlantirishdan mos kelganlari. "Sozlamalar to'ldirilmagan" = 5 ta sozlama maydonidan birortasi bo'sh.

- [ ] **Step 1: Failing test** — `CertificateServiceTest` (`Storage::fake('local')` setUp'da):

```php
public function test_numbers_restart_each_year_and_registration_continues(): void
{
    $exam = $this->makeExam();
    $svc = app(CertificateService::class);
    $a = $svc->issue($this->makeResult($exam, 'good', null, Carbon::parse('2026-03-01')));
    $b = $svc->issue($this->makeResult($exam, 'good', null, Carbon::parse('2026-05-01')));
    $c = $svc->issue($this->makeResult($exam, 'good', null, Carbon::parse('2027-01-10')));
    $this->assertSame(['2026-0001', '2026-0002', '2027-0001'], [$a->number, $b->number, $c->number]);
    $this->assertSame([1, 2, 3], [$a->registration_number, $b->registration_number, $c->registration_number]);
    $this->assertSame(32, strlen($a->public_code));
}

public function test_issue_builds_data_snapshot(): void
{
    CertificateSetting::current()->update([
        'institution_name' => '"JAHON MEHNAT MUHOFAZASI" MCHJ', 'commission_chair_name' => 'J. Xasanov',
        'director_name' => 'A. Bazarova', 'inspector_name' => 'M. Yurev', 'city' => 'Urganch sh.',
    ]);
    $exam = $this->makeExam(null, ['course_start_date' => '2026-11-01', 'course_end_date' => '2026-11-07'],
        ['program_name' => 'Mehnatni muhofaza qilish', 'training_hours' => 72]);
    $result = $this->makeResult($exam, 'good', $this->makeEmployee($exam, 'Aliyev Vali'), Carbon::parse('2026-11-07 10:00'));
    $this->assertSame([
        'full_name' => 'Aliyev Vali', 'institution_name' => '"JAHON MEHNAT MUHOFAZASI" MCHJ',
        'program_name' => 'Mehnatni muhofaza qilish', 'training_hours' => 72,
        'course_start_date' => '2026-11-01', 'course_end_date' => '2026-11-07',
        'commission_chair_name' => 'J. Xasanov', 'director_name' => 'A. Bazarova', 'inspector_name' => 'M. Yurev',
        'city' => 'Urganch sh.', 'issue_date' => '2026-11-07',
    ], app(CertificateService::class)->issue($result)->data);
}

public function test_program_name_falls_back_to_exam_type_name(): void
{
    $exam = $this->makeExam(null, [], ['name' => 'Elektr xavfsizligi', 'program_name' => null]);
    $this->assertSame('Elektr xavfsizligi', app(CertificateService::class)->buildData($this->makeResult($exam))['program_name']);
}

public function test_issue_writes_generated_pdf(): void
{
    $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
    $this->assertSame("certificates/generated/{$cert->number}.pdf", $cert->generated_path);
    $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($cert->generated_path));
}

public function test_reissue_unsigned_keeps_numbers_and_refreshes_data(): void
{
    $svc = app(CertificateService::class);
    $result = $this->makeResult($this->makeExam());
    $first = $svc->issue($result);
    CertificateSetting::current()->update(['city' => 'Xiva sh.']);
    $second = $svc->issue($result->fresh());
    $this->assertFalse($second->wasRecentlyCreated);
    $this->assertSame([$first->id, $first->number, $first->registration_number, $first->public_code],
        [$second->id, $second->number, $second->registration_number, $second->public_code]);
    $this->assertSame('Xiva sh.', $second->data['city']);
}

public function test_reissue_signed_throws(): void
{
    $svc = app(CertificateService::class);
    $result = $this->makeResult($this->makeExam());
    $svc->issue($result)->update(['signed_path' => 'certificates/signed/x.pdf']);
    $this->expectExceptionObject(new CertificateException("Imzolangan sertifikatni qayta yaratib bo'lmaydi"));
    $svc->issue($result->fresh());
}

public function test_issue_failed_result_throws(): void
{
    $this->expectException(CertificateException::class);
    app(CertificateService::class)->issue($this->makeResult($this->makeExam(), 'unsatisfactory'));
}

public function test_store_signed_replaces_previous_file(): void
{
    $svc = app(CertificateService::class);
    $user = $this->makeUser();
    $cert = $svc->issue($this->makeResult($this->makeExam()));
    $svc->storeSigned($cert, UploadedFile::fake()->image('scan.png'), $user);
    $old = $cert->fresh()->signed_path;
    $svc->storeSigned($cert->fresh(), UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'), $user);
    $fresh = $cert->fresh();
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($fresh->signed_path);
    $this->assertStringStartsWith("certificates/signed/{$cert->number}-", $fresh->signed_path);
    $this->assertStringEndsWith('.pdf', $fresh->signed_path);
    $this->assertSame($user->id, $fresh->signed_by);
    $this->assertNotNull($fresh->signed_at);
}

public function test_missing_field_warnings(): void
{
    $exam = $this->makeExam(null, ['course_start_date' => null], ['training_hours' => null]);
    $this->assertSame(
        ['Kurs sanalari kiritilmagan', 'Soat hajmi kiritilmagan', "Sertifikat sozlamalari to'ldirilmagan"],
        app(CertificateService::class)->missingFieldWarnings($exam)
    );
}
```

- [ ] **Step 2:** `php artisan test --filter=CertificateServiceTest` → FAIL.
- [ ] **Step 3: `CertificateService` ni yozing.** Raqam ajratish (testlar shartlamaydigan qism):

```php
for ($attempt = 1; ; $attempt++) {
    try {
        return DB::transaction(function () use ($result, $issueDate) {
            $year = $issueDate->year;
            $sequence = (int) Certificate::where('year', $year)->lockForUpdate()->max('sequence') + 1;
            $registration = (int) Certificate::lockForUpdate()->max('registration_number') + 1;
            return Certificate::create([... 'number' => sprintf('%d-%04d', $year, $sequence), ...]);
        });
    } catch (UniqueConstraintViolationException $e) {
        if ($attempt >= 3) throw $e;
    }
}
```

  `issue_date` = `$result->created_at` sana qismi. PDF yaratish va `generated_path` yozish yangi va qayta yaratishda bir xil yo'l bilan (`Storage::disk('local')->put(...)`).
- [ ] **Step 4:** `php artisan test --filter=CertificateServiceTest` → PASS.
- [ ] **Step 5: Commit** — `app/Exceptions/CertificateException.php app/Services/CertificateService.php tests/Feature/Certificates/CertificateServiceTest.php` → `feat(certificates): yaratish servisi va raqamlash`.

---

### Task 4: Onlayn test bilan bog'lash

**Files:**
- Modify: `backend/muhofaza/app/Http/Controllers/Api/Public/ExamSessionController.php` (`show`, `submit`)
- Modify: `backend/muhofaza/app/Models/Certificate.php` (`publicSummary()`)
- Modify: `backend/muhofaza/tests/Concerns/CertificateFixtures.php` (`makeSession()`)
- Test: `backend/muhofaza/tests/Feature/Certificates/OnlineTestCertificateTest.php`

**Interfaces:**
- Consumes: `CertificateService::issue()` (Task 3).
- Produces:
  - `Certificate::publicSummary(): array` → `['number' => ..., 'status' => ..., 'public_code' => ...]`.
  - `show` va `submit` javoblarida `certificate` kaliti: `publicSummary()` yoki `null`. `show` uchun: `$session->examResult?->certificate`.
  - Trait: `makeSession(PeriodicExam $exam, Employee $employee): ExamSession` (`status` `pending`, `token` `Str::random(48)`, `duration_minutes` 30, `total_points` = savollar ballari yig'indisi).

- [ ] **Step 1: Failing test:**

```php
private function takeTest(ExamSession $s, bool $allCorrect): TestResponse
{
    $questions = $this->postJson("/api/public/exam-sessions/{$s->token}/start")->json('questions');
    $answers = collect($questions)->map(fn ($q) => [
        'exam_session_question_id' => $q['exam_session_question_id'],
        'selected_option' => $allCorrect ? 'A' : 'B',
    ])->all();
    return $this->postJson("/api/public/exam-sessions/{$s->token}/submit", ['answers' => $answers]);
}

public function test_passing_submission_issues_certificate(): void
{
    Storage::fake('local');
    $exam = $this->makeExam();
    $res = $this->takeTest($this->makeSession($exam, $this->makeEmployee($exam)), true);
    $res->assertOk()->assertJsonPath('certificate.status', 'pending_signature')
        ->assertJsonPath('certificate.number', now()->year . '-0001');
    $this->assertSame(1, Certificate::count());
}

public function test_failing_submission_has_no_certificate(): void
{
    Storage::fake('local');
    $exam = $this->makeExam();
    $this->takeTest($this->makeSession($exam, $this->makeEmployee($exam)), false)
        ->assertOk()->assertJsonPath('certificate', null);
    $this->assertSame(0, Certificate::count());
}

public function test_certificate_failure_does_not_break_submission(): void
{
    $this->mock(CertificateService::class)->shouldReceive('issue')->andThrow(new RuntimeException('dompdf'));
    $exam = $this->makeExam();
    $this->takeTest($this->makeSession($exam, $this->makeEmployee($exam)), true)
        ->assertOk()->assertJsonPath('certificate', null);
    $this->assertSame(1, ExamResult::count());
}

public function test_show_includes_certificate_after_completion(): void
{
    Storage::fake('local');
    $exam = $this->makeExam();
    $s = $this->makeSession($exam, $this->makeEmployee($exam));
    $this->takeTest($s, true);
    $this->getJson("/api/public/exam-sessions/{$s->token}")
        ->assertJsonPath('status', 'completed')->assertJsonStructure(['certificate' => ['number', 'status', 'public_code']]);
}
```

  (Savol `options` formati va `passing_score` standart qiymati (60) "hammasi to'g'ri → o'tdi, hammasi xato → yiqildi" bo'lishini ta'minlashi kerak — fixture'ni shunga moslang.)
- [ ] **Step 2:** `php artisan test --filter=OnlineTestCertificateTest` → FAIL.
- [ ] **Step 3:** `submit` ga `CertificateService $certificates` method injection; spec 4.6 dagi try/catch (`report($e)`), faqat `$examResult->is_passed` bo'lsa. `show` va `submit` javobiga `certificate`.
- [ ] **Step 4:** Testlar PASS; oldingi barcha `Certificates` testlari ham PASS (`php artisan test --filter=Certificates`).
- [ ] **Step 5: Commit** → `feat(certificates): onlayn testdan o'tilganda avtomatik sertifikat`.

---

### Task 5: Admin API — ro'yxat, yaratish, qayta yaratish, chop etish

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/CertificateController.php`
- Modify: `backend/muhofaza/routes/api.php` (`auth:sanctum` guruhiga)
- Modify: `backend/muhofaza/app/Models/Certificate.php` (`scopeVisibleTo`)
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificateAdminApiTest.php`

**Interfaces:**
- Consumes: `CertificateService::issue()`, `missingFieldWarnings()`; `CertificatePdf::render()`.
- Produces (yo'llar — `/api/certificates/print` va `/generate` `{id}` route'laridan **oldin** ro'yxatga olinadi):
  - `GET /api/certificates` → Laravel paginator JSON (`data`, `total`, `current_page`, `per_page`); query: `organization_id`, `periodic_exam_id`, `status` (`signed`|`pending_signature`), `search` (`employee.full_name` yoki `number` LIKE), `page`, `per_page` (standart 25). Har element: sertifikat maydonlari + `status` + `employee.organization` + `periodicExam`. Tartib: `id desc`.
  - `POST /api/certificates/generate` `{periodic_exam_id}` → `{created, regenerated, skipped_signed, warnings}`.
  - `POST /api/certificates/{id}/regenerate` → yangilangan sertifikat; `CertificateException` → 422 `{message}`.
  - `GET /api/certificates/print?periodic_exam_id=X` → `application/pdf`, `Content-Disposition: attachment; filename="sertifikatlar-{X}.pdf"`; imzolanmaganlar yo'q → 404 `{message: "Chop etish uchun imzolanmagan sertifikat yo'q"}`.
  - `Certificate::scopeVisibleTo(Builder $q, User $user)`; controller ichida `private function authorizeExam(User $user, PeriodicExam $exam): void`.

- [ ] **Step 1: Failing test** (`Sanctum::actingAs`, `Storage::fake('local')`):

```php
public function test_index_scopes_operator_to_own_organization(): void
{
    [$examA, $examB] = [$this->makeExam($orgA = $this->makeOrganization('A')), $this->makeExam($this->makeOrganization('B'))];
    $this->makeCertificate($this->makeResult($examA));
    $this->makeCertificate($this->makeResult($examB));
    Sanctum::actingAs($this->makeUser('operator', $orgA));
    $this->getJson('/api/certificates')->assertOk()->assertJsonPath('total', 1);
    Sanctum::actingAs($this->makeUser('admin'));
    $this->getJson('/api/certificates')->assertJsonPath('total', 2);
}

public function test_index_filters_by_status_and_search(): void
{
    $exam = $this->makeExam();
    $this->makeCertificate($this->makeResult($exam, 'good', $this->makeEmployee($exam, 'Karimov Anvar')), ['signed_path' => 'x.pdf']);
    $this->makeCertificate($this->makeResult($exam, 'good', $this->makeEmployee($exam, 'Saidova Nilufar')));
    Sanctum::actingAs($this->makeUser());
    $this->getJson('/api/certificates?status=signed')->assertJsonPath('total', 1)->assertJsonPath('data.0.employee.full_name', 'Karimov Anvar');
    $this->getJson('/api/certificates?search=Nilu')->assertJsonPath('total', 1);
}

public function test_generate_creates_regenerates_and_skips_signed(): void
{
    $exam = $this->makeExam();
    $svc = app(CertificateService::class);
    $svc->issue($this->makeResult($exam))->update(['signed_path' => 'certificates/signed/s.pdf']);
    $svc->issue($this->makeResult($exam));
    $this->makeResult($exam);
    $failed = $this->makeResult($exam, 'unsatisfactory');
    Sanctum::actingAs($this->makeUser());
    $this->postJson('/api/certificates/generate', ['periodic_exam_id' => $exam->id])
        ->assertOk()->assertJson(['created' => 1, 'regenerated' => 1, 'skipped_signed' => 1]);
    $this->assertNull($failed->fresh()->certificate);
}

public function test_generate_returns_warnings_when_fields_missing(): void
{
    $exam = $this->makeExam(null, ['course_start_date' => null]);
    $this->makeResult($exam);
    Sanctum::actingAs($this->makeUser());
    $this->postJson('/api/certificates/generate', ['periodic_exam_id' => $exam->id])
        ->assertJsonFragment(['Kurs sanalari kiritilmagan']);
}

public function test_regenerate_signed_returns_422(): void
{
    $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
    $cert->update(['signed_path' => 'certificates/signed/s.pdf']);
    Sanctum::actingAs($this->makeUser());
    $this->postJson("/api/certificates/{$cert->id}/regenerate")
        ->assertStatus(422)->assertJsonPath('message', "Imzolangan sertifikatni qayta yaratib bo'lmaydi");
}

public function test_print_returns_pdf_of_unsigned_or_404(): void
{
    $exam = $this->makeExam();
    Sanctum::actingAs($this->makeUser());
    $this->get("/api/certificates/print?periodic_exam_id={$exam->id}")->assertNotFound();
    app(CertificateService::class)->issue($this->makeResult($exam));
    $res = $this->get("/api/certificates/print?periodic_exam_id={$exam->id}")->assertOk();
    $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
    $this->assertStringStartsWith('%PDF', $res->getContent());
}

public function test_operator_cannot_generate_or_print_for_other_org(): void
{
    $exam = $this->makeExam($this->makeOrganization('B'));
    Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization('A')));
    $this->postJson('/api/certificates/generate', ['periodic_exam_id' => $exam->id])->assertForbidden();
    $this->get("/api/certificates/print?periodic_exam_id={$exam->id}")->assertForbidden();
}
```

- [ ] **Step 2:** FAIL → **Step 3:** controller, scope, route'lar → **Step 4:** `php artisan test --filter=Certificates` PASS.
- [ ] **Step 5: Commit** → `feat(certificates): admin API — ro'yxat, yaratish, chop etish`.

---

### Task 6: Admin API — yuklab olish, imzoli nusxa, o'chirish

**Files:**
- Modify: `backend/muhofaza/app/Http/Controllers/Api/CertificateController.php`, `backend/muhofaza/routes/api.php`
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificateFilesApiTest.php`

**Interfaces:**
- Consumes: `CertificateService::storeSigned()`; `authorizeExam()` (Task 5).
- Produces:
  - `GET /api/certificates/{id}/download?type=generated|signed` (standart `generated`) → attachment; fayl nomi `sertifikat-{number}.pdf` yoki `sertifikat-{number}-imzoli.{ext}`; yo'l bo'sh yoki diskda yo'q → 404 `{message: "Fayl topilmadi"}`.
  - `POST /api/certificates/{id}/signed` multipart `file`: `required|file|mimes:pdf,jpg,jpeg,png|max:10240` → yangilangan sertifikat JSON.
  - `DELETE /api/certificates/{id}` → `{message: "O'chirildi"}`.

- [ ] **Step 1: Failing test:**

```php
public function test_upload_signed_accepts_pdf_and_png(): void { /* PNG → 200, status signed; keyin PDF → 200, signed_path .pdf bilan tugaydi */ }

public function test_upload_rejects_wrong_type_and_oversize(): void
{
    $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
    Sanctum::actingAs($this->makeUser());
    $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->create('a.docx', 10)], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('file');
    $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->create('a.pdf', 11000, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertStatus(422);
}

public function test_download_generated_and_signed(): void
{
    /* generated → 200, Content-Disposition da "sertifikat-{number}.pdf";
       PNG yuklab, ?type=signed → 200, "sertifikat-{number}-imzoli.png" */
}

public function test_download_missing_file_returns_404(): void
{
    $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
    Storage::disk('local')->delete($cert->generated_path);
    Sanctum::actingAs($this->makeUser());
    $this->getJson("/api/certificates/{$cert->id}/download")->assertNotFound()->assertJsonPath('message', 'Fayl topilmadi');
    $this->getJson("/api/certificates/{$cert->id}/download?type=signed")->assertNotFound();
}

public function test_destroy_deletes_certificate_and_files(): void { /* 200, Certificate::count() 0, generated_path missing */ }

public function test_operator_other_org_is_forbidden(): void
{
    /* B tashkiloti sertifikati, A operatori: download, signed, DELETE → hammasi 403 */
}
```

  (Izohdagi testlarni ko'rsatilgan tasdiqlar bilan to'liq yozing.)
- [ ] **Step 2:** FAIL → **Step 3:** metodlar va route'lar; yuklab olish `Storage::disk('local')->download($path, $name)` → **Step 4:** `--filter=Certificates` PASS.
- [ ] **Step 5: Commit** → `feat(certificates): yuklab olish, imzoli nusxa yuklash, o'chirish`.

---

### Task 7: Sozlamalar API va imtihon maydonlari

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/CertificateSettingController.php`
- Modify: `backend/muhofaza/routes/api.php` (`role:admin` guruhi: `GET` va `PUT /api/certificate-settings`)
- Modify: `backend/muhofaza/app/Http/Controllers/Api/ExamTypeController.php` (store/update validatsiya)
- Modify: `backend/muhofaza/app/Http/Controllers/Api/PeriodicExamController.php` (store/update validatsiya)
- Modify: `backend/muhofaza/app/Http/Controllers/Api/ExamResultController.php` (`index` → `with([..., 'certificate'])`)
- Test: `backend/muhofaza/tests/Feature/Certificates/CertificateSettingsApiTest.php`

**Interfaces:**
- Produces:
  - `GET /api/certificate-settings` → 5 maydon + `id`; `PUT` → har biri `nullable|string|max:255`, javob — yangilangan qator.
  - Validatsiya: `program_name` `nullable|string|max:255`; `training_hours` `nullable|integer|min:1|max:2000`; `course_start_date` `nullable|date`; `course_end_date` `nullable|date|after_or_equal:course_start_date`.
  - `GET /api/exam-results` elementlarida `certificate` (yoki `null`).

- [ ] **Step 1: Failing test:** admin GET/PUT roundtrip (`city` = `Urganch sh.`); operator GET → 403; `PUT /api/exam-types/{id}` `{program_name, training_hours: 72}` saqlanadi; `PUT /api/periodic-exams/{id}` `course_end_date` < `course_start_date` → 422 (`course_end_date` xatosi), to'g'ri sanalar → 200 va saqlanadi; `GET /api/exam-results?periodic_exam_id=X` → `0.certificate.number` mavjud.
- [ ] **Step 2:** FAIL → **Step 3:** amalga oshiring → **Step 4:** `--filter=Certificates` PASS; butun to'plam `php artisan test` ham.
- [ ] **Step 5: Commit** → `feat(certificates): sozlamalar API va kurs maydonlari`.

---

### Task 8: Ochiq sertifikat API

**Files:**
- Create: `backend/muhofaza/app/Http/Controllers/Api/Public/CertificateController.php`
- Modify: `backend/muhofaza/routes/api.php` (`public/certificates` prefiksi, auth'siz)
- Test: `backend/muhofaza/tests/Feature/Certificates/PublicCertificateApiTest.php`

**Interfaces:**
- Produces:
  - `GET /api/public/certificates/{code}` → `{number, registration_number (registrationLabel), full_name, program_name, institution_name, training_hours, course_start_date, course_end_date, issue_date, status}` — boshqa hech narsa (ID, fayl yo'llari yo'q). Topilmasa 404 `{message: "Sertifikat topilmadi"}`.
  - `GET /api/public/certificates/{code}/download` → imzoli fayl `inline` (`Storage::disk('local')->response($path, "sertifikat-{number}.{ext}", [], 'inline')`); imzolanmagan → 404 `Sertifikat hali imzolanmagan`; fayl yo'q → 404 `Fayl topilmadi`.

- [ ] **Step 1: Failing test:**

```php
public function test_show_unsigned_returns_details_without_internal_fields(): void
{
    $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
    $this->getJson("/api/public/certificates/{$cert->public_code}")
        ->assertOk()->assertJsonPath('status', 'pending_signature')->assertJsonPath('number', $cert->number)
        ->assertJsonMissingPath('generated_path')->assertJsonMissingPath('signed_path')->assertJsonMissingPath('id');
}

public function test_download_unsigned_returns_404(): void
{
    $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
    $this->getJson("/api/public/certificates/{$cert->public_code}/download")
        ->assertNotFound()->assertJsonPath('message', 'Sertifikat hali imzolanmagan');
}

public function test_download_signed_png_is_inline_with_image_type(): void
{
    $svc = app(CertificateService::class);
    $cert = $svc->issue($this->makeResult($this->makeExam()));
    $svc->storeSigned($cert, UploadedFile::fake()->image('scan.png'), $this->makeUser());
    $res = $this->get("/api/public/certificates/{$cert->public_code}/download")->assertOk();
    $this->assertSame('image/png', $res->headers->get('Content-Type'));
    $this->assertStringStartsWith('inline', $res->headers->get('Content-Disposition'));
}

public function test_unknown_code_returns_404(): void
{
    $this->getJson('/api/public/certificates/nope')->assertNotFound()->assertJsonPath('message', 'Sertifikat topilmadi');
}
```

- [ ] **Step 2:** FAIL → **Step 3:** amalga oshiring → **Step 4:** `php artisan test` (to'liq) PASS.
- [ ] **Step 5: Commit** → `feat(certificates): ochiq tekshirish API`.

---

### Task 9: Frontend — sozlamalar sahifasi va forma maydonlari

**Files:**
- Create: `frontend/muhofaza/src/views/settings/CertificateSettingsView.vue`
- Modify: `frontend/muhofaza/src/router/index.js` (`settings/certificate` — `AppLayout` bolasi)
- Modify: `frontend/muhofaza/src/components/layout/AppSidebar.vue` (admin bloki: "Foydalanuvchilar" ostida `mdi-certificate-outline` "Sertifikat sozlamalari" → `/settings/certificate`)
- Modify: `frontend/muhofaza/src/views/exams/ExamTypesView.vue` (`defaultForm`, tahrirlash xaritasi, forma: "Dastur nomi" hint `Bo'sh bo'lsa imtihon turi nomi ishlatiladi`, "Soat hajmi" `type="number"`)
- Modify: `frontend/muhofaza/src/views/exams/PeriodicExamsView.vue` (`defaultForm`, tahrirlash xaritasi, forma: "Kurs boshlanishi", "Kurs tugashi" — mavjud `exam_date` maydoni uslubida)

**Interfaces:**
- Consumes: `GET/PUT /api/certificate-settings`, exam-types/periodic-exams yangi maydonlari (Task 7).

- [ ] **Step 1:** Sozlamalar sahifasi: 5 ta `v-text-field` (`Ta'lim muassasasi nomi`, `Imtihon komissiyasi raisi (F.I.O)`, `Ta'lim muassasasi rahbari (F.I.O)`, `Davlat texnik inspektori (F.I.O)`, `Shahar (tuman)`), "Saqlash" tugmasi, muvaffaqiyatda snackbar `Saqlandi`. Mavjud `UsersView.vue` uslubiga (karta, `rounded="xl"`) moslang.
- [ ] **Step 2:** Forma maydonlari va sidebar/router.
- [ ] **Step 3:** `cd frontend/muhofaza && npm run build` → `✓ built`.
- [ ] **Step 4: Qo'lda:** backend (`php artisan serve --port=8000`) va `npm run dev`; admin bilan kiring → sozlamalarni saqlang, sahifani yangilang → qiymatlar turadi; imtihon turi va davriy imtihonni tahrirlang → yangi maydonlar saqlanadi; operator bilan kiring → "Sertifikat sozlamalari" ko'rinmaydi.
- [ ] **Step 5: Commit** (AppSidebar.vue haqidagi Global Constraints eslatmasiga qarang) → `feat(certificates): sozlamalar sahifasi va kurs maydonlari (UI)`.

---

### Task 10: Frontend — "Sertifikatlar" sahifasi va imtihon sahifasi

**Files:**
- Create: `frontend/muhofaza/src/services/download.js`
- Create: `frontend/muhofaza/src/views/certificates/CertificatesView.vue`
- Modify: `frontend/muhofaza/src/router/index.js` (`certificates`)
- Modify: `frontend/muhofaza/src/components/layout/AppSidebar.vue` (IMTIHONLAR guruhi oxiriga `mdi-certificate` "Sertifikatlar" → `/certificates`)
- Modify: `frontend/muhofaza/src/views/exams/ExamDetailView.vue`

**Interfaces:**
- Consumes: Task 5–6 endpoint'lari; `GET /api/exam-results` dagi `certificate`.
- Produces:
  - `downloadFile(url: string, params?: object, fallbackName?: string): Promise<void>` — `api.get(url, { params, responseType: 'blob' })`, fayl nomi `Content-Disposition` dan (bo'lmasa `fallbackName`), `URL.createObjectURL` + vaqtinchalik `<a download>`, keyin `revokeObjectURL`.
  - `openFile(url: string, params?: object): Promise<void>` — blob'ni yangi oynada ochadi.
  - Backend CORS: dev rejimida `Content-Disposition` header'i o'qilishi uchun `config/cors.php` da `'exposed_headers' => ['Content-Disposition']` — bu faylni ham shu task'da o'zgartiring.

- [ ] **Step 1:** `download.js`.
- [ ] **Step 2:** `CertificatesView.vue` — spec 5.1: `v-data-table-server` (`items-length` = `total`), filtrlar (tashkilot — `authStore.isAdmin` bo'lsa; imtihon — `/periodic-exams` dan; holat; qidiruv debounce 300ms), `route.query.periodic_exam_id` boshlang'ich filtr. Amallar: PDF (`downloadFile('/certificates/{id}/download')`), yuklash (yashirin `<input type="file" accept=".pdf,.jpg,.jpeg,.png">` → `FormData` POST, xatoda 422 xabari), ko'rish (`openFile(..., {type:'signed'})`, faqat `signed`), qayta yaratish (faqat `pending_signature`), o'chirish (`ConfirmDialog`). Holat chiplari: `pending_signature` → warning `Imzo kutilmoqda`; `signed` → success `Imzolangan`.
- [ ] **Step 3:** `ExamDetailView.vue` — natijalar kartasi sarlavhasiga ikki tugma: `Sertifikat yaratish` (`mdi-certificate`, POST generate → snackbar `Yaratildi: {created}, yangilandi: {regenerated}, imzolangan (o'zgarmadi): {skipped_signed}` + `warnings` bo'lsa `v-alert type="warning"` ro'yxati; keyin natijalarni qayta yuklash) va `Imzolash uchun chop etish (PDF)` (`downloadFile('/certificates/print', {periodic_exam_id})`, 404 da `Chop etish uchun imzolanmagan sertifikat yo'q`). `resultHeaders` ga `{ title: 'Sertifikat', key: 'certificate', sortable: false }`; slot: `№` + holat chipi, bosilganda `router.push('/certificates?periodic_exam_id=' + id)`; sertifikat yo'q → `—`.
- [ ] **Step 4:** `npm run build` → `✓ built`.
- [ ] **Step 5: Qo'lda:** imtihonda o'tgan natija bilan "Sertifikat yaratish" → ustunda № paydo bo'ladi; "Chop etish" → PDF yuklanadi; "Sertifikatlar" sahifasida PNG yuklang → holat "Imzolangan", "ko'rish" ochiladi; qayta yaratish tugmasi yo'qoladi; o'chirish ishlaydi; operator boshqa tashkilot sertifikatlarini ko'rmaydi.
- [ ] **Step 6: Commit** → `feat(certificates): sertifikatlar sahifasi va imtihon integratsiyasi (UI)`.

---

### Task 11: Frontend — QR tekshirish sahifasi va o'quvchi ekrani

**Files:**
- Create: `frontend/muhofaza/src/views/public/CertificateVerifyView.vue`
- Modify: `frontend/muhofaza/src/router/index.js` (`/sertifikat/:code` — `AppLayout` dan tashqarida, `meta` yo'q)
- Modify: `frontend/muhofaza/src/views/public/TestSessionView.vue`

**Interfaces:**
- Consumes: Task 8 endpoint'lari; Task 4 dagi `certificate` maydoni.
- Yuklab olish havolasi: `${publicApi.defaults.baseURL}/public/certificates/${code}/download` (oddiy `<a href target="_blank">`, auth kerak emas).

- [ ] **Step 1:** `CertificateVerifyView.vue` — `TestSessionView` dagi kabi alohida `publicApi`, sahifa fon/karta uslubi ham o'sha. Holatlar:
  - yuklanmoqda → `v-progress-circular`;
  - topildi → yashil `mdi-check-decagram` + `Sertifikat haqiqiy`; qatorlar: `F.I.O.`, `Sertifikat №`, `Ro'yxatdan o'tish raqami`, `Dastur`, `Ta'lim muassasasi`, `Kurs muddati` (`dd.mm.yyyy – dd.mm.yyyy`), `Soat hajmi`, `Berilgan sana`;
  - `signed` → katta tugma `Imzolangan sertifikatni yuklab olish` (`mdi-download`);
  - `pending_signature` → `v-alert type="info"` `Mas'ul shaxslar imzosi kutilmoqda`;
  - 404 → `mdi-alert-circle` + `Sertifikat topilmadi`.
  - Sahifa telefon kengligida (360px) gorizontal scroll'siz.
- [ ] **Step 2:** `TestSessionView.vue`:
  - natija ekrani (`result`) — `result.certificate` bo'lsa `v-alert type="success"`: `Sertifikatingiz yaratildi (№ {number}). Mas'ul shaxslar imzolagandan so'ng shu havolada yuklab olishingiz mumkin.`;
  - `completed` kartasi — `session.certificate?.status === 'signed'` → `Sertifikatni yuklab olish` tugmasi (yuqoridagi havola); `pending_signature` → `Sertifikat № {number} — imzo kutilmoqda`.
- [ ] **Step 3:** `npm run build` → `✓ built`.
- [ ] **Step 4: Qo'lda:** `/sertifikat/{code}` ni imzolanmagan va imzolangan holatda, noto'g'ri kod bilan oching; brauzer devtools'da 375px kenglikda tekshiring; test havolasini qayta oching → yuklab olish tugmasi.
- [ ] **Step 5: Commit** → `feat(certificates): QR tekshirish sahifasi va o'quvchi ekrani`.

---

### Task 12: Yakuniy tekshiruv va hujjat

**Files:**
- Modify: `README.md` (yangi bo'lim "Sertifikatlar": jarayon 4 qadamda, birinchi deploydan keyin to'ldiriladigan maydonlar, `APP_URL` QR uchun muhimligi) — README'da commit qilinmagan nom o'zgarishlari bor, commit'dan oldin foydalanuvchiga ayting.

- [ ] **Step 1:** `cd backend/muhofaza && php artisan test` → hammasi PASS (oldindan yiqilib turgan testlar bo'lsa — qayd eting).
- [ ] **Step 2:** `cd frontend/muhofaza && npm run build` → `✓ built`.
- [ ] **Step 3: To'liq oqim (qo'lda):** savollari bor imtihon → test havolasi yaratish → havolani ochib testdan o'tish → natija ekranida sertifikat xabari → admin: "Sertifikatlar" → PDF yuklab olish → PDF ni `pdftoppm` bilan rasmga o'tkazib shablon bilan solishtirish → PNG/PDF "imzoli" fayl yuklash → `/sertifikat/{code}` → yuklab olish ishlaydi → test havolasida ham yuklab olish tugmasi.
- [ ] **Step 4:** QR ni telefon bilan skanerlash foydalanuvchiga qoladi (mahalliy tarmoqda `APP_URL` ni kompyuter IP'siga qo'yib, `php artisan serve --host=0.0.0.0`) — buni yakuniy hisobotda aniq ayting.
- [ ] **Step 5: Commit** (`git add README.md` faqat foydalanuvchi roziligi bilan) → `docs: sertifikatlar bo'limi`.

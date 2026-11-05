# Sertifikatlar — dizayn hujjati

**Sana:** 2026-11-05
**Holat:** tasdiqlangan dizayn, reja kutilmoqda
**Shablon:** `~/Desktop/sertificate_tizim.pdf` (ko'rinish), `~/Desktop/sertifikat.docx` (gerb rasmi `word/media/image1.png`)

## 1. Maqsad

Testdan o'tgan o'quvchiga tizim "Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida SERTIFIKAT" ni PDF ko'rinishida, QR kod bilan yaratadi. Admin yoki operator PDF ni yuklab oladi, mas'ul shaxslarga qo'lda imzo qo'ydiradi va muhr bostiradi, skanerlangan imzoli nusxani tizimga yuklaydi. O'quvchi sertifikatini test havolasi orqali ko'radi. QR kod skanerlanganda tekshirish sahifasi ochiladi va undan imzolangan sertifikatni yuklab olish mumkin.

### Muvaffaqiyat mezonlari

- Onlayn testdan o'tilgan zahoti sertifikat yaratiladi: noyob №, ro'yxat raqami va QR kod beriladi.
- Yaratilgan PDF shablonga mos keladi: gerb, ikki chiziqli ramka, barcha maydonlar to'ldirilgan, QR kod o'ng pastki katakda.
- Imzoli nusxa yuklangach, QR sahifasi va o'quvchining test havolasi undan yuklab olishni taklif qiladi.
- Imzolanmagan PDF tashqariga (loginsiz) hech qachon chiqmaydi.
- Operator faqat o'z tashkiloti sertifikatlarini ko'radi va boshqaradi.

### Doiradan tashqari (YAGNI)

- Elektron raqamli imzo (ERI) — imzo qog'ozda qo'yiladi.
- Imzoli skanlarni ommaviy yuklash — yuklash bittadan.
- Sertifikatni email yoki SMS orqali yuborish.
- Shablonni UI orqali tahrirlash va bir nechta shablon.
- Baho keyinchalik "qoniqarsiz" ga o'zgartirilsa, sertifikat avtomatik o'chmaydi — admin uni qo'lda o'chiradi.
- Davriy imtihon o'chirilganda (DB cascade) sertifikat fayllari diskda qolishi mumkin. Bu holat hozircha qabul qilinadi.

## 2. Qabul qilingan qarorlar

| # | Savol | Qaror |
|---|---|---|
| 1 | Shablon maydonlari qayerdan olinadi? | Sozlamalar sahifasi (muassasa, 3 imzolovchi, shahar) + imtihon turi (dastur nomi, soat) + davriy imtihon (kurs sanalari) |
| 2 | Qachon yaratiladi? | Onlayn testdan o'tilganda avtomatik + imtihon sahifasidagi "Sertifikat yaratish" tugmasi (qo'lda kiritilgan natijalar uchun ham) |
| 3 | Kim boshqaradi? | Admin — hammasini; operator — faqat o'z tashkilotini |
| 4 | Raqamlash | № = `YYYY-NNNN` (har yili qaytadan boshlanadi); ro'yxat raqami = `NNNNNN` (umumiy, qaytadan boshlanmaydi) |
| 5 | PDF texnologiyasi | Blade HTML + `barryvdh/laravel-dompdf`; QR — `chillerlan/php-qrcode` |
| 6 | QR qayerga olib boradi? | Ochiq tekshirish sahifasi `/sertifikat/{code}` + "Yuklab olish" tugmasi |

"O'tgan" degani — `exam_results.grade != 'unsatisfactory'` (ya'ni `is_passed = true`).

## 3. Ma'lumotlar modeli

### 3.1 Mavjud jadvallarga qo'shimchalar

**`exam_types`:**
- `program_name` — `string(255)`, nullable. Bo'sh bo'lsa sertifikatda `exam_types.name` ishlatiladi.
- `training_hours` — `unsignedSmallInteger`, nullable.

**`periodic_exams`:**
- `course_start_date` — `date`, nullable.
- `course_end_date` — `date`, nullable. Validatsiya: `after_or_equal:course_start_date`.

Ikkala model `$fillable` va controller validatsiyasiga (store/update) qo'shiladi.

### 3.2 `certificate_settings` (yangi, bitta qator)

| Ustun | Tur | Shablondagi joyi |
|---|---|---|
| `id` | PK | — |
| `institution_name` | string, nullable | "(malaka oshirish va qayta tayyorlash ta'lim muassasasining nomi)" |
| `commission_chair_name` | string, nullable | "Imtixon komissiyasi raisi" |
| `director_name` | string, nullable | "Ta'lim muassasi rahbari" |
| `inspector_name` | string, nullable | "Mehnatni muhofaza qilish bo'yicha (bosh) davlat texnik inspektori" |
| `city` | string, nullable | "Shaxar (tuman)" |
| `timestamps` | | |

Model: `CertificateSetting::current()` — birinchi qatorni qaytaradi, bo'lmasa bo'sh qator yaratadi (`firstOrCreate(['id' => 1])`).

### 3.3 `certificates` (yangi)

| Ustun | Tur | Izoh |
|---|---|---|
| `id` | PK | |
| `exam_result_id` | FK → `exam_results`, **unique**, `cascadeOnDelete` | bitta natija — bitta sertifikat |
| `employee_id` | FK → `employees`, `cascadeOnDelete` | filtrlash uchun |
| `periodic_exam_id` | FK → `periodic_exams`, `cascadeOnDelete` | filtrlash va operator scope uchun |
| `year` | `unsignedSmallInteger` | berilgan sana yili |
| `sequence` | `unsignedInteger` | yil ichidagi tartib; `unique(year, sequence)` |
| `number` | string(20), **unique** | `sprintf('%d-%04d', year, sequence)`, masalan `2026-0001` |
| `registration_number` | `unsignedInteger`, **unique** | umumiy tartib; ko'rsatishda `str_pad(.., 6, '0', STR_PAD_LEFT)` |
| `public_code` | string(32), **unique** | `Str::random(32)`; QR havola shu kod bilan |
| `data` | JSON | sertifikatdagi ma'lumotlar "surati" (3.4) |
| `generated_path` | string, nullable | `local` disk ichidagi imzolanmagan PDF yo'li |
| `signed_path` | string, nullable | imzoli skan yo'li |
| `signed_at` | timestamp, nullable | |
| `signed_by` | FK → `users`, nullable, `nullOnDelete` | |
| `timestamps` | | |

**Holat** alohida ustunda saqlanmaydi, `signed_path` dan hisoblanadi:
`status = signed_path ? 'signed' : 'pending_signature'`. Bu qiymat model accessori sifatida (`$appends`) beriladi.

**Fayllar** `local` diskda saqlanadi (`storage/app/private`):
- `certificates/generated/{number}.pdf`
- `certificates/signed/{number}-{random8}.{ext}`

Bu fayllarga URL orqali to'g'ridan-to'g'ri kirib bo'lmaydi, ular faqat controller orqali beriladi. Docker `app_storage` volume ularni saqlab qoladi.

`Certificate` modelining `deleting` hodisasi ikkala faylni ham o'chiradi. `ExamResult` modelining `deleting` hodisasi esa o'z sertifikatini model orqali o'chiradi, shunda fayllar ham o'chadi. Bu `ExamResultController::destroy` uchun ishlaydi.

### 3.4 `data` JSON tarkibi

```json
{
  "full_name": "Aliyev Vali G'aniyevich",
  "institution_name": "\"JAHON MEHNAT MUHOFAZASI\" MCHJ",
  "program_name": "Mehnatni muhofaza qilish",
  "training_hours": 72,
  "course_start_date": "2026-11-01",
  "course_end_date": "2026-11-07",
  "commission_chair_name": "J. Xasanov",
  "director_name": "A. Bazarova",
  "inspector_name": "M. Yurev",
  "city": "Urganch sh.",
  "issue_date": "2026-11-07"
}
```

- `issue_date` — natija yaratilgan kun: `exam_results.created_at`, sana qismi.
- Bo'sh (null) maydonlar PDF da shablondagidek `____` chiziq bo'lib chiqadi.

## 4. Backend

### 4.1 Paketlar

- `barryvdh/laravel-dompdf` — PDF. Unga kerakli `ext-dom`, `ext-mbstring` va `ext-gd` Docker image'da bor.
- `chillerlan/php-qrcode` (v5) — QR. U GD orqali PNG yaratadi va PNG base64 data URI sifatida joylanadi. dompdf'da SVG'ga qaraganda ishonchliroq.

Docker image o'zgarmaydi: `composer.json` va `composer.lock` yangilanadi, qayta build qilinganda paketlar o'rnatiladi.

### 4.2 `App\Services\CertificateService`

| Metod | Vazifasi |
|---|---|
| `issue(ExamResult $result): Certificate` | Natija o'tmagan bo'lsa `DomainException`. Sertifikat yo'q bo'lsa — raqam ajratadi, yaratadi, PDF chiqaradi. Bor va imzolanmagan bo'lsa — `data` ni yangilaydi va PDF ni qayta chiqaradi (raqam, ro'yxat raqami va `public_code` o'zgarmaydi). Bor va imzolangan bo'lsa — `DomainException("Imzolangan sertifikatni qayta yaratib bo'lmaydi")`. |
| `buildData(ExamResult $result): array` | 3.4 dagi JSON ni sozlamalar, imtihon turi, davriy imtihon va xodimdan yig'adi. |
| `render(iterable $certificates): string` | Bitta yoki bir nechta sertifikatni bitta PDF ga chizadi (har biri alohida sahifa). Natija — PDF baytlari. |
| `storeSigned(Certificate $c, UploadedFile $file, User $by): void` | Eski imzoli faylni o'chiradi, yangisini saqlaydi, `signed_at` va `signed_by` ni yozadi. |

**Raqam ajratish** `DB::transaction` ichida bajariladi:

```php
$year = $issueDate->year;
$sequence = Certificate::where('year', $year)->lockForUpdate()->max('sequence') + 1;
$registration = Certificate::lockForUpdate()->max('registration_number') + 1;
```

`unique` cheklovlari kafolat bo'lib xizmat qiladi. Agar `UniqueConstraintViolationException` chiqsa, ajratish 3 martagacha qayta urinadi.

### 4.3 PDF shabloni

Fayl: `resources/views/certificates/certificate.blade.php`.

**Sahifa:**
- A4 vertikal, `@page { margin: 0 }`.
- Ichki ramka `position: fixed` bilan chiziladi: tashqi 3px va ichki 1px chiziq, rangi to'q yashil `#1F5E63`. Har bir sahifada takrorlanadi.
- Shrift DejaVu Serif (dompdf bilan keladi). `o‘`, `g‘` va `ʼ` harflari to'g'ri chiqadi.

**Tartib (yuqoridan pastga, shablon PDF ga mos):**
1. Gerb, markazda, ~110px. Fayl: `resources/certificates/emblem.png` (docx'dan olingan va 400px gacha kichraytirilgan). Base64 data URI sifatida joylanadi.
2. "Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida" — qalin.
3. "SERTIFIKAT" — katta, `#1F5E63` rangda.
4. "№ {number}".
5. F.I.O. qalin chiziq ustida, katta harf bilan. Ostida kichik izoh "(familiyasi, ismi, otasining ismi)".
6. "{yil} yil «{kun}» {oy nomi} dan {yil} yil «{kun}» {oy nomi} gacha". Oy nomlari o'zbekcha: yanvar … dekabr.
7. Muassasa nomi tagiga chizilgan holda. Ostida izoh "(malaka oshirish va qayta tayyorlash ta'lim muassasasining nomi)".
8. Dastur nomi qalin, tagiga chizilgan. Ostida izoh "(xodimlar malakasini oshirish dasturining nomi)".
9. "dastur bo‘yicha {soat} soat hajmida malaka oshirish kursida o‘qidi:".
10. "Muhr o‘rni :" — bo'sh joy.
11. Imzo qatorlari. 3 qator × 3 ustun jadvali: lavozim | imzo chizig'i va "(imzo)" | F.I.O. tagiga chizilgan va "(F.I.O)".
12. "Shaxar (tuman) {city}" chapda, "Sana {dd.mm.yyyy}" o'ngda.
13. "Ro‘yxatdan o‘tish raqami {registration_number}" chapda. O'ngda ramkali katak ichida QR kod (~120px).

QR qiymati — `rtrim(config('app.url'), '/') . '/sertifikat/' . $public_code`.

### 4.4 API — autentifikatsiya talab qiladigan endpoint'lar (`auth:sanctum`)

| Metod va yo'l | Vazifasi | Kirish huquqi |
|---|---|---|
| `GET /api/certificates` | Sahifalangan ro'yxat. Filtrlar: `organization_id`, `periodic_exam_id`, `status` (`signed` / `pending_signature`), `search` (F.I.O. yoki №). `employee.organization` va `periodicExam` bilan birga qaytadi. | admin — hammasi; operator — o'z tashkiloti |
| `POST /api/certificates/generate` `{periodic_exam_id}` | Imtihondagi barcha o'tgan natijalar uchun `issue()` chaqiradi, imzolanganlarini o'tkazib yuboradi. Javob: `{created, regenerated, skipped_signed, warnings[]}`. `warnings` — masalan "Kurs sanalari kiritilmagan", "Soat hajmi kiritilmagan", "Sertifikat sozlamalari to'ldirilmagan". | imtihon tashkiloti bo'yicha scope |
| `POST /api/certificates/{id}/regenerate` | Bitta sertifikatni qayta yaratadi. Imzolangan bo'lsa 422. | scope |
| `GET /api/certificates/print?periodic_exam_id=X` | Imtihondagi barcha imzolanmagan sertifikatlar bitta PDF da. Ular bo'lmasa 404. | scope |
| `GET /api/certificates/{id}/download?type=generated\|signed` | Fayl stream qilinadi. Fayl nomi: `sertifikat-{number}.pdf` yoki `sertifikat-{number}-imzoli.{ext}`. | scope |
| `POST /api/certificates/{id}/signed` (multipart `file`) | Imzoli nusxani yuklash. `required\|file\|mimes:pdf,jpg,jpeg,png\|max:10240`. | scope |
| `DELETE /api/certificates/{id}` | Sertifikat va fayllarini o'chiradi. | scope |
| `GET /api/certificate-settings` | Sozlamalarni o'qish. | faqat admin |
| `PUT /api/certificate-settings` | Sozlamalarni yangilash, barcha maydonlar `nullable\|string\|max:255`. | faqat admin |

Scope qoidasi mavjud kod bilan bir xil: `$user->role === 'operator' && $user->organization_id && $exam->organization_id !== $user->organization_id` bo'lsa `abort(403)`. Ro'yxatda esa `whereHas('periodicExam', fn ($q) => $q->where('organization_id', $user->organization_id))` ishlatiladi.

### 4.5 API — ochiq endpoint'lar (loginsiz)

| Metod va yo'l | Javob |
|---|---|
| `GET /api/public/certificates/{code}` | `{number, registration_number, full_name, program_name, institution_name, training_hours, course_start_date, course_end_date, issue_date, status}`. Kod topilmasa 404. |
| `GET /api/public/certificates/{code}/download` | Imzoli faylni `inline` sifatida stream qiladi (telefonda brauzerning o'zida ochiladi). Imzolanmagan bo'lsa 404 "Sertifikat hali imzolanmagan". |

Bundan tashqari:
- `GET /api/public/exam-sessions/{token}` javobiga `certificate: {number, status, public_code} | null` qo'shiladi.
- `POST /api/public/exam-sessions/{token}/submit` javobiga ham xuddi shu `certificate` maydoni qo'shiladi.

### 4.6 Onlayn test bilan bog'lanish

`Public\ExamSessionController::submit` da `ExamResult::create` dan keyin, agar `is_passed` rost bo'lsa:

```php
try {
    $certificate = $certificates->issue($examResult);
} catch (\Throwable $e) {
    report($e);
    $certificate = null;
}
```

Sertifikat yaratishda xato chiqsa ham test natijasi saqlanadi va javob muvaffaqiyatli qaytadi.

## 5. Frontend

### 5.1 Yangi sahifalar

**`src/views/certificates/CertificatesView.vue`** — route `/certificates`, `requiresAuth`.
- Filtrlar: tashkilot (faqat admin), imtihon, holat, qidiruv. `?periodic_exam_id=` query param'ini qabul qiladi.
- Jadval ustunlari: №, F.I.O., tashkilot, imtihon, berilgan sana, holat chipi ("Imzo kutilmoqda" — warning, "Imzolangan" — success).
- Har bir qatordagi amallar:
  - PDF yuklab olish;
  - imzoli nusxani yuklash (yashirin `<input type="file" accept=".pdf,.jpg,.jpeg,.png">`);
  - imzoli nusxani ko'rish (faqat imzolangan bo'lsa);
  - qayta yaratish (faqat imzolanmagan bo'lsa);
  - o'chirish (`ConfirmDialog` orqali).

**`src/views/settings/CertificateSettingsView.vue`** — route `/settings/certificate`, faqat admin. 5 ta maydonli forma va "Saqlash" tugmasi.

**`src/views/public/CertificateVerifyView.vue`** — route `/sertifikat/:code`, loginsiz (`TestSessionView` dagi kabi alohida axios instansiyasi).
- Topilsa: "✓ Sertifikat haqiqiy" kartasi, F.I.O., №, ro'yxat raqami, dastur, muassasa, kurs muddati, berilgan sana.
- Imzolangan bo'lsa: katta "Imzolangan sertifikatni yuklab olish" tugmasi, u `/api/public/certificates/{code}/download` ga olib boradi.
- Imzolanmagan bo'lsa: "Mas'ul shaxslar imzosi kutilmoqda" yozuvi.
- 404 bo'lsa: "Sertifikat topilmadi".

### 5.2 O'zgaradigan fayllar

- **`router/index.js`** — 3 ta yangi route qo'shiladi.
- **`components/layout/AppSidebar.vue`** — "Sertifikatlar" (`mdi-certificate`) Imtihonlar guruhidan keyin; "Sertifikat sozlamalari" Sozlamalar guruhida (admin).
- **`views/exams/ExamDetailView.vue`:**
  - "Sertifikat yaratish" tugmasi — javobdagi `warnings` snackbar yoki alert sifatida ko'rsatiladi;
  - "Imzolash uchun chop etish (PDF)" tugmasi;
  - natijalar jadvalida "Sertifikat" ustuni: № va holat chipi, bosilganda `/certificates?periodic_exam_id=X` ochiladi.
- **`views/exams/ExamTypesView.vue`** (forma) — "Dastur nomi" va "Soat hajmi" maydonlari.
- **`views/exams/PeriodicExamsView.vue`** (forma) — "Kurs boshlanishi" va "Kurs tugashi" sanalari.
- **`views/public/TestSessionView.vue`:**
  - natija ekranida, `certificate` bo'lsa: "Sertifikatingiz yaratildi (№ …). Mas'ul shaxslar imzolagandan so'ng shu havolada yuklab olishingiz mumkin";
  - `completed` holatda havola qayta ochilsa va imzolangan bo'lsa: "Sertifikatni yuklab olish" tugmasi.

**Autentifikatsiyali yuklab olish.** Token `Authorization` header'da yuboriladi, shuning uchun oddiy havola ishlamaydi. Fayllar `api.get(url, { responseType: 'blob' })` orqali olinadi, keyin `URL.createObjectURL` va vaqtinchalik `<a download>` bilan saqlanadi. Imzoli nusxani ko'rish yangi oynada ochiladi.

## 6. Xatolarni qayta ishlash

| Holat | Javob |
|---|---|
| Avtomatik yaratishda istisno | `report()` qilinadi, test natijasi saqlanadi, `certificate: null` |
| O'tmagan natijaga `issue()` | `generate` da shunchaki o'tkazib yuboriladi; `regenerate` da 422 |
| Imzolangan sertifikatni qayta yaratish | 422 "Imzolangan sertifikatni qayta yaratib bo'lmaydi" |
| Yuklangan fayl turi yoki hajmi noto'g'ri | 422, Laravel validatsiya xabari |
| Operator boshqa tashkilot sertifikatiga murojaat qilsa | 403 |
| Ochiq: kod topilmadi | 404 "Sertifikat topilmadi" |
| Ochiq: imzolanmagan faylni yuklab olish | 404 "Sertifikat hali imzolanmagan" |
| `print`: imzolanmagan sertifikat yo'q | 404 "Chop etish uchun imzolanmagan sertifikat yo'q" |

## 7. Testlar

**Test muhiti.** Mahalliy PHP'da `pdo_sqlite` yo'q, shuning uchun `phpunit.xml` dagi `DB_CONNECTION=sqlite` / `:memory:` o'rniga `DB_CONNECTION=mysql`, `DB_DATABASE=muhofaza_test` ishlatiladi. Test bazasi bir marta yaratiladi: `CREATE DATABASE muhofaza_test`. Testlar `RefreshDatabase` bilan ishlaydi va ishchi `muhofaza` bazasiga tegmaydi. Kerakli modellar uchun factory'lar qo'shiladi: Organization, Department, Position, Employee, ExamType, PeriodicExam, ExamResult, Question.

**Feature testlar** — `tests/Feature/Certificates/`:
1. **Raqamlash:** 2026 da ikkita, 2027 da bitta sertifikat → `2026-0001`, `2026-0002`, `2027-0001`; ro'yxat raqamlari 1, 2, 3.
2. **Avtomatik yaratish:** onlayn testdan o'tilganda `submit` → sertifikat yaratiladi, javobda `certificate.number` bor; yiqilganda → sertifikat yo'q.
3. **Generate:** yangisini yaratadi, imzolanmaganini qayta yaratadi (raqam o'zgarmaydi, `data` yangilanadi), imzolanganiga tegmaydi; javobdagi hisoblar to'g'ri.
4. **Imzoli nusxani yuklash:** PDF va PNG qabul qilinadi, `.docx` va 11 MB fayl 422; qayta yuklashda eski fayl o'chadi.
5. **Ruxsatlar:** operator boshqa tashkilot sertifikatiga `download`, `signed`, `delete` va `generate` qilganda → 403; ro'yxatda faqat o'z tashkiloti chiqadi; `certificate-settings` → operator uchun 403.
6. **Ochiq endpoint'lar:** imzolanmagan sertifikatda `show` → 200 va `status=pending_signature`, `download` → 404; imzolanganda `download` → 200; noma'lum kod → 404.
7. **PDF smoke:** `render()` natijasi `%PDF` bilan boshlanadi; `print` endpoint'i `application/pdf` qaytaradi.

`Storage::fake('local')` ishlatiladi.

**Qo'lda tekshirish.** Yaratilgan PDF ochilib, shablon bilan ko'z bilan solishtiriladi. QR telefon bilan skanerlanadi (mahalliy tarmoqda APP_URL'ni IP'ga sozlab).

## 8. Deploy eslatmalari

- `docker compose up -d --build` — yangi composer paketlari va migratsiyalar avtomatik qo'llanadi (`docker-entrypoint.sh` → `migrate --force`).
- `.env.docker` dagi `APP_URL=https://mehnatmuhofazasi.uz` QR havolasi uchun to'g'ri bo'lishi shart.
- Birinchi deploydan keyin admin "Sertifikat sozlamalari"ni, imtihon turlarida "Dastur nomi" va "Soat hajmi"ni, davriy imtihonlarda kurs sanalarini to'ldiradi.

# Sertifikat turlari, qo'lda raqam va test variantlari — dizayn hujjati

**Sana:** 2026-11-09
**Holat:** tasdiqlangan dizayn, reja kutilmoqda
**Bog'liq:** `2026-11-05-sertifikatlar-design.md` (mavjud sertifikat tizimi — ushbu hujjat uni o'zgartiradi)

## 1. Maqsad

1. Sertifikat **4 turda** bo'ladi: 36 soatlik malaka oshirish, 36 soatlik o'qish, 72 soatlik malaka oshirish, 288 soatlik qayta tayyorlov. Tur sertifikatdagi soatni, sarlavhani va kurs matnini belgilaydi.
2. Sertifikat **raqami (№) qo'lda kiritiladi**. Imzo uchun PDF faqat raqam kiritilgandan keyin yuklab olinadi.
3. Test sahifasida variantlar orasi kengroq, oldida "A.", "B." harflari yo'q.

### Muvaffaqiyat mezonlari

- Admin davriy imtihonda sertifikat turini tanlaydi; shu imtihonning sertifikatlari tur matni va soati bilan chiqadi.
- Admin turlar matnini "Sertifikat sozlamalari"da o'zgartira oladi, deploy kerak emas.
- Raqamsiz sertifikatning PDF'ini yuklab bo'lmaydi; raqam kiritilgach PDF darhol shu raqam bilan yuklanadi.
- Imtihon bo'yicha ommaviy chop etishda raqamlar bitta oynada, "ketma-ket to'ldirish" yordamida kiritiladi.
- Raqam takrorlanmaydi; imzolangan sertifikat raqami o'zgarmaydi.
- Test sahifasida variantlar harfsiz, keng, telefonda bosish oson.

### Qabul qilingan qarorlar

| # | Savol | Qaror |
|---|---|---|
| 1 | Tur qayerda tanlanadi? | Davriy imtihonda (kurs guruhi) |
| 2 | Tur matnlari | 4 tur boshlang'ich matnlar bilan kiritiladi; admin "Sertifikat sozlamalari"da tahrirlaydi |
| 3 | Ommaviy chop etishda raqam | Ro'yxat + "birinchi raqamdan ketma-ket to'ldirish", har katak qo'lda tuzatiladi |
| 4 | Yondashuv | Alohida `certificate_types` jadvali; `periodic_exams.certificate_type_id` |
| 5 | Qaysi raqam qo'lda? | Faqat "№" (`number`). "Ro'yxatdan o'tish raqami" avtomatik qoladi |
| 6 | Yozuv | Lotin (tizim va sertifikat lotinda) |

### Doiradan tashqari (YAGNI)

- Har tur uchun alohida PDF shablon (joylashuv bir xil, faqat matnlar farq qiladi).
- Raqam formatini majburlash (ixtiyoriy matn, 1–50 belgi).
- Raqamni avtomatik berish (eski `YYYY-NNNN` mexanizmi yangi sertifikatlarda ishlatilmaydi).

## 2. Ma'lumotlar modeli

### 2.1 Yangi `certificate_types`

| Ustun | Tur |
|---|---|
| `id` | PK |
| `name` | string(255), unique |
| `hours` | unsignedSmallInteger |
| `title_text` | string(255) — sertifikat sarlavhasi |
| `course_text` | string(255) — "dastur bo'yicha {hours} soat hajmida **{course_text}** o'qidi:" |
| `timestamps` | |

Boshlang'ich qatorlar:

| name | hours | title_text | course_text |
|---|---|---|---|
| 36 soatlik malaka oshirish | 36 | Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida | malaka oshirish kursida |
| 36 soatlik o'qish | 36 | Mehnatni muhofaza qilish sohasida o'qiganlik haqida | o'quv kursida |
| 72 soatlik malaka oshirish | 72 | Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida | malaka oshirish kursida |
| 288 soatlik qayta tayyorlov | 288 | Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida | qayta tayyorlov kursida |

### 2.2 Boshqa jadvallar

- `periodic_exams.certificate_type_id` — FK → `certificate_types`, nullable, `restrictOnDelete`.
- `exam_types.training_hours` — **olib tashlanadi**.
- `certificates.number` — nullable (unique saqlanadi), `string(50)`.
- `certificates.year`, `certificates.sequence` — nullable (faqat eski sertifikatlarda to'ldirilgan; `unique(year, sequence)` saqlanadi — MySQL NULL'larni takror hisoblamaydi).

### 2.3 Migratsiya (`2026_11_09_000001_create_certificate_types.php`)

Har qadam qayta ishga tushirishga chidamli (`hasTable`/`hasColumn`/FK nomi tekshiruvi) — oldingi migratsiyadagi kabi:

1. `certificate_types` jadvali + 4 boshlang'ich qator (nomi bo'yicha mavjud bo'lsa qo'shilmaydi).
2. `periodic_exams.certificate_type_id` qo'shiladi.
3. Ma'lumot (tranzaksiyada): `exam_types.training_hours` 36 → "36 soatlik malaka oshirish", 72 → "72 soatlik malaka oshirish", 288 → "288 soatlik qayta tayyorlov" turi shu imtihon turining **barcha** davriy imtihonlariga yoziladi (faqat `certificate_type_id` bo'sh bo'lganlariga). Boshqa qiymatlar → bo'sh.
4. `exam_types.training_hours` olib tashlanadi.
5. `certificates.number` (string(50)), `year`, `sequence` nullable qilinadi.

`down()`: `training_hours` ni qaytaradi (davriy imtihon turi soati bilan, imtihon turi bo'yicha birinchi uchragan qiymat), `certificate_type_id` va jadvalni olib tashlaydi. `number` NOT NULL ga qaytarilmaydi (raqamsiz sertifikatlar bo'lishi mumkin — hujjatlashtirilgan).

### 2.4 Modellar

- `CertificateType`: `fillable [name, hours, title_text, course_text]`, `periodicExams()` hasMany.
- `PeriodicExam`: fillable + `certificate_type_id`; `certificateType()` belongsTo.
- `ExamType`: fillable'dan `training_hours` olib tashlanadi.

### 2.5 Sertifikat `data` surati

Mavjud kalitlar + yangilari:
- `certificate_type_name`, `title_text`, `course_text` — davriy imtihon turidan (tur yo'q bo'lsa `null`).
- `training_hours` = tur `hours` (tur yo'q bo'lsa `null`). `exam_types.training_hours` endi ishlatilmaydi.

Shablon `title_text`/`course_text` bo'sh bo'lsa (eski sertifikatlar, tur tanlanmagan) — standart matnlar: "Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida" va "malaka oshirish kursida".

## 3. Backend oqimi

### 3.1 `CertificateService`

| Metod | Xatti-harakat |
|---|---|
| `issue(ExamResult)` | Yangi: `registration_number` va `public_code` beriladi, `number = null`, **PDF chizilmaydi**. Mavjud imzolanmagan: `data` yangilanadi; `number` bo'lsa va (ma'lumot o'zgargan yoki fayl yo'q) — PDF qayta chiziladi. Imzolangan → `CertificateException` (mavjud). |
| `setNumber(Certificate, string $number): Certificate` | Imzolangan → `CertificateException("Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi")`. Raqamni saqlaydi va PDF ni `certificates/generated/{id}.pdf` ga chizadi (eski boshqa nomli fayl bo'lsa o'chiriladi). |
| `setNumbers(array $items)` | `[{certificate, number}]` — bitta tranzaksiyada: avval shu sertifikatlarning raqamlari `null` qilinadi (o'zaro almashtirish unique'ga urilmasligi uchun), keyin yangilari yoziladi; tranzaksiyadan keyin PDF'lar chiziladi. |
| `missingFieldWarnings(PeriodicExam)` | "Soat hajmi kiritilmagan" o'rniga `Sertifikat turi tanlanmagan` (davriy imtihonda tur yo'q). |

Raqam ajratish (`createWithNumbers`) endi faqat `registration_number` ni ajratadi (lock + retry saqlanadi); `year`/`sequence`/`number` yozilmaydi.

### 3.2 API

| Endpoint | Qoida | Kim |
|---|---|---|
| `GET /certificate-types` | nom bo'yicha | hamma |
| `POST /certificate-types`, `PUT /certificate-types/{id}` | `name` required\|max:255\|unique; `hours` required\|integer\|1–2000; `title_text`, `course_text` required\|string\|max:255 (o'zbekcha xabarlar) | admin |
| `DELETE /certificate-types/{id}` | ishlatilsa 422 `Tur davriy imtihonlarda ishlatilmoqda` | admin |
| `PUT /certificates/{id}/number` | `number` required\|string\|max:50 (trim), boshqa sertifikatda band bo'lsa 422 `Bu raqam boshqa sertifikatga berilgan`; imzolangan 422; javob — sertifikat | admin; operator — o'z tashkiloti |
| `PUT /certificates/numbers` | `items` required\|array\|min:1; `items.*.id` required\|distinct\|exists; `items.*.number` required\|string\|max:50, ro'yxat ichida distinct (trim'dan keyin); ro'yxatdan tashqari band → 422 `items.{i}.number`: `Bu raqam boshqa sertifikatga berilgan`; imzolangan → 422; boshqa tashkilot → 403; hech biri qisman saqlanmaydi | admin; operator — o'z tashkiloti |
| `GET /certificates/{id}/download` (generated) | `number` bo'sh → 422 `Avval sertifikat raqamini kiriting`; fayl nomi `sertifikat-{xavfsiz raqam}.pdf` (`[^A-Za-z0-9._-]` → `-`) | mavjud |
| `GET /certificates/print` | faqat `number` bor va imzolanmaganlar; yo'q bo'lsa 404 `Chop etish uchun raqamlangan, imzolanmagan sertifikat yo'q` | mavjud |
| `GET /certificates` | `per_page` maksimumi 500 ga oshiriladi (ommaviy oyna uchun) | mavjud |
| Davriy imtihon store/update | `certificate_type_id` nullable\|exists | mavjud |
| Imtihon turi store/update | `training_hours` qoidasi olib tashlanadi | mavjud |

Onlayn test javobidagi `certificate.number` endi `null` bo'lishi mumkin. Ochiq tekshirish (`/public/certificates/{code}`) `number` `null` qaytarishi mumkin.

## 4. Frontend

- **CertificatesView**: № ustunida raqam yo'q bo'lsa kulrang chip `Raqam yo'q`. 📄 tugmasi — imzolanmagan bo'lsa "Sertifikat raqami" dialogi (maydon oldindan to'ldirilgan; "Saqlash va yuklab olish" → `PUT number` → yuklab olish; 422 xabari maydon ostida). Imzolangan — to'g'ridan-to'g'ri yuklab olish (raqam bor).
- **ExamDetailView**: "Imzolash uchun chop etish (PDF)" → dialog: shu imtihonning barcha imzolanmagan sertifikatlari (`GET /certificates?periodic_exam_id=X&status=pending_signature&per_page=500`), har qatorda F.I.O. va raqam katagi (bor bo'lsa oldindan). Tepada "Birinchi raqam" + "Ketma-ket to'ldirish": faqat **bo'sh** kataklarni ro'yxat tartibida to'ldiradi; raqam `^(.*?)(\d+)(\D*)$` bo'yicha prefiks/son/suffiksga bo'linadi, son oshiriladi, uzunligi (boshidagi nollar) saqlanadi; sonsiz birinchi raqam → xato `Birinchi raqamda son bo'lishi kerak`. Bo'sh katak qolsa "Saqlash va PDF yuklab olish" o'chiq. Saqlash: `PUT /certificates/numbers` → `print` PDF yuklanadi; 422 xatolari tegishli qator ostida. Ro'yxat bo'sh bo'lsa: `Imzolanmagan sertifikat yo'q`. Natijalar jadvalidagi sertifikat chipi raqamsiz bo'lsa `Raqam yo'q · Imzo kutilmoqda`.
- **CertificateSettingsView**: "Sertifikat turlari" bo'limi — jadval (nom, soat, sarlavha, matn qatori), qo'shish/tahrirlash dialogi, o'chirish (422 xabari snackbar'da).
- **PeriodicExamsView**: "Sertifikat turi" `v-select` (`GET /certificate-types`), clearable, kurs sanalari yonida.
- **ExamTypesView**: "Soat hajmi" maydoni olib tashlanadi.
- **TestSessionView**: variantlar `opt.text` bilan, harfsiz; har variant alohida ramkali qator (min-height ~52px, oralig'i ~10px, butun qator bosiladi, tanlangani `primary` ramka va och fon). Natija ekrani xabari: `Sertifikatingiz yaratildi. Mas'ul shaxslar raqam berib imzolagandan so'ng shu havolada yuklab olishingiz mumkin.`; tugallangan holatda raqam yo'q bo'lsa `Sertifikat tayyorlanmoqda — imzo kutilmoqda`.
- **CertificateVerifyView**: `number` `null` bo'lsa `—`.

## 5. Xatolar

| Holat | Javob |
|---|---|
| Raqamsiz PDF yuklab olish | 422 `Avval sertifikat raqamini kiriting` |
| Band raqam | 422 `Bu raqam boshqa sertifikatga berilgan` |
| Imzolanganning raqamini o'zgartirish | 422 `Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi` |
| Ommaviy ro'yxatda takror raqam | 422 `items.{i}.number` (distinct) |
| Ishlatilayotgan turni o'chirish | 422 `Tur davriy imtihonlarda ishlatilmoqda` |
| Chop etish uchun raqamli sertifikat yo'q | 404 `Chop etish uchun raqamlangan, imzolanmagan sertifikat yo'q` |
| Operator boshqa tashkilot | 403 |

## 6. Testlar

PHPUnit (MySQL `muhofaza_test`):
1. Migratsiya (`DatabaseMigrations`): 4 tur; `training_hours=72` turining davriy imtihonlari → "72 soatlik malaka oshirish"; `50` → null; `training_hours` ustuni yo'q; qayta ishga tushirishga chidamli.
2. `issue`: `number` null, `generated_path` null, `registration_number` bor; onlayn test javobi `certificate.number` null.
3. `setNumber`: PDF yaratiladi (`%PDF`), fayl `certificates/generated/{id}.pdf`; band → 422; imzolangan → 422; trim.
4. `setNumbers`: hammasi saqlanadi; o'zaro almashtirish (A↔B) ishlaydi; ichki takror → 422, hech narsa o'zgarmaydi; tashqi band → 422; operator boshqa tashkilot → 403.
5. Yuklab olish raqamsiz → 422; `print` faqat raqamli imzolanmaganlar; yo'q bo'lsa 404.
6. PDF matni: 288 soatlik tur bilan render — `pdftotext` (yoki PDF matn oqimi) da "qayta tayyorlovdan o'tganlik haqida" va "288 soat hajmida qayta tayyorlov kursida".
7. Turlar API: admin CRUD; operator GET 200, yozish 403; ishlatilayotganini o'chirish → 422.
8. Ogohlantirish: tur tanlanmagan → `Sertifikat turi tanlanmagan`.
9. Regressiya: mavjud sertifikat testlari yangi oqimga moslanadi (raqam `setNumber` orqali).

Frontend: `npm run build`; Playwright — raqam dialogi, ommaviy chop etish (ketma-ket to'ldirish, prefiksli raqam `MM-0451` → `MM-0452`), turlar jadvali, davriy imtihonda tur tanlash, test sahifasidagi variantlar (harfsiz, keng, telefonda 375px).

## 7. Deploy

- Avval `mysqldump` zaxira.
- Migratsiya avtomatik; mavjud sertifikatlar raqami saqlanadi.
- Deploydan keyin admin turlar matnini tekshiradi va davriy imtihonlarda turni tanlaydi (`training_hours` 36/72/288 bo'lmagan imtihonlar uchun).

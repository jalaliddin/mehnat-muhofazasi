# Savollar bazalari — dizayn hujjati

**Sana:** 2026-11-05
**Holat:** tasdiqlangan dizayn, reja kutilmoqda

## 1. Maqsad

Savollar alohida, qayta ishlatiladigan **bazalarda** saqlanadi (masalan, "Elektr xavfsizligi" — 200 savol). Har bir imtihon turi uchun **test tarkibi** belgilanadi: qaysi bazadan nechta savol olinadi. Test boshlanganda har bir o'quvchiga har bir bazadan shuncha savol tasodifiy tanlanadi.

### Muvaffaqiyat mezonlari

- Admin baza yaratadi, unga savollarni qo'lda yoki Excel'dan qo'shadi.
- Admin imtihon turiga tarkib beradi, masalan "Elektr xavfsizligi → 10, Yong'in xavfsizligi → 10". Shundan keyin test har o'quvchiga 20 ta savol beradi: har bazadan 10 tadan, tasodifiy.
- Mavjud savollar va testlar migratsiyadan keyin avvalgidek ishlaydi.
- Bazada savol yetmasa, test havolalari yaratilmaydi va xato nima uchunligini aniq aytadi.
- Operator bazalar, savollar va tarkibni faqat ko'radi.

### Qabul qilingan qarorlar

| # | Savol | Qaror |
|---|---|---|
| 1 | Bazalar imtihon turlari bilan qanday bog'lanadi? | Bazalar mustaqil; imtihon turi tarkibi = (baza, nechta) ro'yxati; bitta baza bir nechta testda ishlatilishi mumkin |
| 2 | Kim boshqaradi? | Faqat admin; operator faqat ko'radi |
| 3 | Yondashuv | Alohida `question_banks` va `exam_type_question_bank` jadvallari; savollar bazaga tegishli |
| 4 | Tarkib qaysi darajada? | Imtihon turi darajasida — shu turdagi barcha davriy imtihonlar bir xil tarkibda |
| 5 | Savollar tartibi | Barcha bazalardan tanlangan savollar birga aralashtiriladi |
| 6 | "Nechtadan tushishi" | Har bir bazadan olinadigan son (`question_count`); jami = yig'indi |

### Doiradan tashqari (YAGNI)

- Davriy imtihon darajasida tarkibni alohida o'zgartirish.
- Testdagi jami sonni bazalardan mustaqil belgilash.
- Savolni bir nechta bazaga biriktirish.
- Qiyinlik darajasi, mavzu teglari, bir guruhga bir xil savollar berish.
- Bazalar tashkilotlarga tegishli bo'lishi — ular butun tizimga umumiy.

## 2. Ma'lumotlar modeli

### 2.1 Yangi `question_banks`

| Ustun | Tur |
|---|---|
| `id` | PK |
| `name` | string(255), unique |
| `description` | text, nullable |
| `timestamps` | |

### 2.2 Yangi `exam_type_question_bank` (test tarkibi)

| Ustun | Tur |
|---|---|
| `id` | PK |
| `exam_type_id` | FK → `exam_types`, `cascadeOnDelete` |
| `question_bank_id` | FK → `question_banks`, `restrictOnDelete` |
| `question_count` | `unsignedSmallInteger` (1–500) |
| `timestamps` | |
| | `unique(exam_type_id, question_bank_id)` |

### 2.3 `questions` o'zgarishi

`exam_type_id` olib tashlanadi, o'rniga `question_bank_id` (FK → `question_banks`, `cascadeOnDelete`) qo'shiladi. Bazani testda ishlatilayotgan bo'lsa o'chirib bo'lmagani uchun cascade faqat ishlatilmayotgan bazada ishlaydi.

### 2.4 Migratsiya (bitta migratsiya fayli, tranzaksiyada)

`up()`:
1. `question_banks` va `exam_type_question_bank` jadvallarini yaratadi.
2. `questions` jadvaliga nullable `question_bank_id` qo'shadi.
3. Savoli bor har bir imtihon turi uchun:
   - shu tur nomli baza yaratadi; nom band bo'lsa, oxiriga ` (ID {id})` qo'shadi;
   - savollarni shu bazaga o'tkazadi;
   - tarkib qatorini yozadi: `question_count` = savollar soni.
4. `question_bank_id` ni NOT NULL qiladi va FK qo'shadi; `exam_type_id` ning FK va ustunini olib tashlaydi.

`down()`:
- `questions.exam_type_id` (nullable) ni qaytaradi va har bir savolga uning bazasini ishlatgan **birinchi** imtihon turini yozadi.
- Hech bir imtihon turida ishlatilmagan bazalarning savollari o'chiriladi — bu hujjatlashtirilgan yo'qotish.
- So'ng yangi jadvallar va ustun olib tashlanadi.

### 2.5 Modellar

- `QuestionBank`: `fillable [name, description]`; `questions()` hasMany; `examTypes()` belongsToMany(`exam_type_question_bank`)->withPivot('question_count').
- `ExamType`: `questions()` olib tashlanadi; `questionBanks()` belongsToMany->withPivot('question_count')->withTimestamps(); `questionTotal(): int` = pivot `question_count` yig'indisi.
- `Question`: `exam_type_id` → `question_bank_id`; `bank()` belongsTo.

## 3. Test yaratish va savollarni tanlash

### 3.1 Havolalar (`ExamSessionController::generate`)

Tekshiruvlar (mavjud operator scope'dan keyin):
- Tarkib bo'sh → 422: `Bu imtihon turi uchun test tarkibi belgilanmagan. Imtihon turlari sahifasida "Test tarkibi"ni to'ldiring.`
- Biror bazada `questions` soni < `question_count` → 422: `«{nom}» bazasida savollar yetarli emas: {bor} ta bor, {kerak} ta kerak.` Birinchi yetmagan baza aytiladi.
- Yangi sessiyalarda `total_points` = 0 bo'ladi; haqiqiy qiymat test boshlanganda yoziladi.

### 3.2 Boshlash (`Public\ExamSessionController::start`)

1. Tarkibdagi har bir baza uchun `inRandomOrder()->limit(question_count)` bilan savollar olinadi. Bazada kamroq bo'lsa (havoladan keyin savol o'chirilgan) — borining hammasi olinadi.
2. Barcha savollar birga `shuffle()` qilinadi; variantlar ham aralashtiriladi (mavjud kod).
3. `total_points` = tushgan savollar `points` yig'indisi. U sessiyaga `status = in_progress` bilan bir tranzaksiyada yoziladi.
4. Bitta ham savol bo'lmasa → 422 `Bu test uchun savollar topilmadi` (mavjud xabar).

`submit` o'zgarmaydi: u `total_points` va `sessionQuestions` dan foydalanadi.

### 3.3 Boshqa joylar

- `Public\ExamSessionController::show` → `total_questions` = `sessionQuestions` soni, u 0 bo'lsa `examType->questionTotal()`.
- `ExamTypeController::index` → `questions_count` o'rniga `question_total` (tarkib yig'indisi). Frontend shu nomga moslanadi.
- `CertificateFixtures` va mavjud testlar yangi modelga moslanadi.

## 4. API

Barchasi `auth:sanctum` ostida. Yozish `role:admin` bilan.

| Endpoint | Javob / qoida | Kim |
|---|---|---|
| `GET /question-banks` | `[{id, name, description, questions_count, exam_types: [{id, name, question_count}]}]`, nom bo'yicha tartiblangan | hamma |
| `GET /question-banks/{id}` | bitta baza, xuddi shu maydonlar | hamma |
| `POST /question-banks` | `name` required\|string\|max:255\|unique; `description` nullable\|string | admin |
| `PUT /question-banks/{id}` | xuddi shunday (unique o'zidan tashqari) | admin |
| `DELETE /question-banks/{id}` | testda ishlatilsa 422 `Baza testlarda ishlatilmoqda: {tur nomlari}`; aks holda savollari bilan o'chiriladi | admin |
| `GET /questions?question_bank_id=X` | bazaning savollari, `id` bo'yicha | hamma |
| `POST/PUT/DELETE /questions` | validatsiyada `exam_type_id` o'rniga `question_bank_id` | admin |
| `GET /questions/template` | o'zgarmaydi | admin |
| `POST /questions/import` | `exam_type_id` o'rniga `question_bank_id` | admin |
| `GET /exam-types/{id}/question-banks` | `[{question_bank_id, name, question_count, available}]` — `available` = bazadagi savollar soni | hamma |
| `PUT /exam-types/{id}/question-banks` | `{banks: [{question_bank_id, question_count}]}` — tarkibni to'liq almashtiradi (`sync`); `banks` present\|array; `question_bank_id` distinct\|exists; `question_count` integer 1–500; bo'sh ro'yxat tarkibni tozalaydi; javob = GET bilan bir xil | admin |

## 5. Frontend

- **`views/exams/QuestionBanksView.vue`** (`/exams/question-banks`): jadval (nom, savollar soni, ishlatilayotgan testlar), admin uchun yaratish/tahrirlash/o'chirish dialoglari; satr bosilsa bazaning sahifasi ochiladi.
- **`views/exams/QuestionsView.vue`** (`/exams/question-banks/:id`): mavjud sahifa bazaga moslanadi — sarlavhada baza nomi, `question_bank_id` bilan so'rovlar; tahrirlash/import tugmalari faqat admin uchun.
- **`views/exams/ExamTypesView.vue`**: har bir qatorga "Test tarkibi" tugmasi va dialog (3-qism mockup'idagidek): bazani tanlash (allaqachon tanlanganlar ro'yxatda chiqmaydi), son, "Bazada N ta", `question_count > available` bo'lsa ⚠; "Jami: N ta savol"; saqlash `PUT`. Operator uchun dialog faqat o'qish rejimida.
- **Sidebar**: "Savollar" → "Savollar bazalari" (`/exams/question-banks`).
- **Router**: yangi yo'llar; `/exams/questions` va `/exams/types/:id/questions` → `/exams/question-banks` ga redirect. `QuestionsOverviewView.vue` o'chiriladi.

## 6. Xatolar

| Holat | Javob |
|---|---|
| Tarkib bo'sh, havola yaratish | 422 (3.1 matni) |
| Bazada savol yetmaydi, havola yaratish | 422 (3.1 matni) |
| Testda ishlatilayotgan bazani o'chirish | 422 `Baza testlarda ishlatilmoqda: …` |
| Takroriy baza yoki son 1–500 dan tashqari | 422 validatsiya |
| Operator yozishga urinsa | 403 |
| Boshlashda savol topilmasa | 422 `Bu test uchun savollar topilmadi` |

## 7. Testlar

PHPUnit, `muhofaza_test` MySQL bazasida.

1. **Migratsiya:** savollari bor imtihon turi o'z nomli bazaga ko'chadi va `question_count` = savollar soni; nom band bo'lsa ` (ID n)` qo'shiladi; `down()` savollarni turga qaytaradi.
2. **Tanlash:** tarkib 3+2 → `start` 5 savol beradi, ulardan 3 tasi 1-bazadan, 2 tasi 2-bazadan; `total_points` = tushgan ballar yig'indisi.
3. **Generate:** bo'sh tarkib → 422; yetmaydigan baza → 422, xabarda baza nomi va sonlar bor.
4. **Kamaygan baza:** havoladan keyin savol o'chirilsa, `start` borining hammasini beradi.
5. **Ruxsatlar:** operator `POST/PUT/DELETE question-banks`, `questions`, `import`, `PUT tarkib` → 403; `GET` → 200.
6. **Baza o'chirish:** ishlatilayotgan → 422; ishlatilmayotgan → 200, savollari ham o'chadi.
7. **Tarkibni saqlash:** takroriy baza → 422; `question_count` 0 → 422; to'g'ri ro'yxat saqlanadi va `available` qaytadi.
8. **Import:** Excel import tanlangan bazaga yoziladi.
9. **Regressiya:** mavjud `OnlineTestCertificateTest` va sertifikat testlari yangi fixture bilan o'tadi.

Frontend `npm run build` bilan tekshiriladi. Playwright oqimi: baza yaratish → savol qo'shish → test tarkibi → havola → test topshirish → tushgan savollar soni to'g'ri.

## 8. Deploy

- Migratsiya avtomatik bajariladi (`docker-entrypoint.sh` → `migrate --force`).
- Deploydan keyin mavjud testlar avvalgidek ishlaydi.
- Admin yangi bazalarni yaratadi va imtihon turlarida "Test tarkibi"ni sozlaydi.

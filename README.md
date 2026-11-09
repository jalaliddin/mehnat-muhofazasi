# Mehnat Muhofazasi Tizimi

Mehnat muhofazasi va sanoat xavfsizligi bo'yicha davriy imtihonlarni boshqarish tizimi — [mehnatmuhofazasi.uz](https://mehnatmuhofazasi.uz).

## Texnologiyalar

| Qism | Texnologiya |
|------|-------------|
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | Vue 3 + Vuetify 3 + Pinia |
| Ma'lumotlar bazasi | MySQL 8.0 |
| Web-server | Nginx |
| Konteyner | Docker + Docker Compose |

---

## Tezkor ishga tushirish (Docker)

### Talablar

- [Docker](https://docs.docker.com/get-docker/) 24+
- [Docker Compose](https://docs.docker.com/compose/) v2+

### 1. Loyihani yuklab oling

```bash
git clone <repo-url> mehnatmuhofazasi.uz
cd mehnatmuhofazasi.uz
```

### 2. `.env` faylini tayyorlang

```bash
cp .env.example .env
```

`.env` ichida kerakli qiymatlarni o'zgartiring:

```env
APP_PORT=80          # Sayt porti (http://localhost)
DB_DATABASE=muhofaza
DB_PASSWORD=secret   # MySQL root paroli
DB_PORT=3306
```

### 3. Backend `.env.docker` ni sozlang

```bash
# backend/muhofaza/.env.docker faylida APP_KEY ni o'zgartiring
# Yangi kalit generatsiya qilish uchun:
# docker run --rm php:8.3-alpine php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

### 4. Konteynerlarni yarating va ishga tushiring

```bash
docker compose up -d --build
```

> Birinchi marta build **5–10 daqiqa** olishi mumkin (npm install + composer install).

### 5. Tayyor!

| Manzil | Tavsif |
|--------|--------|
| http://localhost | Dastur (Vue SPA) |
| http://localhost/api | Backend API |
| http://localhost/health | Nginx holati |

---

## Login ma'lumotlari (default)

> Seeder ishga tushirilgandan keyin:

```
Admin:    admin      / admin123   (barcha tashkilotlar)
Operator: urgench_op / pass123    (Markaziy Apparat)
Operator: xiva_op    / pass123    (Xiva GTS)
```

Agar seeder ishlamagan bo'lsa, qo'lda ishga tushiring:

```bash
docker compose exec app php artisan db:seed
```

---

## Sertifikatlar

Testdan o'tgan o'quvchiga tizim sertifikatni PDF ko'rinishida, QR kod bilan yaratadi. Sertifikat **turi** davriy imtihonda tanlanadi va soat, sarlavha hamda kurs matnini belgilaydi. Boshlang'ich turlar: 36 soatlik malaka oshirish, 36 soatlik o'qish, 72 soatlik malaka oshirish, 288 soatlik qayta tayyorlov — ularni admin "Sertifikat sozlamalari"da tahrirlaydi va yangisini qo'shadi.

**Jarayon:**

1. O'quvchi onlayn testdan o'tadi (baho "qoniqarsiz" emas) va sertifikat avtomatik yaratiladi — hali **raqamsiz**. Qo'lda kiritilgan natijalar uchun imtihon sahifasidagi **"Sertifikat yaratish"** tugmasi ishlatiladi. Ro'yxatdan o'tish raqami avtomatik beriladi.
2. Admin yoki operator sertifikat **raqamini (№) qo'lda kiritadi** va PDF ni yuklab oladi:
   - bittasi — **Sertifikatlar** sahifasidagi 📄 tugmasi (raqam so'raladi);
   - imtihon bo'yicha hammasi — **"Imzolash uchun chop etish (PDF)"**: ro'yxatda har biriga raqam, "Birinchi raqam" + **"Ketma-ket to'ldirish"** bo'sh kataklarni to'ldiradi (raqamning oxirgi soni oshiriladi: `MM-0451` → `MM-0452`).

   Raqamsiz PDF yuklab olinmaydi. Raqam takrorlanmaydi; imzolangan sertifikat raqami o'zgarmaydi. Chop etilgach mas'ul shaxslar imzolaydi va muhr bosiladi.
3. Skanerlangan imzoli nusxa (PDF/JPG/PNG, 10 MB gacha) **Sertifikatlar** sahifasida ⬆️ tugmasi orqali yuklanadi.
4. O'quvchi test havolasini qayta ochib sertifikatni yuklab oladi. QR kod `https://mehnatmuhofazasi.uz/sertifikat/{kod}` tekshirish sahifasini ochadi va u yerdan imzoli nusxani yuklab olish mumkin.

**Birinchi deploydan keyin to'ldiring:**

- **Sertifikat sozlamalari** (faqat admin): ta'lim muassasasi nomi, 3 imzolovchining F.I.O., shahar.
- **Imtihon turlari:** "Dastur nomi".
- **Davriy imtihonlar:** "Kurs boshlanishi", "Kurs tugashi" va **"Sertifikat turi"**. Yangilashda eski "Soat hajmi" 36/72/288 bo'lgan imtihonlarga tur avtomatik qo'yiladi (36 → "36 soatlik malaka oshirish"); qolganlarida turni qo'lda tanlang.

To'ldirilmagan maydonlar sertifikatda `____` bo'lib chiqadi. To'ldirgandan keyin imzolanmagan sertifikatlarni "Sertifikat yaratish" tugmasi bilan qayta yaratish mumkin (raqam va QR o'zgarmaydi). Oldin berilgan avtomatik raqamlar (`2026-0001` kabi) saqlanadi.

> **Muhim:** QR havolasi `APP_URL` dan olinadi. Productionda `backend/muhofaza/.env.docker` ichida `APP_URL=https://mehnatmuhofazasi.uz` bo'lishi shart. Fayllar `storage/app/private/certificates/` da saqlanadi (Docker'da `app_storage` volume).

## Savollar bazalari

Savollar imtihon turidan mustaqil **bazalarda** saqlanadi (masalan, "Elektr xavfsizligi" — 200 savol). Har bir imtihon turida **test tarkibi** belgilanadi: qaysi bazadan nechta savol olinadi.

```
"Mehnat muhofazasi" testi:
  Elektr xavfsizligi      → 10 ta
  Yong'in xavfsizligi     → 10 ta
  Birinchi tibbiy yordam  →  5 ta   (jami 25 ta savol)
```

- **Savollar bazalari** sahifasida baza yaratiladi, savollar qo'lda qo'shiladi yoki Excel shablon orqali import qilinadi.
- **Imtihon turlari** → "Test tarkibi" tugmasi: bazalar, har biridan nechta savol, test vaqti va o'tish balli.
- Test boshlanganda har bir o'quvchiga har bazadan belgilangan sondagi savol **tasodifiy** tanlanadi va hammasi aralashtiriladi. Ball foizi tushgan savollar bo'yicha hisoblanadi.
- Bazada savol yetmasa yoki tarkib bo'sh bo'lsa, test havolalari yaratilmaydi va sababi aniq aytiladi.
- Bazalar, savollar va tarkibni faqat **admin** o'zgartiradi; operator faqat ko'radi. Testda ishlatilayotgan bazani o'chirib bo'lmaydi.
- Yangilashda mavjud savollar avtomatik ravishda imtihon turi nomli bazaga ko'chadi va tarkibga "hamma savol" yoziladi — mavjud testlar avvalgidek ishlaydi.
- **Bu versiyani deploy qilishdan oldin bazadan zaxira oling** (`docker compose exec mysql mysqldump -uroot -p muhofaza > backup.sql`). Migratsiya yarim yo'lda to'xtasa, `migrate` qayta ishga tushganda qolgan joyidan davom etadi.

## Testlar

Backend testlari alohida `muhofaza_test` MySQL bazasida ishlaydi (ishchi bazaga tegmaydi):

```bash
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS muhofaza_test"
cd backend/muhofaza && php artisan test
```

---

## Foydali buyruqlar

### Loglarni ko'rish

```bash
# Barcha konteynerlar loglari
docker compose logs -f

# Faqat backend
docker compose logs -f app

# Faqat nginx
docker compose logs -f nginx
```

### Konteynerga kirish

```bash
# Backend (PHP)
docker compose exec app sh

# MySQL
docker compose exec mysql mysql -uroot -psecret muhofaza
```

### Artisan buyruqlari

```bash
# Migratsiyalar
docker compose exec app php artisan migrate

# Migratsiyani qayta boshlash (barcha ma'lumotlar o'chadi!)
docker compose exec app php artisan migrate:fresh --seed

# Cache tozalash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
```

### Konteynerlarni to'xtatish

```bash
# To'xtatish (ma'lumotlar saqlanadi)
docker compose stop

# To'xtatish + konteynerlarni o'chirish
docker compose down

# To'xtatish + konteynerlar + volumelarni o'chirish (ma'lumotlar O'CHADI!)
docker compose down -v
```

---

## Loyiha tuzilmasi

```
mehnatmuhofazasi.uz/
├── docker-compose.yml          # Docker Compose konfiguratsiya
├── .env.example                # Root env namunasi
├── .dockerignore
│
├── nginx/
│   ├── Dockerfile              # Nginx image (Vue build + static serve)
│   └── default.conf            # Nginx konfiguratsiya
│
├── backend/
│   └── muhofaza/               # Laravel 13 loyihasi
│       ├── Dockerfile          # PHP 8.3-FPM image
│       ├── docker-entrypoint.sh
│       ├── .env.docker         # Docker uchun production .env
│       ├── app/
│       │   └── Http/Controllers/Api/
│       ├── database/migrations/
│       └── routes/api.php
│
└── frontend/
    └── muhofaza/               # Vue 3 + Vuetify loyihasi
        ├── src/
        │   ├── views/
        │   ├── components/
        │   ├── stores/
        │   └── services/api.js
        └── vite.config.js
```

---

## Arxitektura

```
Brauzer
   │
   ▼
Nginx :80
   ├── /          →  Vue SPA (static dist fayllar)
   ├── /api/*     →  PHP-FPM :9000 (Laravel)  [FastCGI]
   └── /storage/* →  Yuklangan fayllar
        │
        ▼
   PHP-FPM (app)
        │
        ▼
   MySQL :3306
```

---

## Rivojlantirish rejimi (Docker olmay)

### Backend

```bash
cd backend/muhofaza
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve        # http://localhost:8000
```

### Frontend

```bash
cd frontend/muhofaza
npm install
npm run dev              # http://localhost:5173
```

> Rivojlantirish rejimida `src/services/api.js` dagi `baseURL` ni `http://localhost:8000/api` ga o'rnating (default shu).

---

## API Endpointlar (asosiylar)

| Method | Endpoint | Tavsif |
|--------|----------|--------|
| POST | `/api/auth/login` | Kirish |
| POST | `/api/auth/logout` | Chiqish |
| GET | `/api/employees` | Xodimlar ro'yxati |
| GET | `/api/employees/{id}/upcoming-exams` | Xodim yaqin imtihonlari |
| GET | `/api/periodic-exams` | Davriy imtihonlar |
| GET | `/api/exam-types` | Imtihon turlari |
| GET | `/api/exam-types/calendar` | Oylik kalendar |
| POST | `/api/exam-results` | Natija kiritish |
| GET | `/api/dashboard/stats` | Dashboard statistika |
| GET | `/api/dashboard/upcoming-employees` | Yaqin imtihon xodimlari |
| GET | `/api/reports/annual-plan` | Yillik reja hisoboti |

---

## Muhit o'zgaruvchilari

### Root `.env` (Docker Compose uchun)

| O'zgaruvchi | Default | Tavsif |
|-------------|---------|--------|
| `APP_PORT` | `80` | Sayt porti |
| `DB_DATABASE` | `muhofaza` | Ma'lumotlar bazasi nomi |
| `DB_PASSWORD` | `secret` | MySQL root paroli |
| `DB_PORT` | `3306` | MySQL porti (host) |

### `backend/muhofaza/.env.docker` (Laravel uchun)

| O'zgaruvchi | Tavsif |
|-------------|--------|
| `APP_KEY` | Laravel shifrlash kaliti |
| `APP_DEBUG` | `false` (production) |
| `DB_HOST` | `mysql` (konteyner nomi) |
| `SANCTUM_STATEFUL_DOMAINS` | Frontend domeni |

---

## Muammo hal qilish

### Konteyner ishga tushmaydi

```bash
docker compose ps          # Holat
docker compose logs app    # Backend xatolari
docker compose logs nginx  # Nginx xatolari
```

### "502 Bad Gateway"

Backend hali tayyor emas. Biroz kuting va sahifani yangilang:
```bash
docker compose logs -f app   # "Starting PHP-FPM" chiqishini kuting
```

### MySQL ulanish xatosi

```bash
docker compose restart app   # Backend ni qayta ishga tushiring
```

### Migratsiya xatosi

```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate --force
```

### Frontend o'zgarishlar ko'rinmaydi

Nginx image ni qayta build qilish kerak:
```bash
docker compose build nginx
docker compose up -d nginx
```

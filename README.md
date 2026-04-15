# Muhofaza — Mehnat Muhofazasi Tizimi

Urganchtransgaz UK xodimlari uchun davriy xavfsizlik imtihonlarini boshqarish tizimi.

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
git clone <repo-url> muhofaza.urtg.uz
cd muhofaza.urtg.uz
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
Admin:    admin@muhofaza.uz   / password
Operator: operator@muhofaza.uz / password
```

Agar seeder ishlamagan bo'lsa, qo'lda ishga tushiring:

```bash
docker compose exec app php artisan db:seed
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
muhofaza.urtg.uz/
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

# Audit roli va testlarni real vaqtda kuzatish — dizayn hujjati

**Sana:** 2026-11-10
**Holat:** tasdiqlangan dizayn, reja kutilmoqda

## 1. Maqsad

Yangi **auditor** roli test topshirayotgan xodimlarni kuzatadi: kim hozir test yechyapti, qancha savolga javob bergan, qaysi variantni tanlagan va to'g'rimi — sahifa har 3–5 soniyada yangilanadi. Yakunlangan testlar natijalarini ham ko'radi. Auditor hech narsani o'zgartirmaydi.

Qo'shimcha (shu ish bilan birga): "Havolalarni yaratish" imtihonga kirmagan xodimlarning **boshlanmagan** eski havolalarini o'chiradi.

### Muvaffaqiyat mezonlari

- Admin "Foydalanuvchilar"da auditor yaratadi (tashkilot ixtiyoriy).
- Auditor tizimga kirganda faqat "Monitoring" sahifasini ko'radi; boshqa sahifa va API'lar unga yopiq (403).
- Xodim variant tanlagach, 5 soniya ichida Monitoring'da jarayon yangilanadi; batafsil oynada tanlangan variant va uning to'g'riligi ko'rinadi.
- Test sahifasi yangilansa yoki qayta ochilsa, saqlangan javoblar va taymer tiklanadi.
- Operator va admin ham Monitoring'ni ko'radi (operator — o'z tashkiloti).

### Qabul qilingan qarorlar

| # | Savol | Qaror |
|---|---|---|
| 1 | Auditor nimani ko'radi? | Faol testlar ro'yxati (jarayon, qolgan vaqt) + batafsil: har savol, tanlangan variant, to'g'ri/noto'g'ri; yakunlangach ball va baho |
| 2 | Qaysi tashkilotlar? | Admin biriktiradi: biriktirilsa — faqat o'sha, biriktirilmasa — hammasi (operator bilan bir xil qoida) |
| 3 | Boshqa sahifalar? | Yo'q — faqat "Monitoring" ("Hozir yechilmoqda" + "Yakunlangan") |
| 4 | Real vaqt mexanizmi | Javob har tanlovda serverga saqlanadi + polling (ro'yxat 5 s, batafsil 3 s); WebSocket yo'q |
| 5 | Eski havolalar | Imtihonga kirmagan xodimlarning `pending` sessiyalari havola yaratishda o'chiriladi |

### Doiradan tashqari (YAGNI)

- WebSocket/SSE, push bildirishnomalar.
- Auditorning testga aralashuvi (to'xtatish, vaqt qo'shish).
- Kamera/ekran kuzatuvi, brauzer oynasidan chiqishni aniqlash.
- Vaqti tugab topshirilmagan (`expired`) testni saqlangan javoblar bo'yicha avtomatik baholash.

## 2. Ma'lumotlar modeli

- `users.role`: enum `admin, operator, auditor` (migratsiya: `ALTER TABLE users MODIFY role ENUM('admin','operator','auditor') NOT NULL DEFAULT 'operator'`; `down()` — avval `auditor` larni `operator` ga o'tkazib, enum'ni qaytaradi).
- `exam_session_questions.answered_at` — timestamp, nullable (oxirgi javob vaqti).
- Migratsiya qayta ishga tushirishga chidamli (`hasColumn` tekshiruvi).

## 3. Kirish huquqi

- `User::isAuditor(): bool`.
- Yangi middleware `DenyAuditor` (alias `deny.auditor`): `role === 'auditor'` bo'lsa 403 `{"message": "Auditor faqat Monitoring bo'limini ko'radi"}`. U mavjud `auth:sanctum` guruhidagi **barcha** route'larga qo'llanadi, quyidagilardan tashqari: `auth/me`, `auth/logout`, `monitoring/*`.
- `monitoring/*` — admin (hammasi), operator va auditor: `organization_id` bo'lsa faqat o'sha tashkilot (davriy imtihonning `organization_id` si bo'yicha), bo'lmasa hammasi.
- `UserController` store/update: `role` `in:admin,operator,auditor`.

## 4. Javobni darhol saqlash

### 4.1 API (ochiq, token bilan)

| Endpoint | Qoida |
|---|---|
| `PUT /api/public/exam-sessions/{token}/answer` `{exam_session_question_id, selected_option}` | Sessiya `in_progress` va vaqti tugamagan bo'lishi kerak, aks holda 422 `Test boshlanmagan yoki yakunlangan` (vaqt tugagan bo'lsa sessiya `expired` ga o'tkaziladi — mavjud `findSessionAndExpire`). Savol shu sessiyaga tegishli, variant kaliti shu savol variantlaridan biri bo'lishi kerak — aks holda 422. `selected_option` va `answered_at = now()` yoziladi. Javob: `{saved: true}`. |
| `GET /api/public/exam-sessions/{token}/questions` | Faqat `in_progress` (vaqti tugamagan) da: `{started_at, duration_minutes, questions: [{exam_session_question_id, question_order, question_text, options, selected_option}]}`; aks holda 422 `Test boshlanmagan yoki yakunlangan`. |

`submit` o'zgarmaydi (barcha javoblarni o'zi yuboradi va baholaydi). `start` javobi o'zgarmaydi.

### 4.2 Test sahifasi (`TestSessionView`)

- Variant tanlanganda `PUT …/answer` fonda yuboriladi; xato bo'lsa jim o'tkaziladi (keyingi tanlovda va `submit` da hammasi yuboriladi).
- `show` javobida `status === 'in_progress'` bo'lsa, sahifa `GET …/questions` bilan savollar va saqlangan javoblarni yuklaydi, taymer `started_at + duration` dan davom etadi.

## 5. Monitoring

### 5.1 API (`auth:sanctum`, admin/operator/auditor, faqat o'qish)

| Endpoint | Javob |
|---|---|
| `GET /api/monitoring/active` | `[{id, employee: {id, full_name}, organization: {id, name}, periodic_exam: {id, title}, started_at, remaining_seconds, answered, total, last_activity_at}]` — `status = in_progress` va `started_at + duration > now`; `started_at` bo'yicha (yangisi tepada) |
| `GET /api/monitoring/finished` | Paginator (`per_page` ≤ 100, standart 25): `status = completed`; filtrlar `periodic_exam_id`, `date_from`, `date_to` (`finished_at` sanasi), `search` (F.I.O.); element: `{id, employee, organization, periodic_exam, finished_at, score_percent, grade}` (`grade` — `examResult.grade`); `finished_at` bo'yicha kamayish tartibida |
| `GET /api/monitoring/sessions/{id}` | `{id, status, employee, organization, periodic_exam, started_at, finished_at, remaining_seconds, summary: {answered, total, correct, score_percent}, grade, questions: [{order, question_text, options, selected_option, correct_option, is_correct, answered_at}]}`. `in_progress` da `is_correct` va `score_percent` jonli hisoblanadi (`selected_option === question.correct_option`, ball — `points` bo'yicha, `total_points` ga nisbatan); `completed` da saqlangan `is_correct`/`score_percent`. Scope tashqarisi → 403. |

`remaining_seconds` = `max(0, started_at + duration_minutes − now)`; `in_progress` dan boshqa holatda `null`.

### 5.2 Sahifa (`/monitoring`, `MonitoringView.vue`)

- Tablar: **"Hozir yechilmoqda"** (polling 5 s) va **"Yakunlangan"** (filtrlar: imtihon, sana oralig'i, qidiruv; sahifalash).
- "Hozir yechilmoqda" jadvali: xodim + tashkilot, imtihon, jarayon (`v-progress-linear` + "12/20"), qolgan vaqt (har soniya kamayadi; 60 s dan kam — qizil), oxirgi faollik ("15 soniya oldin"). Tepada "Jonli · har 5 soniyada yangilanadi". Bo'sh: "Hozir test yechayotgan xodim yo'q".
- Qatorni bosish → o'ng yon panel (`v-navigation-drawer` temporary, ~520px; telefonda to'liq kenglik): sarlavha, umumiy ko'rsatkichlar, savol kartochkalari (tanlangan — yashil/qizil, to'g'ri javob belgilangan, javobsiz — kulrang "Javob berilmagan"); `in_progress` bo'lsa 3 s polling.
- Polling `onUnmounted` va tab/oyna yopilganda to'xtaydi; tarmoq xatosida kichik ogohlantirish `Yangilab bo'lmadi, qayta urinilmoqda`, keyingi so'rov odatdagidek.

### 5.3 Rolga qarab frontend

- Router: `meta.roles` yoki guard — auditor uchun `/monitoring` dan boshqa barcha yo'llar `/monitoring` ga yo'naltiriladi; login'dan keyin auditor → `/monitoring`.
- Sidebar: auditor — faqat "Monitoring"; admin/operator — "IMTIHONLAR" guruhida "Monitoring" (`mdi-monitor-eye`).
- Auditor uchun header/sidebar'dagi `/notifications/unread-count` va `/retakes` so'rovlari yuborilmaydi.
- "Foydalanuvchilar": rol tanlovida "Auditor"; auditor uchun tashkilot ixtiyoriy (izoh: `Bo'sh qolsa — barcha tashkilotlar`).

## 6. Eski havolalarni tozalash

`ExamSessionController::generate` (so'rovda `employee_ids` berilmaganda): ishtirokchilar ro'yxati aniqlangach, shu imtihonning `status = pending` bo'lgan va xodimi ishtirokchilar orasida bo'lmagan sessiyalari o'chiriladi. `in_progress`, `completed`, `expired` sessiyalarga tegilmaydi. Javob shakli o'zgarmaydi.

## 7. Xatolar

| Holat | Javob |
|---|---|
| Auditor yopiq API'ga | 403 `Auditor faqat Monitoring bo'limini ko'radi` |
| Monitoring: boshqa tashkilot sessiyasi | 403 |
| Javob saqlash: boshlanmagan/yakunlangan/vaqti tugagan | 422 `Test boshlanmagan yoki yakunlangan` |
| Javob saqlash: begona savol / noto'g'ri variant | 422 |
| `…/questions` `in_progress` bo'lmasa | 422 `Test boshlanmagan yoki yakunlangan` |

## 8. Testlar

PHPUnit (MySQL `muhofaza_test`):
1. Rol: admin `role=auditor` bilan foydalanuvchi yaratadi (tashkilotsiz ham); auditor `auth/me` 200; `employees`, `periodic-exams`, `certificates`, `users` → 403 aynan xabar bilan.
2. Javob saqlash: saqlanadi + `answered_at`; qayta saqlash oxirgisini qoldiradi; `pending`/`completed` → 422; vaqti o'tgan → 422 va sessiya `expired`; begona savol → 422; mavjud bo'lmagan variant → 422.
3. `…/questions`: `in_progress` da saqlangan javoblar bilan; `pending` da 422.
4. `active`: faqat davom etayotgan (vaqti o'tgani yo'q); `answered/total` to'g'ri; tashkilotli auditor — faqat o'sha; tashkilotsiz auditor — hammasi; operator — o'z tashkiloti.
5. `sessions/{id}`: davom etayotganda jonli `is_correct`, `correct`, `score_percent`; yakunlangandan keyin saqlangan qiymatlar va `grade`; boshqa tashkilot → 403.
6. `finished`: `periodic_exam_id`, sana va qidiruv filtrlari.
7. Tozalash: ishtirokchi bo'lmagan `pending` sessiya o'chadi; ishtirokchi bo'lmagan `in_progress`/`completed` qoladi; ishtirokchilarniki qoladi.
8. Regressiya: `start`, `submit`, sertifikat testlari o'zgarishsiz o'tadi.

Frontend: `npm run build`; Playwright — xodim oynasida variant tanlash → auditor oynasida ≤ 6 s ichida "1/3" va batafsil panelda tanlangan variant; test sahifasini yangilash → javoblar tiklangan; auditor `/employees` ga o'tsa → `/monitoring`; sidebar'da faqat "Monitoring".

## 9. Deploy

- Migratsiya avtomatik (`users.role` enum, `answered_at`).
- Admin auditor foydalanuvchilarini yaratadi.

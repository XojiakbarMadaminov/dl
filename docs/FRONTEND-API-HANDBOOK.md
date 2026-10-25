# Dialog: frontend uchun to‘liq API qo‘llanma

**Auth/organization yangilangan sana: 2026-10-25; qolgan API kontraktlari tekshiruvi: 2026-10-20.** Ushbu hujjat hozirgi route, validation, action, response resource va testlarga asoslangan. Auth, tashkilot, survey builder, mantiq, moderatsiya, respondent va natijalar shu faylda jamlangan. Boshqa qo‘llanmani ochish shart emas.

Host, ID, sana, ism va tokenlar namunaviy. IDni haqiqiy create/start response’idan oling. JSON bloklari valid JSON. **Qisqartirilgan response** deb ko‘rsatilgan joyda qolgan fieldlar shu fayldagi umumiy kontraktga mos keladi.

### 2026-10-25: frontendga kiritiladigan auth/organization o‘zgarishlari

| Joy | Yangi kontrakt va frontend harakati |
| --- | --- |
| Register formasi | `organization_name` va `inn` fieldlarini ko‘rsatmang/yubormang. `POST /api/v1/auth/register` faqat user va token yaratadi. |
| Business onboarding | Alohida formadan `POST /api/v1/business/organizations`ga `name` va string `inn` yuboring. Register bergan Bearer token ishlatiladi; qayta login shart emas. Respondent bu bosqichni o‘tkazib ketishi mumkin. |
| Ilova ochilishi | `GET /api/v1/auth/me`dan `user` va top-level `organizations` massivini oling. Har bir element `{id, name, inn, current_user_role}`; bo‘sh massiv userning a’zoligi yo‘qligini bildiradi. |
| INN maydoni | INNni numberga aylantirmang: yetakchi nollar saqlanishi kerak. Yangi tashkilot uchun majburiy va barcha organizationlar bo‘yicha unique. 422da `error.fields.inn`ni shu input yonida ko‘rsating. |
| Create’dan keyingi state | 201 response’dagi `organization`ni organization state’ga qo‘shing va uning IDsi bilan business sahifalarini oching; yoki `/auth/me`ni qayta oling. `organization` object va `/auth/me`dagi `organizations` massivini aralashtirmang. |
| Eski tashkilotlar | `inn: null` bo‘lishi mumkin; mavjud a’zolik va rol saqlanadi. Owner/manager INNni `PATCH /api/v1/business/organizations/{organization}`ga joriy `name` bilan yuborib to‘ldiradi. |

To‘liq request/response misollari: [Auth](#auth) va [Organization](#organizations). Frontend so‘rovlarini boshqarish qoidalari [13-bo‘limda](#frontend-requests).

## Mundarija

1. [Tez boshlash](#quick-start)
2. [HTTP, token, CORS](#http)
3. [Auth](#auth)
4. [Organization va ruxsatlar](#organizations)
5. [Survey lifecycle](#lifecycle)
6. [Builder: survey, page, question, option](#builder)
7. [Shartli mantiq](#logic)
8. [Preview](#preview)
9. [Moderatsiya va publish](#moderation)
10. [Respondent: start, save, resume, Back, complete](#respondent)
11. [Natijalar va CSV](#results)
12. [Xatolar](#errors)
13. [Frontend so‘rovlarini boshqarish](#frontend-requests)
14. [Amaliy case’lar va checklist](#cases)
15. [Barcha endpointlar](#endpoint-index)
16. [Hozir mavjud bo‘lmagan imkoniyatlar](#limits)

<a id="quick-start"></a>
## 1. Tez boshlash

### 1.1. Atamalar

| Atama | Ma’nosi |
| --- | --- |
| User | Email/parol bilan kiradigan akkaunt. Bir akkaunt ham business, ham respondent bo‘lishi mumkin. |
| Organization | Survey egasi bo‘lgan tashkilot. Business ruxsatlari tashkilotdagi rolga bog‘liq. |
| Survey | Tillar, matnlar, sahifalar, savollar va qoidalardan tuzilgan so‘rovnoma. |
| Page | Savollarni birgalikda submit qilish sahifasi. |
| Question / option | Savol / javob varianti. Bir variantning IDsi barcha tillarda bir xil. |
| Logic rule | Savol ko‘rinishi yoki keyingi yo‘nalishni belgilovchi shart. |
| Participation | Bitta userning bitta survey bo‘yicha javob berish sessiyasi. |
| Answer | Participation ichidagi savol javobi. |

### 1.2. Eng qisqa ketma-ketlik

Quyidagi qisqa yo‘llarning oldiga `/api/v1` qo‘shiladi.

| Qadam | API | Frontend harakati |
| --- | --- | --- |
| 1 | `POST /auth/register` | Faqat user akkauntini ochish, tokenni olish; organization fieldlari yuborilmaydi. |
| 2 | `POST /business/organizations` | Token bilan alohida onboarding: `name` va unique string `inn`; user avtomatik owner bo‘ladi. |
| 3 | `GET /auth/me` | User va `organizations` ro‘yxatini, har biridagi `id`, `name`, `inn`, `current_user_role`ni olish. |
| 4 | `POST /business/organizations/{organization}/surveys` | Tillar va title’lar bilan draft yaratish. Birinchi page avtomatik yaratiladi. |
| 5 | `POST /business/organizations/{organization}/pages/{page}/questions` | Savollar yaratish. |
| 6 | `POST /business/organizations/{organization}/questions/{question}/options` | Choice savollarga variantlar qo‘shish. |
| 7 | `POST /business/organizations/{organization}/surveys/{survey}/pages` | Zarur qo‘shimcha sahifalar yaratish. |
| 8 | `PUT .../surveys/{survey}/logic` | Zarur bo‘lsa qoidalarni saqlash. |
| 9 | `GET .../surveys/{survey}/preview?locale=uz` | Preview va validate bilan sinash. |
| 10 | `POST .../surveys/{survey}/submit-review` | Tekshiruvga yuborish. |
| 11 | `POST /admin/moderation/surveys/{survey}/approve` | Alohida admin tokeni bilan tasdiqlash. |
| 12 | `POST .../surveys/{survey}/publish` | Business tokeni bilan `active` qilish. |
| 13 | `POST /auth/register` yoki `/auth/login` | Respondent tokenini olish; organization onboarding shart emas. |
| 14 | `POST /respondent/surveys/{survey}/participate` | Participation yaratish/davom ettirish. |
| 15 | `PUT /respondent/participations/{participation}/pages/{page}` | Sahifa javoblarini saqlash; backend qaytargan keyingi sahifani ochish. |
| 16 | `POST /respondent/participations/{participation}/complete` | `can_complete=true`dan keyin yakunlash. |
| 17 | `GET .../surveys/{survey}/results/summary` | Business natijalarni ko‘rishi. |

### 1.3. Misollardagi ID xaritasi

| Resurs | ID / mazmun |
| --- | --- |
| Organization / Survey | `7` / `42`; survey tillari `uz`, `ru`, default `uz` |
| Page | `10` — kirish; `11` — fikr; `12` — baholash |
| Question `101` | Page `10`, required single choice: «Davom etasizmi?» |
| Options `501`, `502`, `503` | Question `101`: «Ha», «O‘tkazib yuborish», «Yakunlash» |
| Question `102` | Page `10`, optional short text: «Mijoz kodi» |
| Question `201` | Page `11`, required short text; visibility sharti bo‘lsa ko‘rinadi |
| Question `202` | Page `11`, required long text: «Batafsil fikr» |
| Question `301` | Page `12`, required rating `1..5` |
| Question `302` | Page `12`, required multiple choice, min=1, max=2 |
| Options `601`, `602`, `603` | Question `302` variantlari |
| Participation | `90` |

<a id="http"></a>
## 2. HTTP va umumiy qoidalar

### 2.1. Base URL va headerlar

Base: `{BACKEND_ORIGIN}/api/v1`. Misol, server shu portda ishlasa: `http://localhost:8000/api/v1`. Production hostni konfiguratsiyadan oling.

```http
Authorization: Bearer 12|example-plain-text-token
Accept: application/json
Content-Type: application/json
```

Register/login Authorization talab qilmaydi. Qolgan biznes, moderatsiya, respondent operatsiyalariga token kerak. GET body’siz; filterlar query stringda. Body talab qilmaydigan POSTda body’ni tashlab ketish yoki `{}` yuborish mumkin.

Hozir **Bearer token** ishlaydi. Login/register cookie session ochmaydi; `/sanctum/csrf-cookie` login o‘rnini bosmaydi. Tokenni parchalamasdan to‘liq yuboring. Refresh-token API yo‘q; avtomatik refresh oqimini taxmin qilmang.

### 2.2. Ma’lumot shakllari

- IDlar numeric; UUID emas. Javob mapining kaliti JSONda string: `"101"`.
- `position` odatda 1dan boshlanadi; delete’dan keyin oraliq qolishi mumkin. ID yoki position’ga `+1` bilan navigatsiya qilmang.
- Sanalar ISO 8601, UTC/offset bilan. UI kerakli vaqt zonasiga o‘giradi.
- Optional matnlar `null` bo‘lishi mumkin.
- Bo‘sh `settings`, tarjima map yoki respondent `answers` ba’zan `[]` keladi. Bo‘sh mapni UI `{}`ga normallashtirishi mumkin.
- Create odatda 201, read/update/delete/lifecycle 200. Delete’da 204 kutmang.
- Barcha success response’larda yagona `data` envelope yo‘q: authda `user`, builderda `survey`/`page`/`question`/`option`, listlarda `data`.
- Message’lar hozir inglizcha. UI uchun `error.code`ni tarjima qiling; message matnini parse qilmang.

Asosiy enumlar:

| Field | Mumkin bo‘lgan qiymatlar |
| --- | --- |
| User `platform_role` | `user`, `admin` |
| User `status` | `active`, `inactive` |
| Organization `status` | `active`, `suspended` |
| Organization `current_user_role` | `owner`, `manager`, `researcher`, `viewer`; a’zo bo‘lmagan admin o‘qiganda null bo‘lishi mumkin |
| Participation `status` | `in_progress`, `completed` |
| Locale | `uz`, `ru`, `en` |

Survey statuslari 5-bo‘limda, question type’lari 6.6-bo‘limda. `editor`, `analyst`, `abandoned`, `disqualified` qiymatlariga frontend holati sifatida tayanmang.

### 2.3. CORS va rate limit

Local/testing’da `localhost`, `127.0.0.1`, `[::1]` HTTP/HTTPS originlari istalgan portda ruxsat etiladi. Boshqa originlar backend `CORS_ALLOWED_ORIGINS`ida bo‘lishi kerak. OPTIONS preflight login talab qilmaydi. CORS ruxsati auth ruxsati o‘rnini bosmaydi.

| Operatsiya | Limit | Oshganda |
| --- | --- | --- |
| Register + login | IP bo‘yicha umumiy `20/minute` | 429 `TOO_MANY_REQUESTS` |
| Authenticated APIlar | User bo‘yicha umumiy `120/minute` | 429 `TOO_MANY_REQUESTS` |

429da `Retry-After` va rate-limit headerlari bor. Cross-origin browser JS uchun hozir faqat `Content-Disposition` expose qilingan; `Retry-After`ni o‘qish `null` bo‘lishi mumkin. Bunday paytda UI standart kutish vaqtini, masalan 60 soniyani, ishlatsin. Autosave debounce bo‘lsin; cheksiz retry qilmang.

<a id="auth"></a>
## 3. Auth

### 3.1. Register

**`POST /api/v1/auth/register`**, Authorization yo‘q.

| Body field | Talab |
| --- | --- |
| `name` | Required string, max 255. |
| `email` | Required email string, max 255; trim/lowercase qilinadi; unique. |
| `password` | Required string, min 8. |
| `password_confirmation` | Optional; yuborilsa required string va password bilan bir xil. |
| `locale` | Optional `uz`/`ru`/`en`, default `uz`; platforma UI tili. |

Business va respondent uchun bir xil register body:

```json
{
  "name":"Ali Karimov",
  "email":"owner@example.com",
  "password":"SecurePass123!",
  "password_confirmation":"SecurePass123!",
  "locale":"uz"
}
```

Registration formida organization nomi va INN kiritilmaydi. Register faqat user yaratadi; biznes uchun keyingi alohida onboarding formida organization yaratiladi (4.3-bo‘lim). `role=business/respondent` yuborilmaydi. `platform_role=admin` yuborish admin huquqini bermaydi.

Response **201**:

```json
{
  "user":{
    "id":8,"name":"Ali Karimov","email":"owner@example.com",
    "locale":"uz","platform_role":"user","status":"active",
    "created_at":"2026-10-20T15:00:00+00:00"
  },
  "token":"12|example-plain-text-token"
}
```

Organization bu response’da yo‘q va registerda yaratilmaydi. Register token bergani uchun qayta login shart emas: shu token bilan `POST /business/organizations`ga `name` va `inn` yuboring. Yaratilgandan so‘ng response’dagi organization bilan state’ni yangilang yoki `GET /auth/me`ni qayta chaqiring.

### 3.2. Login

**`POST /api/v1/auth/login`**, Authorization yo‘q.

```json
{"email":"owner@example.com","password":"SecurePass123!","device_name":"Business web"}
```

`email` required email string, max 255; `password` required string. `device_name` optional string, max 255, default `web`. Email case normallashtiriladi. Response **200**: register’dagi aynan `{"user": User, "token": string}` shakli. Har login yangi token chiqaradi, boshqa qurilma tokenini avtomatik bekor qilmaydi.

Xatolar: `401 INVALID_CREDENTIALS`, `403 ACCOUNT_INACTIVE`, `422 VALIDATION_ERROR`, `429 TOO_MANY_REQUESTS`.

### 3.3. Ilova ochilganda profil

**`GET /api/v1/auth/me`**, body yo‘q, Bearer kerak.

```json
{
  "user":{
    "id":8,"name":"Ali Karimov","email":"owner@example.com",
    "locale":"uz","platform_role":"user","status":"active",
    "created_at":"2026-10-20T15:00:00+00:00"
  },
  "organizations":[
    {"id":7,"name":"Dialog Research","inn":"123456789","current_user_role":"owner"}
  ]
}
```

Response **200**. Token bo‘lsa me chaqiring: profil va organization konteksti bitta response’da keladi. `organizations` faqat userning a’zoliklari, eng yangi a’zolikdan boshlab; admin uchun ham shunday. A’zolik bo‘lmasa `organizations: []`. Har bir organizationdagi `current_user_role` shu userga tegishli; `user.platform_role` alohida. `inn` eski organizationlarda vaqtincha null bo‘lishi mumkin. `401 UNAUTHORIZED`da lokal auth state’ni tozalab login ekraniga qayting.

### 3.4. Logout

**`POST /api/v1/auth/logout`**, body yo‘q.

Response **200**:

```json
{"message":"Logged out successfully."}
```

Faqat joriy token bekor qilinadi. UI token, user va organizationga bog‘liq cache’ni tozalaydi. Boshqa qurilma tokeni ishlashda davom etadi.

<a id="organizations"></a>
## 4. Organization va ruxsatlar

### 4.1. Rollar

| Rol | Organization/survey/natijalarni o‘qish | Survey CRUD, logic, lifecycle | Organization nomi/INNni o‘zgartirish | Admin moderatsiya |
| --- | --- | --- | --- | --- |
| `owner` | O‘z tashkilotida | Ha | Ha | Yo‘q |
| `manager` | O‘z tashkilotida | Ha | Ha | Yo‘q |
| `researcher` | O‘z tashkilotida | Ha | Yo‘q | Yo‘q |
| `viewer` | O‘z tashkilotida | Yo‘q | Yo‘q | Yo‘q |
| Platform `admin` | Istalgan tashkilotni policy orqali o‘qiy oladi | Faqat mos organization roli ham bo‘lsa | Faqat mos organization roli ham bo‘lsa | Ha |
| Business a’zoligi yo‘q respondent | Yo‘q | Yo‘q | Yo‘q | Yo‘q |

Platform roli `user`/`admin`; organization roli alohida `current_user_role`. UI tugmalarni rolga mos ko‘rsatadi; yakuniy ruxsatni backend tekshiradi.

### 4.2. Ro‘yxat

**`GET /api/v1/business/organizations`**, body yo‘q. Response **200**, pagination yo‘q:

```json
{
  "data":[{
    "id":7,"name":"Dialog Research","inn":"123456789","status":"active","created_by":8,
    "current_user_role":"owner","created_at":"2026-10-20T15:00:00+00:00"
  }]
}
```

Faqat user a’zo bo‘lgan tashkilotlar; admin uchun ham barcha tashkilotlar katalogi emas. `data=[]` bo‘lsa onboarding ko‘rsating.

### 4.3. Organization yaratish

Registerdan keyingi alohida organization onboarding: **`POST /api/v1/business/organizations`**, Bearer token kerak. Yangi organization faqat shu endpoint orqali yaratiladi.

```json
{"name":"Dialog Research","inn":"123456789"}
```

`name` va `inn` required string, max 255. INN trim qilinadi va barcha organizationlar bo‘yicha unique. Uni JSON string sifatida yuboring: boshidagi nollar saqlanadi; number yuborish 422. Hozir mamlakatga xos qat’iy uzunlik tekshiruvi yo‘q. Response **201**:

```json
{
  "organization":{
    "id":7,"name":"Dialog Research","inn":"123456789","status":"active","created_by":8,
    "current_user_role":"owner","created_at":"2026-10-20T15:00:00+00:00"
  }
}
```

Organization va owner membership bitta tranzaksiyada yaratiladi. Creator avtomatik owner; `organization.id`ni haqiqiy response’dan oling. Bir xil INN boshqa userning organizationida ham bo‘lsa, **422 `VALIDATION_ERROR`**, `error.fields.inn` keladi; qo‘shimcha organization/a’zolik yaratilmaydi. Masalan:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The inn has already been taken.",
    "fields": {"inn": ["The inn has already been taken."]}
  }
}
```

Create’dan so‘ng organization switcher/state’ni shu response’dan yangilang yoki `/auth/me`ni qayta oling. Respondent uchun organization yaratish shart emas.

### 4.4. Read/update

| So‘rov | Body | Response |
| --- | --- | --- |
| `GET /api/v1/business/organizations/{organization}` | Yo‘q | 200, `{"organization": Organization}`; yuqoridagi resource. |
| `PATCH /api/v1/business/organizations/{organization}` | `{"name":"Yangi nom","inn":"123456789"}` | 200, yangilangan `{"organization": Organization}`; name required, inn optional; ikkalasi string, max 255. |

INN yuborilmasa joriy qiymat saqlanadi. O‘z organizationining INNini qayta yuborish mumkin; boshqa organizationniki yoki null/bo‘sh replacement 422. Eski tashkilotlar migratsiyadan keyin `inn: null` bilan saqlanadi; owner/manager shu PATCH orqali haqiqiy INNni to‘ldiradi.

Tashkilotga a’zolik/ruxsat yo‘q — 403. URLdagi survey/page/question/option tashkilotga mos kelmasa — 404. Har business URLda tanlangan organization ID bo‘lsin.

<a id="lifecycle"></a>
## 5. Survey lifecycle

```text
draft -> pending_review -> approved -> active <-> paused
             |                         |           |
             v                         +--> closed <+
       changes_requested                     |
             |                               v
             +--> pending_review          archived
```

| Status | Builder tahriri | Respondent start/save/Back/birinchi complete | Business tugmalari |
| --- | --- | --- | --- |
| `draft` | Ha | Yo‘q | Preview, submit-review, tarixsiz draftni delete |
| `changes_requested` | Ha | Yo‘q | Tuzatish, submit-review |
| `pending_review` | Yo‘q | Yo‘q | Tekshiruv kutilmoqda |
| `approved` | Yo‘q | Yo‘q | Publish |
| `active` | Yo‘q | Ha | Pause, close, results |
| `paused` | Yo‘q | Yo‘q | Resume, close, results |
| `closed` | Yo‘q | Yo‘q | Archive, results |
| `archived` | Yo‘q | Yo‘q | Read/results |

Approved hali ochiq emas: publish kerak. Pause strukturani tahrirlashga ruxsat bermaydi. Closed’ni qayta ochish/draftga qaytarish endpointi yo‘q.

Completed participation complete retry’i survey yopilgandan keyin ham muvaffaqiyatli. Ammo start/resume survey active ekanini avval tekshiradi. Yopilgan surveyda completed holatni ma’lum participation ID bilan GET orqali oling.

<a id="builder"></a>
## 6. Builder

### 6.1. Lokalizatsiya va PATCH qoidasi

`user.locale` — UI tili; `survey.locales/default_locale` — muallif tanlagan kontent tillari; `participation.locale` — respondent sessiyasi tili.

Builder map yuboradi/map oladi:

```json
{"title":{"uz":"Xizmat sifati","ru":"Качество обслуживания"}}
```

Respondent string oladi:

```json
{"title":"Xizmat sifati"}
```

PATCHdagi map **to‘liq almashtiriladi**, locale’lar avtomatik merge qilinmaydi. Mavjud title `uz`+`ru` bo‘lsa faqat `uz` yuborish `ru`ni saqlamaydi. Frontend mavjud map bilan merge qilib, to‘liq map yuborsin. Xuddi shu qoida settings va option label uchun ham amal qiladi.

### 6.2. Survey create

**`POST /api/v1/business/organizations/{organization}/surveys`**.

| Field | Talab |
| --- | --- |
| `locales` | Required array; kamida bitta `uz`/`ru`/`en`. Takrorlamasdan yuboring. |
| `default_locale` | Required; locales ichida bo‘lishi shart. |
| `title` | Required map; har selected tilda bo‘sh bo‘lmagan string. |
| `description`, `welcome_message`, `thank_you_message` | Optional translation map/null. |
| `internal_note` | Optional oddiy string max 5000/null; respondentga berilmaydi. |

```json
{
  "locales":["uz","ru"],"default_locale":"uz",
  "title":{"uz":"Xizmat sifati","ru":"Качество обслуживания"},
  "description":{"uz":"Fikringizni bildiring","ru":"Поделитесь мнением"},
  "welcome_message":{"uz":"Xush kelibsiz!","ru":"Добро пожаловать!"},
  "thank_you_message":{"uz":"Rahmat!","ru":"Спасибо!"},
  "internal_note":"Oktyabr tadqiqoti"
}
```

Response **201**, to‘liq resource:

```json
{
  "survey":{
    "id":42,"organization_id":7,"created_by":8,"status":"draft",
    "default_locale":"uz","locales":["uz","ru"],
    "title":{"uz":"Xizmat sifati","ru":"Качество обслуживания"},
    "description":{"uz":"Fikringizni bildiring","ru":"Поделитесь мнением"},
    "welcome_message":{"uz":"Xush kelibsiz!","ru":"Добро пожаловать!"},
    "thank_you_message":{"uz":"Rahmat!","ru":"Спасибо!"},
    "internal_note":"Oktyabr tadqiqoti",
    "published_at":null,"closed_at":null,"archived_at":null,
    "created_at":"2026-10-20T15:00:00+00:00","updated_at":"2026-10-20T15:00:00+00:00",
    "pages":[{"id":10,"survey_id":42,"position":1,"title":null,"description":null}]
  }
}
```

Birinchi page avtomatik yaratiladi. `survey.pages[0].id`ga savol qo‘shing; yana bir «birinchi page» yaratish shart emas.

### 6.3. Survey read/update/delete

| So‘rov | Qachon / body | Response |
| --- | --- | --- |
| `GET /api/v1/business/organizations/{organization}/surveys?page=1` | Dashboard, body yo‘q. | 200, resource pagination: `data`, `links`, `meta`; 20/page. Status/search filter hozir qo‘llanmagan. |
| `GET /api/v1/business/organizations/{organization}/surveys/{survey}` | Builder ochish/sync. | 200, `{"survey": Survey}`; `pages[].questions[].options[]` bilan. Logic alohida GETdan olinadi. |
| `PATCH /api/v1/business/organizations/{organization}/surveys/{survey}` | O‘zgargan metadata fieldlari. | 200, `{"survey": Survey}`; pages kelishi kafolatlanmaydi. |
| `DELETE /api/v1/business/organizations/{organization}/surveys/{survey}` | Body yo‘q; faqat tarixsiz draft. | 200, `{"message":"Survey deleted successfully."}`. |

PATCH misoli:

```json
{
  "title":{"uz":"Yangi sarlavha","ru":"Новый заголовок"},
  "description":null,"internal_note":"Tuzatildi"
}
```

Yuborilmagan fieldlar saqlanadi. Optional matnni tozalash — null. Title/locales/default_locale null bo‘lmasin. Til qo‘shishda yangi tildagi survey title’ini shu PATCHda yuboring. Savol/variant tarjimalarini draftda keyin to‘ldirish mumkin.

Changes_requested tarixli survey delete qilinmaydi. Holatga qarab `SURVEY_NOT_EDITABLE`/`SURVEY_HAS_HISTORY` qaytadi. Survey duplicate API yo‘q, faqat question duplicate bor.

### 6.4. Resource va cache

| Resource | Doimiy fieldlar | Shartli relation |
| --- | --- | --- |
| Survey | `id`, `organization_id`, `created_by`, `status`, `default_locale`, `locales`, `title`, `description`, `welcome_message`, `thank_you_message`, `internal_note`, `published_at`, `closed_at`, `archived_at`, `created_at`, `updated_at` | `pages` |
| Page | `id`, `survey_id`, `position`, `title`, `description` | `questions` |
| Question | `id`, `page_id`, `type`, `title`, `description`, `is_required`, `position`, `settings` | `options` |
| Option | `id`, `question_id`, `label`, `position` | Yo‘q |

Response’da relation yo‘qligi uning bo‘shligini anglatmaydi. PATCH question’da options kelmasa cache’dagi options’ni o‘chirmang. Aniq `[]` — bo‘sh relation. To‘liq daraxtni survey detail GETdan oling.

### 6.5. Page CRUD

**Create:** `POST /api/v1/business/organizations/{organization}/surveys/{survey}/pages`.

```json
{"title":{"uz":"Fikr","ru":"Мнение"},"description":null}
```

Ikkalasi optional map/null; `{}` ham mumkin. Position backend tomonidan oxiriga qo‘yiladi. Response **201**:

```json
{"page":{"id":11,"survey_id":42,"position":2,"title":{"uz":"Fikr","ru":"Мнение"},"description":null}}
```

**Update:** `PATCH /api/v1/business/organizations/{organization}/pages/{page}`; shu fieldlardan keraklilarini yuboring. **200**, `{"page": Page}`.

**Delete:** `DELETE /api/v1/business/organizations/{organization}/pages/{page}`, body yo‘q. **200**:

```json
{"message":"Page deleted successfully."}
```

Yagona oxirgi page o‘chirilmaydi: `422 CANNOT_DELETE_LAST_PAGE`. Page bilan ichidagi savollar/variantlar ham ketadi. Logic reference’lari avtomatik tozalanmaydi.

**Reorder:** `PUT /api/v1/business/organizations/{organization}/surveys/{survey}/pages/reorder`.

```json
{"pages":[12,10,11]}
```

**200**:

```json
{"message":"Pages reordered successfully."}
```

Shu survey’ning barcha page IDlari bir martadan. Yangi positionlar 1,2,3 bo‘ladi. Tartibni o‘zgartirish forward-only logicni buzishi mumkin; qayta tekshiring.

### 6.6. Question create va settings

**`POST /api/v1/business/organizations/{organization}/pages/{page}/questions`**.

Required: `type`, `title` map. Optional: `description` map/null, `is_required` boolean (default false), `settings` object/null. Translation qiymatlari string/null; nested obyekt emas. Draftda vaqtincha chala tarjima mumkin, reviewda to‘liqlik tekshiriladi.

Single choice — page 10, question 101:

```json
{
  "type":"single_choice","title":{"uz":"Davom etasizmi?","ru":"Продолжить?"},
  "description":null,"is_required":true,"settings":{}
}
```

Response **201**:

```json
{
  "question":{
    "id":101,"page_id":10,"type":"single_choice",
    "title":{"uz":"Davom etasizmi?","ru":"Продолжить?"},
    "description":null,"is_required":true,"position":1,"settings":[]
  }
}
```

Multiple choice — page 12, question 302:

```json
{
  "type":"multiple_choice",
  "title":{"uz":"Qaysi xizmatlar yoqdi?","ru":"Какие услуги понравились?"},
  "is_required":true,"settings":{"min_selections":1,"max_selections":2}
}
```

Short text — page 10, question 102:

```json
{
  "type":"short_text","title":{"uz":"Mijoz kodi","ru":"Код клиента"},
  "is_required":false,"settings":{"max_length":255}
}
```

Page 11dagi question 201ni ham short_text, required=true va mos title’lar bilan yarating; keyin visibility qoidasiga ulang.

Long text — page 11, question 202:

```json
{
  "type":"long_text","title":{"uz":"Batafsil fikr","ru":"Подробный отзыв"},
  "is_required":true,"settings":{"max_length":5000}
}
```

Rating — page 12, question 301:

```json
{
  "type":"rating","title":{"uz":"Baholang","ru":"Оцените"},"is_required":true,
  "settings":{
    "min":1,"max":5,
    "min_label":{"uz":"Yomon","ru":"Плохо"},
    "max_label":{"uz":"A’lo","ru":"Отлично"}
  }
}
```

Barcha type’lar create response’i **201**, `{"question": Question}`; yuqoridagi fieldlar o‘sha savol qiymatlari bilan.

| Type | Settings qoidasi / default | Widget / javob |
| --- | --- | --- |
| `single_choice` | Odatda `{}` | Radio / bitta option ID |
| `multiple_choice` | Optional positive integer min_selections/max_selections; ikkalasi bo‘lsa max ≥ min | Checkbox / takrorlanmagan option IDlar arrayi |
| `short_text` | Positive integer max_length, default 255 | Input / string |
| `long_text` | Positive integer max_length, default 5000 | Textarea / string |
| `rating` | Integer min ≥0 va max; default 1 va 5; max > min | Integer tanlash / number |

Rating min_label/max_label optional map; non-empty map berilsa barcha survey tillari kerak. Backend settings defaultlarini response’ga majburan qo‘shmaydi; UI jadvaldagi defaultlarni ishlatsin. `randomize_options` boolean qabul qilinsa ham MVPda randomizatsiya ishlamaydi; feature sifatida yoqmang.

### 6.7. Question update/delete/duplicate/reorder

**Update:** `PATCH /api/v1/business/organizations/{organization}/questions/{question}`.

```json
{
  "title":{"uz":"Yangi savol","ru":"Новый вопрос"},
  "is_required":false,"settings":{"min":0,"max":10}
}
```

Misol rating uchun. **200**, `{"question": Question}`. Settings to‘liq almashtiriladi: label’larni saqlash kerak bo‘lsa yangi settingsga kiriting. Type o‘zgartirganda mos settingsni birga yuboring; eski options/logic avtomatik yangilanmaydi.

**Delete:** `DELETE /api/v1/business/organizations/{organization}/questions/{question}`, body yo‘q. **200**:

```json
{"message":"Question deleted successfully."}
```

**Duplicate:** `POST /api/v1/business/organizations/{organization}/questions/{question}/duplicate`, body yo‘q. **201**, `{"question": Question}`, options bilan. Nusxa shu page **oxiriga** qo‘shiladi; title o‘zgarmaydi; question/option IDlari yangi. Logic nusxalanmaydi.

**Reorder:** `PUT /api/v1/business/organizations/{organization}/pages/{page}/questions/reorder`.

```json
{"questions":[202,201]}
```

**200**:

```json
{"message":"Questions reordered successfully."}
```

Shu page’ning barcha question IDlari bir martadan. Questionni boshqa page’ga ko‘chirish endpointi yo‘q.

### 6.8. Option CRUD/reorder

**Create:** `POST /api/v1/business/organizations/{organization}/questions/{question}/options`.

```json
{"label":{"uz":"Ha","ru":"Да"}}
```

**201**:

```json
{"option":{"id":501,"question_id":101,"label":{"uz":"Ha","ru":"Да"},"position":1}}
```

Shu yo‘l bilan 502/503 va question 302 uchun 601/602/603 variantlarini ham yarating. Har selected tilda label kiriting.

**Update:** `PATCH /api/v1/business/organizations/{organization}/options/{option}`. `label` map required, to‘liq almashtiriladi. **200**, `{"option": Option}`.

**Delete:** `DELETE /api/v1/business/organizations/{organization}/options/{option}`, body yo‘q. **200**:

```json
{"message":"Option deleted successfully."}
```

**Reorder:** `PUT /api/v1/business/organizations/{organization}/questions/{question}/options/reorder`.

```json
{"options":[503,501,502]}
```

**200**:

```json
{"message":"Options reordered successfully."}
```

Barcha reorder body’lari list array; obyekt emas. Tegishli barcha IDlar bir martadan kerak. Takroriy ID/malformed body — `VALIDATION_ERROR`; begona/yetishmaydigan IDlar — `INVALID_REORDER_PAYLOAD`.

### 6.9. Autosave va review oldidan

Resurslar alohida saqlanadi. Butun daraxtni survey PATCHga yubormang. Bir resursga so‘rovlarni ketma-ket yuboring, eski response yangisini bosib ketmasin. Submit-review’ni barcha autosave’lar tugagach yoqing.

Review uchun:

- Har fizik page’da kamida 1 question.
- Choice question’da kamida 2 option; selection min variantlar sonidan oshmasin.
- Har selected tilda survey title, question title, choice label to‘liq.
- Page title/rating endpoint label non-empty map bo‘lsa barcha selected tillari to‘liq.
- Settings va barcha logic reference/operator/value/destination valid.

Description/welcome/thank-you optional. UI ularni ishlatsa barcha tillarda to‘ldirishni ta’minlasin; hozir backend completeness validatori bu optional fieldlarning hammasini majburiy tekshirmaydi.

<a id="logic"></a>
## 7. Shartli mantiq

### 7.1. API va saqlash tartibi

Avval page/question/options yarating, keyin response’dagi IDlar bilan rule yozing.

| API | Body | Response |
| --- | --- | --- |
| `GET /api/v1/business/organizations/{organization}/surveys/{survey}/logic` | Yo‘q | 200, `{"rules":[Rule]}`; priority, keyin ID bo‘yicha. Viewer ham o‘qiy oladi. |
| `PUT /api/v1/business/organizations/{organization}/surveys/{survey}/logic` | `{"rules":[RuleInput]}` | 200, saqlangan `{"rules":[Rule]}`. Faqat editable state va edit ruxsati. |

PUT **butun rule to‘plamini almashtiradi**. Bitta rule o‘zgarsa ham saqlanishi kerak bo‘lgan hamma rule’ni yuboring. Barcha qoidalarni tozalash:

```json
{"rules":[]}
```

Rule IDlari qayta yaratiladi. Frontend form uchun alohida vaqtinchalik key ishlatsin; eski server rule IDsi barqaror emas. GET response’dagi `id`ni PUT bodyga qayta yubormang: inputda qo‘shimcha kalitlar ruxsat etilmagan.

### 7.2. Rule fieldlari

| Field | Talab |
| --- | --- |
| `action` | Required: `show_question`, `skip_to_page`, `complete`. |
| `priority` | Required integer 0..1000000. Kichik son avval ishlaydi. |
| `match` | Required `all` (AND) yoki `any` (OR). |
| `conditions` | Required list, 1..20 ta. Maksimum 100 rule/survey. |
| `source_page_id` | Navigation uchun required. Visibility uchun yubormang/null. |
| `target_page_id` | Faqat skip_to_page uchun required; oldinga page. |
| `target_question_id` | Faqat show_question uchun required. |

Condition: `question_id`, `operator`, zarur bo‘lsa `value`. Nested AND/OR guruhlari yo‘q.

| Action | Natija |
| --- | --- |
| `show_question` | Shart rost bo‘lsa savol ko‘rinadi, aks holda yashirin. Bir savolga faqat bitta visibility rule. Rule yo‘q savol odatda ko‘rinadi. |
| `skip_to_page` | Source page valid saqlangach target page’ga o‘tadi. Oradagi sahifalarni tashlaydi. |
| `complete` | Source page valid saqlangach yakunlash imkonini beradi. Participation hali completed emas: complete API kerak. |

### 7.3. Bitta to‘liq mantiq misoli

Bizning 10 → 11 → 12 survey uchun:

1. Question 201 faqat question 101 = «Ha» **va** question 102 ichida `VIP` bo‘lsa ko‘rinadi.
2. Question 101 = «O‘tkazib yuborish» bo‘lsa 10dan 12ga o‘tadi.
3. Question 101 = «Yakunlash» **yoki** question 102 = `STOP` bo‘lsa 10dan keyin yakunlash mumkin.

`PUT .../surveys/42/logic` body:

```json
{
  "rules":[
    {
      "action":"show_question","target_question_id":201,"priority":0,"match":"all",
      "conditions":[
        {"question_id":101,"operator":"equals","value":501},
        {"question_id":102,"operator":"contains","value":"VIP"}
      ]
    },
    {
      "action":"skip_to_page","source_page_id":10,"target_page_id":12,
      "priority":10,"match":"all",
      "conditions":[{"question_id":101,"operator":"equals","value":502}]
    },
    {
      "action":"complete","source_page_id":10,"priority":0,"match":"any",
      "conditions":[
        {"question_id":101,"operator":"equals","value":503},
        {"question_id":102,"operator":"equals","value":"STOP"}
      ]
    }
  ]
}
```

GET/PUT response **200**. Quyida bitta qoida bilan response kontrakti ko‘rsatilgan; yuqoridagi body saqlansa 3 ta qoida qaytadi:

```json
{
  "rules":[{
    "id":71,"action":"skip_to_page","source_page_id":10,
    "target_page_id":12,"target_question_id":null,"priority":10,"match":"all",
    "conditions":[{"question_id":101,"operator":"equals","value":502}]
  }]
}
```

Navigationda bir nechta qoida mos kelsa kichik priority yutadi; teng bo‘lsa PUTdagi input tartibi yutadi. Masalan 101=502 va 102=STOP bo‘lsa complete priority 0 jump priority 10dan oldin ishlaydi. Visibility navigation qoidalari bilan raqobat qilmaydi. Hech navigation rule mos kelmasa tabiiy tartib davom etadi.

### 7.4. Operatorlar

| Savol type | Operatorlar | `value` |
| --- | --- | --- |
| `single_choice` | `equals`, `not_equals` | Shu savolga tegishli integer option ID |
| `multiple_choice` | `contains`, `not_contains` | Bitta integer option ID; array emas |
| `rating` | `equals`, `not_equals`, `greater_than`, `less_than` | Settings chegarasidagi integer |
| `short_text`, `long_text` | `equals`, `not_equals`, `contains`, `not_contains` | Bo‘sh bo‘lmagan string, savol max_length chegarasida |
| Barcha type | `answered`, `not_answered` | Value’ni tashlab ketish yoki null |

Text taqqoslash case-sensitive, literal; regex emas. `VIP` va `vip` boshqa. Javob bo‘sh/yashirin/skipped bo‘lsa negative comparison ham rost bo‘lmaydi; bunday holat uchun `not_answered` ishlating.

```json
{"question_id":102,"operator":"not_answered"}
```

### 7.5. Qoidalarning chegaralari

- Barcha page/question/option reference’lari shu survey ichida.
- Visibility condition faqat **oldingi page** savoliga qarashi mumkin. Shu page ichida reaktiv show/hide yo‘q.
- Navigation condition source page yoki undan oldingi page’ga qarashi mumkin; kelajak savoli mumkin emas.
- Jump faqat keyingi page’ga; orqaga/o‘ziga qaytish mumkin emas. Shu bilan looplar bloklanadi.
- Savol/option/page o‘chirish, savol type’ini yoki page tartibini o‘zgartirish rule’ni buzishi mumkin. Backend rule’ni avtomatik o‘chirib yubormaydi; UI tuzatadi yoki PUT bilan olib tashlaydi.
- Qoidalar PUT, preview, submit-review va publishda qayta validatsiya qilinadi.

Malformed body — `422 VALIDATION_ERROR`; semantik xato — `422 INVALID_SURVEY_LOGIC`, field masalan `rules.1.conditions.0`.

<a id="preview"></a>
## 8. Preview

Preview real participation/answer yaratmaydi, natijalarni ifloslantirmaydi. Organizationni o‘qish huquqi bo‘lsa ishlaydi; viewer ham ko‘rishi mumkin.

### 8.1. Preview ochish

**`GET /api/v1/business/organizations/{organization}/surveys/{survey}/preview?locale=uz`**.

Locale optional; survey defaulti ishlatiladi. Survey tanlamagan locale — `UNSUPPORTED_SURVEY_LOCALE`.

Response **200**, to‘liq envelope va namunaviy birinchi page:

```json
{
  "survey":{
    "id":42,"status":"draft","locale":"uz","title":"Xizmat sifati",
    "description":"Fikringizni bildiring","welcome_message":"Xush kelibsiz!",
    "thank_you_message":"Rahmat!"
  },
  "current_step":{
    "page":{
      "id":10,"position":1,"title":null,"description":null,
      "questions":[
        {
          "id":101,"type":"single_choice","title":"Davom etasizmi?",
          "description":null,"is_required":true,"position":1,"settings":[],
          "options":[
            {"id":501,"position":1,"label":"Ha"},
            {"id":502,"position":2,"label":"O‘tkazib yuborish"},
            {"id":503,"position":3,"label":"Yakunlash"}
          ]
        },
        {
          "id":102,"type":"short_text","title":"Mijoz kodi",
          "description":null,"is_required":false,"position":2,
          "settings":{"max_length":255},"options":[]
        }
      ]
    },
    "progress":{"current_step":1,"total_steps":3,"percent":33,"is_first_page":true,"is_last_page":false},
    "next":{"type":"page","id":11}
  }
}
```

### 8.2. Page’ni sinash

**`POST /api/v1/business/organizations/{organization}/surveys/{survey}/preview/pages/{page}/validate`**.

Birinchi page body:

```json
{"locale":"uz","answers":{"101":502}}
```

Keyingi page 12ni sinash:

```json
{
  "locale":"uz",
  "previous_answers":{"101":502},
  "answers":{"301":5,"302":[601,602]}
}
```

`answers` required map, bo‘sh bo‘lsa `{}`. `previous_answers` optional map, faqat oldingi applicable sahifalar javoblari; ikkala map maksimum 1000 kalit. Locale optional string. Oldingi required savollar ham tekshiriladi. Kelajak/skipped page’ni preview validate qilish — `INVALID_PAGE_FLOW`.

Response **200**: `{"valid":true,"current_step": Step}`. `Step` yuqoridagi `page/progress/next` shakli. Invalid answer’da success `valid:false` kelmaydi; 422 error envelope keladi.

**Preview validate response keyingi page payloadini bermaydi:** `current_step` validatsiya qilingan page’ning o‘zi. `current_step.next`dan keyingi IDni oling. Hozir alohida «preview current-page GET» yo‘q; required javoblar berilmagan keyingi page validate’i ham page payload o‘rniga 422 qaytaradi.

Shuning uchun interaktiv conditional preview renderer’i builder detail’dagi page va GET logic qoidalaridan foydalanadi: oldingi applicable answers asosida `show_question`ni hisoblaydi, faqat visible inputlarni ko‘rsatadi, keyin backend validate bilan javob/yo‘nalishni tekshiradi. Bu faqat stateless preview uchun; haqiqiy respondent frontendida visibility/nav hisoblanmaydi, backend Step ishlatiladi. Agar preview renderer’i ham har qadamda tayyor localized/visible server payload talab qilsa, hozir buning alohida APIsi yo‘q.

Accumulated answers va Back history frontend xotirada saqlanadi. Oldingi javob o‘zgarsa downstream preview javoblarni tozalab, yo‘lni boshidan tekshiring. GET preview qaytargan resolved matnlar/faqat birinchi page o‘zi butun keyingi preview daraxti emas; builder detail’dan tanlangan locale matnlarini oling. Respondentdagidek doimiy server preview sessiyasi yo‘q.

<a id="moderation"></a>
## 9. Moderatsiya va lifecycle APIlari

### 9.1. Business: submit-review

**`POST /api/v1/business/organizations/{organization}/surveys/{survey}/submit-review`**, body yo‘q.

Faqat `draft` yoki `changes_requested`. Survey to‘liq valid bo‘lsa **200**, `{"survey": Survey, "message": string}`. Qisqartirilgan misol:

```json
{"survey":{"id":42,"status":"pending_review"},"message":"Survey submitted for review successfully."}
```

Survey fieldlarining qolgan qismi 6.4dagi kontraktga mos. UI formani lock qiladi. Xato bo‘lsa status o‘zgarmaydi; errorsni ko‘rsatib draftda qoldiring. Submit qayta yuborilib survey allaqachon pending_review bo‘lsa takroriy tarix yaratilmaydi.

### 9.2. Admin ro‘yxat/detail

**`GET /api/v1/admin/moderation/surveys?status=pending_review&page=1`**.

Faqat `user.platform_role=admin`. Default status pending_review. Boshqa haqiqiy SurveyStatus bilan ham filterlash mumkin. Status query hozir qat’iy enum-validatsiya qilinmaydi; noma’lum status bo‘sh natija berishi mumkin. Frontend faqat shu fayldagi statuslarni yuborsin.

Response **200**, resource pagination `data`, `links`, `meta`, 20/page. Elementlar Survey; nested pages listda yo‘q. Organization/creator obyektlari Survey resource ichida serialize qilinmaydi; `organization_id`, `created_by` mavjud.

**`GET /api/v1/admin/moderation/surveys/{survey}`**, body yo‘q. **200**, `{"survey": Survey}`; pages/questions/options bilan. Tekshiruvchi tarjimalar mapini ko‘radi.

### 9.3. Admin approve

**`POST /api/v1/admin/moderation/surveys/{survey}/approve`**, body yo‘q, admin tokeni.

Pending_review → approved. **200**, qisqartirilgan response:

```json
{"survey":{"id":42,"status":"approved"},"message":"Survey approved successfully."}
```

Business roli admin endpointiga huquq bermaydi; admin bo‘lmagan token — 403. Frontend admin rolini register bilan yaratmaydi. Approve survey’ni avtomatik active qilmaydi.

### 9.4. Admin tuzatishga qaytarishi

**`POST /api/v1/admin/moderation/surveys/{survey}/request-changes`**.

```json
{"comment":"Rus tilidagi variantlarni to‘liq tarjima qiling."}
```

Comment required string, 3–5000 belgi. Pending_review → changes_requested. **200**, qisqartirilgan response:

```json
{"survey":{"id":42,"status":"changes_requested"},"message":"Changes requested successfully."}
```

Business tuzatib yana submit-review qiladi. **Hozir survey resource’da moderation comment/history yo‘q va ularni GET qilish endpointi yo‘q.** Business detail’dan reviewer comment keladi deb UI qurilmang. Status orqali tuzatish kerakligini ko‘rsatish mumkin; comment tarixini ko‘rsatish uchun backend API kengaytmasi kerak bo‘ladi.

### 9.5. Publish/pause/resume/close/archive

Quyidagi barcha POSTlar body’siz; business owner/manager/researcher ruxsati kerak. Response **200**, `{"survey": Survey, "message": string}`; status/datesni response’dan yangilang.

| To‘liq API | Oldingi status | Yangi status | Message |
| --- | --- | --- | --- |
| `POST /api/v1/business/organizations/{organization}/surveys/{survey}/publish` | approved | active | `Survey published and activated successfully.` |
| `POST /api/v1/business/organizations/{organization}/surveys/{survey}/pause` | active | paused | `Survey paused successfully.` |
| `POST /api/v1/business/organizations/{organization}/surveys/{survey}/resume` | paused | active | `Survey resumed successfully.` |
| `POST /api/v1/business/organizations/{organization}/surveys/{survey}/close` | active/paused | closed | `Survey closed successfully.` |
| `POST /api/v1/business/organizations/{organization}/surveys/{survey}/archive` | closed | archived | `Survey archived successfully.` |

Publish response’ining qisqartirilgan misoli:

```json
{
  "survey":{"id":42,"status":"active","published_at":"2026-10-20T15:10:00+00:00"},
  "message":"Survey published and activated successfully."
}
```

Close closed_at, archive archived_atni yozadi. Noto‘g‘ri o‘tish — `422 INVALID_STATUS_TRANSITION`. Target statusda takrorlangan so‘rovlar duplicate side effect yaratmaydi. Retry faqat tegishli target state uchun: approve’ni active bo‘lgandan keyin qayta yuborish universal no-op emas.

<a id="respondent"></a>
## 10. Respondent oqimi

### 10.1. Asosiy qoida

Frontend backend bergan **ko‘rinadigan savollarni** render qiladi. Keyingi sahifani backend hisoblaydi. `next` har doim quyidagilardan biri:

```json
{"type":"page","id":12}
```

```json
{"type":"completed"}
```

`completed` destination — endi complete so‘rovini yuborish mumkin degani. O‘zi participation statusini completed qilmaydi. GET/start’dagi next joriy javob saqlanmaguncha taxminiy; bosilgan «Keyingi» avval PUT save qiladi.

### 10.2. Start yoki resume

**`POST /api/v1/respondent/surveys/{survey}/participate`**, respondent tokeni.

```json
{"locale":"uz"}
```

Locale optional/null; yangi sessiyada survey defaulti. `uz/ru/en` ichidan survey qo‘llaydigan tilni yuboring. `de` kabi code — VALIDATION_ERROR; global valid, lekin survey tanlamagan `en` — UNSUPPORTED_SURVEY_LOCALE.

**201** yangi va in_progress resume uchun ham. Bitta `(survey,user)`ga bitta participation. Start qayta chaqirilsa yangi record emas, mavjud sessiya. Original participation.locale o‘zgarmaydi.

To‘liq envelope, `current_step` 8.1dagi Step bilan bir xil:

```json
{
  "participation_id":90,"status":"in_progress","locale":"uz",
  "survey":{"id":42,"title":"Xizmat sifati","welcome_message":"Xush kelibsiz!"},
  "current_step":{
    "page":{
      "id":10,"position":1,"title":null,"description":null,
      "questions":[
        {
          "id":101,"type":"single_choice","title":"Davom etasizmi?",
          "description":null,"is_required":true,"position":1,"settings":[],
          "options":[
            {"id":501,"position":1,"label":"Ha"},
            {"id":502,"position":2,"label":"O‘tkazib yuborish"},
            {"id":503,"position":3,"label":"Yakunlash"}
          ]
        },
        {
          "id":102,"type":"short_text","title":"Mijoz kodi","description":null,
          "is_required":false,"position":2,"settings":{"max_length":255},"options":[]
        }
      ]
    },
    "progress":{"current_step":1,"total_steps":3,"percent":33,"is_first_page":true,"is_last_page":false},
    "next":{"type":"page","id":11}
  }
}
```

Start response’da saved answers **yo‘q**. Resume holatida inputlarni tiklash uchun participation GET chaqiring. Title/welcome bu response’da string, barcha translation maplar emas. Survey locale’lar ro‘yxati ham bu response’da yo‘q; respondent katalog/detail endpointi hozir mavjud emas.

Oldin yakunlangan surveyga yana participate, survey active bo‘lsa **200**:

```json
{"participation_id":90,"status":"completed","locale":"uz","completed":true,"thank_you_message":"Rahmat!"}
```

Survey active bo‘lmasa start/resume — `422 SURVEY_NOT_ACTIVE`. Start qayta yuborish, locale almashtirish yoki qayta login orqali ikkinchi marta javob berib bo‘lmaydi.

### 10.3. Javob formatlari

**`PUT /api/v1/respondent/participations/{participation}/pages/{page}`**.

`answers` majburiy map, question ID → to‘g‘ridan-to‘g‘ri qiymat. `{"option_id":501}` kabi nested wrapper yubormang.

| Type | Qiymat | Misol |
| --- | --- | --- |
| single_choice | Integer option ID | `{"101":501}` |
| multiple_choice | Distinct integer option IDlar arrayi | `{"302":[601,602]}` |
| rating | Settings oralig‘idagi integer | `{"301":5}` |
| short_text/long_text | String | `{"202":"Xizmat yoqdi"}` |
| Optional javobsiz | Kalitni tashlab ketish/null; text `""`, multi `[]` ham bo‘sh | `{"102":null}` |

**Har save shu page’dagi javoblar to‘plamini to‘liq almashtiradi.** Faqat o‘zgargan bitta inputni yubormang; page’dagi barcha visible javoblarni yuboring. Omitted optional eski javob o‘chadi. Required javob bo‘sh bo‘lsa 422.

Noto‘g‘ri javob bo‘lsa butun save bekor: valid javoblar ham qisman yozilmaydi, page oldinga siljimaydi. Unknown/hidden answer kalitlari ignore qilinadi va saqlanmaydi. Faqat current yoki history’dagi oldingi page saqlanadi; skipped/unvisited page — INVALID_PAGE_FLOW.

### 10.4. Oddiy «Keyingi» — page 10dan 11ga

```json
{"answers":{"101":501,"102":"VIP-123"}}
```

Response **200**:

```json
{
  "next":{"type":"page","id":11},"can_complete":false,
  "next_step":{
    "page":{
      "id":11,"position":2,"title":"Fikr","description":null,
      "questions":[
        {
          "id":201,"type":"short_text","title":"Qisqa tavsiya","description":null,
          "is_required":true,"position":1,"settings":{"max_length":255},"options":[]
        },
        {
          "id":202,"type":"long_text","title":"Batafsil fikr","description":null,
          "is_required":true,"position":2,"settings":{"max_length":5000},"options":[]
        }
      ]
    },
    "progress":{"current_step":2,"total_steps":3,"percent":67,"is_first_page":false,"is_last_page":false},
    "next":{"type":"page","id":12}
  }
}
```

Frontend `next_step`ni render qiladi. Top-level `next` — **endi o‘tilgan yo‘nalish**; `next_step.next` — yangi page uchun hozircha hisoblangan yo‘nalish. Ikkinchisidan foydalanib yana bir page’ni avtomatik tashlab ketmang.

Page 11 save body:

```json
{"answers":{"201":"Tezroq xizmat","202":"Xizmat yaxshi, navbatni kamaytirish kerak."}}
```

Keyingi response ham `next`, `can_complete:false`, `next_step`; endi next.id=12. Agar 102da VIP bo‘lmasa question 201 response’da bo‘lmaydi va uning requiredligi progressni bloklamaydi.

### 10.5. Oxirgi page va explicit complete

Page 12 body:

```json
{"answers":{"301":5,"302":[601,602]}}
```

Response **200**:

```json
{
  "next":{"type":"completed"},"can_complete":true,
  "current_step":{
    "page":{
      "id":12,"position":3,"title":"Baholash","description":null,
      "questions":[
        {
          "id":301,"type":"rating","title":"Baholang","description":null,
          "is_required":true,"position":1,
          "settings":{"min":1,"max":5,"min_label":"Yomon","max_label":"A’lo"},"options":[]
        },
        {
          "id":302,"type":"multiple_choice","title":"Qaysi xizmatlar yoqdi?",
          "description":null,"is_required":true,"position":2,
          "settings":{"min_selections":1,"max_selections":2},
          "options":[
            {"id":601,"position":1,"label":"Tezlik"},
            {"id":602,"position":2,"label":"Sifat"},
            {"id":603,"position":3,"label":"Narx"}
          ]
        }
      ]
    },
    "progress":{"current_step":3,"total_steps":3,"percent":100,"is_first_page":false,"is_last_page":true},
    "next":{"type":"completed"}
  }
}
```

Terminal save’da `next_step` yo‘q, `current_step` bor. Keyin **`POST /api/v1/respondent/participations/{participation}/complete`**, body yo‘q.

Response **200**:

```json
{"completed":true,"status":"completed","thank_you_message":"Rahmat!"}
```

Complete persisted applicable javoblarni qayta tekshiradi va yo‘l oxiriga yetilganini tekshiradi. Shu sababli complete oldin page save tugashini kuting. Participation completed bo‘lgandan keyin javoblar o‘zgarmaydi. Complete retry 200, completed_at o‘zgarmaydi. Complete hali valid bo‘lmasa INVALID_ANSWER yoki INVALID_PAGE_FLOW; survey to‘xtatilgan bo‘lsa SURVEY_NOT_ACTIVE.

### 10.6. Jump va early completion

Page 10ga `{"answers":{"101":502}}` yuborilsa `next.id=12`, `next_step.page.id=12`; page 11 savollari talab qilinmaydi.

Page 10ga `{"answers":{"101":503}}` yoki `{"answers":{"101":502,"102":"STOP"}}` yuborilsa `next.type=completed`, `can_complete=true`, `current_step.page.id=10`. Keyin explicit complete POST kerak. Alohida disqualified status yo‘q; odatiy completed bo‘ladi.

### 10.7. Resume / GET participation

**`GET /api/v1/respondent/participations/{participation}`**, body yo‘q. **200**, in_progress response: `participation_id`, `status`, `locale`, `current_step`, `answers`.

Quyida current_step’ning faqat yo‘nalish qismi ko‘rsatilgan **qisqartirilgan response**; haqiqiy Step’da page/progress/next to‘liq keladi:

```json
{
  "participation_id":90,"status":"in_progress","locale":"uz",
  "current_step":{"page":{"id":12},"next":{"type":"completed"}},
  "answers":{
    "101":{"text_value":null,"number_value":null,"option_ids":[501]},
    "102":{"text_value":"VIP-123","number_value":null,"option_ids":[]},
    "201":{"text_value":"Tezroq xizmat","number_value":null,"option_ids":[]},
    "202":{"text_value":"Batafsil fikr","number_value":null,"option_ids":[]},
    "301":{"text_value":null,"number_value":5,"option_ids":[]},
    "302":{"text_value":null,"number_value":null,"option_ids":[601,602]}
  }
}
```

GETdagi answer shakli PUT inputdan boshqa. Widgetga o‘giring: single = option_ids[0], multiple = option_ids, rating = number_value, text = text_value. Faqat current_step.page.questions ichidagi IDlarni hozirgi formaga joylang.

GET completed response’i **aynan**:

```json
{"participation_id":90,"status":"completed","completed":true}
```

Unda locale/current_step/answers/thank_you_message yo‘q. Thank-you kerak bo‘lsa complete response’idan saqlang; survey active bo‘lsa participate qayta chaqirish terminal thank_you qaytaradi. Completed ekanini bilgach forma ko‘rsatmang.

Participation IDni user+survey kontekstida saqlang. ID yo‘qolsa survey active ekanida participate orqali mavjudini topish mumkin. Survey closed bo‘lsa bu yo‘l ishlamaydi; respondentning barcha sessiyalarini listlash APIsi hozir yo‘q.

### 10.8. Back va oldingi javobni tahrirlash

**`POST /api/v1/respondent/participations/{participation}/back`**, body yo‘q. **200**, participation GET bilan bir xil envelope, saved answers bilan.

Back haqiqatda o‘tilgan previous page’ga qaytaradi: 10→12 bo‘lgan bo‘lsa 12dan 10ga, 11ga emas. First page’da no-op. Bir nechta Back kerak bo‘lsa so‘rovlarni ketma-ket kuting. Completed — PARTICIPATION_ALREADY_COMPLETED; survey inactive — SURVEY_NOT_ACTIVE; begona participation — 403.

Back o‘zi hozirgi **saqlanmagan** inputni saqlamaydi. «Keyingi» PUT navigatsiya qiladi; faqat autosave maqsadida PUT yuborib shu page’da qolish endpointi yo‘q. Backdan oldin unsaved javoblarni lokal saqlash yoki ogohlantirish UI qarori.

Oldingi visited page qayta saqlansa forward history qisqaradi, route qayta hisoblanadi. Endi hidden/skipped javoblar va choice pivotlar backendda o‘chadi; hali applicable javoblar saqlanadi. Frontend eski downstream state’ni ko‘r-ko‘rona tiklamasin: kerak bo‘lsa GET bilan qayta sync qilsin.

### 10.9. Progress va bo‘sh ko‘rinadigan page

`current_step` — fizik page ordini, `total_steps` — fizik page soni, percent — shular nisbati. Jump qilinsa progress 33dan 100ga o‘tishi mumkin. Early complete fizik birinchi page’da 33% bilan ham terminal bo‘lishi mumkin. `is_last_page` hozirgi destination terminal ekanini bildiradi; completed status emas.

Published fizik page savolsiz bo‘lmaydi, lekin hamma savoli condition orqali yashirilib `questions=[]` bo‘lishi mumkin. Bunday page’ni quyidagicha saqlab o‘ting:

```json
{"answers":{}}
```

### 10.10. Ownership va survey to‘xtashi

Faqat o‘z participationingizni GET/save/Back/complete qilasiz. Business owner ham respondent API orqali boshqa user sessiyasini boshqara olmaydi; o‘qish uchun business results detail bor.

Survey paused/closed bo‘lsa yangi start, save, Back va birinchi complete bloklanadi. In_progress GET active-state guard qilmaydi va mavjud page’ni qaytarishi mumkin; GET 200 bo‘lgani yozish ochiq degani emas. Save’dagi SURVEY_NOT_ACTIVE’ni UI albatta ishlasin. Pause’dan keyin business resume qilsa original sessiya davom etadi.

<a id="results"></a>
## 11. Natijalar va CSV

Business read huquqi kerak; viewer ham natijalarni ko‘ra oladi. URLdagi organization/survey/question/participation bir-biriga tegishli bo‘lishi shart. Survey faqat active bo‘lgandagina results olish mumkin degan cheklov yo‘q.

### 11.1. Umumiy filterlar

Summary, question aggregate, business participation list va export uchun:

| Query | Ma’nosi |
| --- | --- |
| `status=in_progress` yoki `completed` | Omit qilinsa ikkala holat ham kiradi. |
| `participation_locale=uz/ru/en` | Respondent sessiyasining saqlangan tiliga filter. |
| `locale=uz/ru/en` | Question/option label’larini qaysi tilda chiqarish. Participation filteri emas. Default survey default_locale. |
| `page=1` | Faqat list paginationi uchun. |

Masalan faqat ruscha completed javoblarni o‘zbekcha label bilan ko‘rsatish: `?status=completed&participation_locale=ru&locale=uz`. Noto‘g‘ri filter qiymati — 422 VALIDATION_ERROR. Summary locale’ni qabul qiladi, lekin matn yo‘qligi uchun countsga ta’sir qilmaydi. Detail label’lari esa participation.locale’da keladi.

### 11.2. Summary

**`GET /api/v1/business/organizations/{organization}/surveys/{survey}/results/summary`**.

Response **200**:

```json
{"survey_id":42,"started_count":10,"completed_count":8,"in_progress_count":2,"completion_rate":80}
```

Counts filterlangan to‘plamga tegishli. Completion rate = completed / started × 100, bir kasrgacha; started=0 bo‘lsa 0. `status=completed` bilan rate completed set ichida hisoblanishi sababli 100 bo‘ladi; umumiy konversiya uchun status filterini yubormang.

### 11.3. Question aggregate

**`GET /api/v1/business/organizations/{organization}/surveys/{survey}/results/questions/{question}`**.

Single/multiple choice **200**:

```json
{
  "question_id":101,"type":"single_choice","title":"Davom etasizmi?","total_responses":4,
  "distribution":[
    {"option_id":501,"label":"Ha","count":2,"percentage":50},
    {"option_id":502,"label":"O‘tkazib yuborish","count":1,"percentage":25},
    {"option_id":503,"label":"Yakunlash","count":1,"percentage":25}
  ]
}
```

Multiple choice ham shu shaklda, type=multiple_choice. Percentage maxraji shu savolga javob berganlar soni; bir user bir nechta variant tanlagani uchun jami 100%dan oshishi mumkin.

Rating **200**:

```json
{
  "question_id":301,"type":"rating","title":"Baholang","total_responses":2,"average":4.5,
  "distribution":[
    {"rating":1,"count":0,"percentage":0},
    {"rating":2,"count":0,"percentage":0},
    {"rating":3,"count":0,"percentage":0},
    {"rating":4,"count":1,"percentage":50},
    {"rating":5,"count":1,"percentage":50}
  ]
}
```

Average ikki kasrgacha; javob yo‘q bo‘lsa 0. Distribution configured min..max qiymatlarini qamrab oladi.

Text **200**:

```json
{"question_id":202,"type":"long_text","title":"Batafsil fikr","total_responses":2,"sample_answers":["Yaxshi xizmat","Navbatni kamaytiring"]}
```

Sample_answers — maksimum eng so‘nggi 50 non-null text. To‘liq text ro‘yxati/search API emas. Hidden/skipped savol uchun total_responses completed_count’dan kam bo‘lishi normal. Status filter berilmasa in_progress javoblar ham hisobga kiradi.

### 11.4. Business participation list

**`GET /api/v1/business/organizations/{organization}/surveys/{survey}/participations?page=1&status=completed`**.

**200**, raw Laravel pagination. Bu survey/admin listidagi resource paginationdan boshqa. To‘liq fieldlar quyidagi misolda:

```json
{
  "current_page":1,
  "data":[{
    "id":90,"survey_id":42,"user_id":25,"locale":"uz","status":"completed",
    "current_page_id":null,"page_history":[10,12],
    "started_at":"2026-10-20T15:15:00.000000Z","completed_at":"2026-10-20T15:18:00.000000Z",
    "last_activity_at":"2026-10-20T15:18:00.000000Z",
    "created_at":"2026-10-20T15:15:00.000000Z","updated_at":"2026-10-20T15:18:00.000000Z",
    "user":{"id":25,"name":"Vali","email":"respondent@example.com"}
  }],
  "first_page_url":"http://localhost:8000/api/v1/business/organizations/7/surveys/42/participations?page=1",
  "from":1,"last_page":1,
  "last_page_url":"http://localhost:8000/api/v1/business/organizations/7/surveys/42/participations?page=1",
  "links":[
    {"url":null,"label":"&laquo; Previous","page":null,"active":false},
    {"url":"http://localhost:8000/api/v1/business/organizations/7/surveys/42/participations?page=1","label":"1","page":1,"active":true},
    {"url":null,"label":"Next &raquo;","page":null,"active":false}
  ],
  "next_page_url":null,"path":"http://localhost:8000/api/v1/business/organizations/7/surveys/42/participations",
  "per_page":20,"prev_page_url":null,"to":1,"total":1
}
```

Pagination label matni va generated URL muhitga bog‘liq. Frontend mavjud filterlari bilan `page`ni o‘zi almashtirsin: generated link barcha filterlarni saqlashiga tayanmang. Duration fieldi tayyor kelmaydi; completed_at − started_atni UI hisoblaydi. In_progressda completed_at null.

### 11.5. Business participation detail

**`GET /api/v1/business/organizations/{organization}/surveys/{survey}/participations/{participation}`**.

Response **200**, bitta answerli misol:

```json
{
  "participation":{
    "id":90,"status":"completed","locale":"uz",
    "started_at":"2026-10-20T15:15:00.000000Z","completed_at":"2026-10-20T15:18:00.000000Z",
    "user":{"id":25,"name":"Vali","email":"respondent@example.com"},
    "answers":[{
      "question_id":101,"question_title":"Davom etasizmi?","type":"single_choice",
      "text_value":null,"number_value":null,"selected_options":[{"id":501,"label":"Ha"}]
    }]
  }
}
```

Answers array; respondent GETdagi map bilan aralashtirmang. Rating number_value’da; text text_value’da; choice selected_options’da. Labels participation.locale’da, survey defaultiga fallback bilan. User fieldlari faqat id/name/email. History/moderation comment bu javobda yo‘q.

### 11.6. CSV export

**`GET /api/v1/business/organizations/{organization}/surveys/{survey}/results/export?status=completed&participation_locale=ru&locale=uz`**.

**200**, JSON emas, fayl:

```http
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="survey_42_results_20261020_151800.csv"
```

UTF-8 BOM bor. Ustunlar: Participation ID, User ID, Locale, Started At, Completed At, so‘ng `Q{id}: {localized title}`. Choice katagi localized variantlar vergul bilan; text/rating o‘z qiymati. Hidden/skipped javob katagi bo‘sh. Formula sifatida ishlashi mumkin bo‘lgan katak boshiga apostrof qo‘yiladi.

Export defaulti ham in_progress+completed. Completed-only uchun statusni aniq yuboring. UI filterlarini exportga ham yuboring; page filteri exportga kerak emas. Export sinxron; job/status/polling endpointi yo‘q. Browserda token kerakligi sabab oddiy `<a href>` yangi tabida Authorization yuborilmaydi; fetch bilan blob olib yuklang.

### 11.7. Survey/admin ro‘yxatlaridagi pagination

Survey list va admin moderation list **resource pagination** qaytaradi. Bo‘sh listning to‘liq envelope misoli:

```json
{
  "data":[],
  "links":{
    "first":"http://localhost:8000/api/v1/business/organizations/7/surveys?page=1",
    "last":"http://localhost:8000/api/v1/business/organizations/7/surveys?page=1",
    "prev":null,"next":null
  },
  "meta":{
    "current_page":1,"from":null,"last_page":1,
    "links":[
      {"url":null,"label":"&laquo; Previous","page":null,"active":false},
      {"url":"http://localhost:8000/api/v1/business/organizations/7/surveys?page=1","label":"1","page":1,"active":true},
      {"url":null,"label":"Next &raquo;","page":null,"active":false}
    ],
    "path":"http://localhost:8000/api/v1/business/organizations/7/surveys",
    "per_page":20,"to":null,"total":0
  }
}
```

Data bo‘lsa har element 6.4dagi Survey; listlarda pages odatda yo‘q. Organization list esa pagination emas, faqat `{"data":[Organization]}`.

<a id="errors"></a>
## 12. Xatolar

### 12.1. Umumiy envelope

```json
{
  "error":{
    "code":"INVALID_ANSWER","message":"One or more answers are invalid.",
    "fields":{"answers.301":["Rating must be between 1 and 5."]}
  }
}
```

Fieldga bog‘liq xato bo‘lmasa `fields={}`. Proxy/network xatosi backendga yetmasa JSON envelope bo‘lmasligi mumkin; frontend fallback ko‘rsatsin.

| HTTP / code | Frontend harakati |
| --- | --- |
| 400 `BAD_REQUEST` | So‘rov sintaksisi/body formatini tekshirish. |
| 401 `UNAUTHORIZED` | Token yo‘q/bekor qilingan; auth state’ni tozalash, login. |
| 401 `INVALID_CREDENTIALS` | Login formida email/parol xatosi. |
| 403 `ACCOUNT_INACTIVE` | Login bloklangan akkaunt ekrani. |
| 403 `FORBIDDEN` | Ruxsat yo‘q; business rol/organization kontekstini tekshirish. |
| 404 `NOT_FOUND` | ID mavjud emas yoki path resurslari mos emas. |
| 405 `METHOD_NOT_ALLOWED` | URL to‘g‘ri, HTTP method noto‘g‘ri. |
| 422 `VALIDATION_ERROR` | Field errorsni inputlarga ko‘rsatish. |
| 422 `INVALID_DEFAULT_LOCALE` | Default tilni selected locales ichidan tanlash. |
| 422 `UNSUPPORTED_LOCALE` | Survey yaratish/yangilashdagi tilni tuzatish; request darajasida VALIDATION_ERROR ham bo‘lishi mumkin. |
| 422 `UNSUPPORTED_SURVEY_LOCALE` | Shu survey qo‘llamaydigan respondent/preview tili. |
| 422 `TRANSLATION_INCOMPLETE` | Ko‘rsatilgan savol/variant/locale tarjimasini to‘ldirish. |
| 422 `INVALID_QUESTION_SETTINGS` | Settingsni tuzatish; errors `min/max` yoki to‘liq question pathida kelishi mumkin. |
| 422 `INVALID_SURVEY_LOGIC` | Rule reference, action, operator, value yoki forward-only qoidasi xato. |
| 422 `SURVEY_NOT_EDITABLE` | Builderni lock qilish; statusni yangilash. |
| 422 `INVALID_STATUS_TRANSITION` | Statusni refetch qilish; tegishli tugmalarni ko‘rsatish. |
| 422 `SURVEY_NOT_ACTIVE` | Respondent yozishni to‘xtatish, survey vaqtincha/yakuniy yopiq holati. |
| 422 `SURVEY_HAS_HISTORY` | Delete taklifini olib tashlash; tarix saqlanadi. |
| 422 `CANNOT_DELETE_LAST_PAGE` | Oxirgi page’ni saqlab qolish. |
| 422 `INVALID_REORDER_PAYLOAD` | To‘liq to‘g‘ri ID listini qayta yuborish. |
| 422 `INVALID_ANSWER` | Xatoli savolni belgilash; current pageda qolish. |
| 422 `INVALID_PAGE_FLOW` | GET bilan current participationni sync qilish; qo‘lda page hisoblamaslik. |
| 422 `PARTICIPATION_ALREADY_COMPLETED` | Read-only completion holatiga o‘tish. |
| 422 `SURVEY_EMPTY` | Preview/start uchun sahifa yo‘q; builderni to‘ldirish. |
| 429 `TOO_MANY_REQUESTS` | Kutish, autosave/retry chastotasini pasaytirish. |
| 500 `INTERNAL_ERROR` | Umumiy fallback; yozuvchi so‘rovni cheksiz takrorlamaslik. |

### 12.2. Field pathlari

| Field path | Qaysi UI elementi |
| --- | --- |
| `email`, `password_confirmation` | Auth input |
| `title.ru` | Survey ruscha title |
| `pages.11.questions` | Builder page 11 bo‘shligi |
| `questions.201.title.ru` | Question 201 ruscha title |
| `options.501.label.ru` | Option 501 ruscha label |
| `questions.301.settings.min_label.ru` | Rating endpoint label tarjimasi |
| `rules.1.conditions.0` | Rules arrayining ikkinchi qoidasi, birinchi condition; index, ID emas |
| `answers.301` | Respondent question 301 inputi |

Tarjima xatosining misoli:

```json
{
  "error":{
    "code":"TRANSLATION_INCOMPLETE","message":"Survey translations are incomplete.",
    "fields":{"questions.201.title.ru":["Question 201 title is missing for locale 'ru'."]}
  }
}
```

Field errorsni faqat VALIDATION_ERROR uchun emas, INVALID_ANSWER/TRANSLATION_INCOMPLETE/INVALID_SURVEY_LOGIC/INVALID_QUESTION_SETTINGS uchun ham ko‘rsating. Survey validation birinchi bloklovchi xatoda to‘xtashi mumkin; bitta xato tuzatilgach boshqasi chiqishi normal.

<a id="frontend-requests"></a>
## 13. Frontend so‘rovlarini boshqarish

Form submit paytida loading yoqing va takroriy yuborishni bloklang. Navigatsiyada backend qaytargan `next`, `next_step`, `current_step` va `can_complete` qiymatlaridan foydalaning. `can_complete=true` bo‘lganda yakunlash tugmasini ko‘rsating yoki alohida complete so‘rovini yuboring.

Oldindan saqlangan javoblarni tiklash uchun participation GET qiling. Save response’dagi `next_step` javob qiymatlarini o‘z ichiga olmaydi. Formaga faqat joriy sahifaning ko‘rinadigan savollarini va ularga tegishli javoblarni tiklang.

Backend qaytargan `error.fields` xatolarini tegishli form maydonlari yonida ko‘rsating. Tarmoq xatosini API validation xatosidan alohida ko‘rsating. Tokenni to‘liq Bearer headerda yuboring; uni log yoki URLga yozmang.

Non-idempotent create so‘rovini tarmoq uzilganda avtomatik takrorlamang: list/detail orqali resurs serverda yaratilganini tekshiring. Duplicate complete/publish xavfsizligi barcha create endpointlarida idempotency kaliti mavjudligini anglatmaydi.

<a id="cases"></a>
## 14. Amaliy case’lar va qabul checklisti

### 14.1. Mantiq misolining natijalari

| Page 10 javobi | Kutilgan yo‘l | Muhim holat |
| --- | --- | --- |
| 101=501, 102=`VIP-123` | 10→11→12→complete | 201 va 202 required. |
| 101=501, 102 bo‘sh | 10→11→12→complete | 201 hidden; faqat 202 required. |
| 101=502 | 10→12→complete | Page 11 butunlay skipped. |
| 101=503 | 10→complete | Early completion; alohida complete POST. |
| 101=502, 102=`STOP` | 10→complete | Complete priority 0 jump priority 10dan oldin. |
| 101=501, 102=`vip-123` | 10→11→12→complete | Text case-sensitive; 201 hidden. |

### 14.2. Frontendda albatta sinash

| Case | Kutilgan xatti-harakat |
| --- | --- |
| Registerda takroriy email turli katta-kichik harf bilan | 422 email field xatosi; 500 emas. |
| Password confirmation farq qiladi | 422, akkaunt yaratilmaydi. |
| User register bo‘ldi | Faqat user/token; organization hali yaratilmaydi. |
| Organization yaratishda INN yo‘q yoki number yuborildi | 422, `error.fields.inn`; INN string sifatida yuboriladi. |
| INN boshqa tashkilotda band | 422, organization/a’zolik yaratilmaydi. |
| Eski tashkilotda INN null | Owner/manager `name` va `inn` bilan PATCH qilib to‘ldiradi. |
| Logoutdan keyin me | 401; eski token qayta ishlatilmaydi. |
| Userning organizationi yo‘q | Onboarding; survey yaratish uchun org avval yaratiladi. |
| Viewer builderga kirdi | O‘qish/preview mumkin, yozish/lifecycle tugmalari yo‘q. |
| Survey uz+ru, title faqat uz | Create 422; ikkala title kerak. |
| Question tarjimasi draftda chala | Saqlanishi mumkin; submit-review bloklanadi. |
| Approved surveyga respondent kirdi | Publish bo‘lmaguncha SURVEY_NOT_ACTIVE. |
| Required hidden savolga javob yo‘q | Save muvaffaqiyatli, hidden savol bloklamaydi. |
| Ko‘rinadigan page savollari bo‘sh | `answers={}` bilan keyingi yo‘nalishni olish. |
| Multiple choiceda duplicate/begona ID yoki maxdan ortiq | INVALID_ANSWER; page siljimaydi. |
| Rating 5.9 yoki 6, settings 1..5 | INVALID_ANSWER; integer 1..5 kerak. |
| Page javoblaridan bittasi invalid | Shu page’da hech qanday qisman yozuv yo‘q. |
| Refresh/resume | Startdan participation ID, GETdan original locale/current page/saved values. |
| Resume’da boshqa locale yuborildi | Original locale saqlanadi; tilni o‘zgartirish APIsi yo‘q. |
| 10→12dan Back | 10ga qaytadi; 11ga emas. |
| Oldingi javob yo‘lni o‘zgartirdi | Backend inapplicable javoblarni tozalaydi; UI downstream cache’ni sync qiladi. |
| Save `can_complete=true` | Participation hali in_progress; complete POSTdan keyin success ekrani. |
| Complete ikki marta | Ikkalasi 200, bitta yakunlangan participation. |
| Completed participationni tahrirlash | PARTICIPATION_ALREADY_COMPLETED. |
| Boshqa userning participation IDsi | GET/save/Back/complete — 403. |
| Survey davomida pause bo‘ldi | Save/Back/complete SURVEY_NOT_ACTIVE; yozishni to‘xtatish. |
| Reorderda bir ID ikki marta | 422; to‘liq distinct ID list yuborish. |
| Rule ishlatgan option o‘chirildi | Preview/review/publish INVALID_SURVEY_LOGIC; rule’ni tuzatish. |
| Natija filteri ru+completed | List/summary/aggregate/exportga bir xil filterlarni yuborish. |
| CSVga oddiy URL bilan o‘tish | Bearer yetib bormaydi; fetch blob ishlatish. |
| 429 yoki network uzilishi | Controlled retry; unsaved state’ni saqlab qolish; create’ni ko‘r-ko‘rona takrorlamaslik. |

### 14.3. Kerakli ekranlar

1. Register/login va token bilan session restore.
2. Organization onboarding/switcher, rolga mos ruxsatlar.
3. Survey list va tillarni tanlab create form.
4. Page/question/option builder, tarjima tablari, settings, autosave.
5. Logic form: action/match/conditions/priority, invalid reference xatolari.
6. Preview va frontend xotiradagi vaqtinchalik javoblar.
7. Business status/review/publish/pause/close/archive holatlari.
8. Admin queue/detail, approve yoki comment bilan request-changes.
9. Respondent start/current page, validation, Back/resume, complete/thank-you.
10. Results summary, question aggregates, participation list/detail, CSV.

<a id="endpoint-index"></a>
## 15. Barcha endpointlar indeksi

Placeholderlar: `{organization}`, `{survey}`, `{page}`, `{question}`, `{option}`, `{participation}` — oldin olingan numeric ID. Har qatorning body/response’i yuqoridagi tegishli bo‘limda yozilgan.

### Auth va organization

| Method | To‘liq path | Bo‘lim |
| --- | --- | --- |
| POST | `/api/v1/auth/register` | 3.1 |
| POST | `/api/v1/auth/login` | 3.2 |
| GET | `/api/v1/auth/me` | 3.3 |
| POST | `/api/v1/auth/logout` | 3.4 |
| GET | `/api/v1/business/organizations` | 4.2 |
| POST | `/api/v1/business/organizations` | 4.3 |
| GET | `/api/v1/business/organizations/{organization}` | 4.4 |
| PATCH | `/api/v1/business/organizations/{organization}` | 4.4 |

### Builder

| Method | To‘liq path | Bo‘lim |
| --- | --- | --- |
| GET | `/api/v1/business/organizations/{organization}/surveys` | 6.3, 11.7 |
| POST | `/api/v1/business/organizations/{organization}/surveys` | 6.2 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}` | 6.3 |
| PATCH | `/api/v1/business/organizations/{organization}/surveys/{survey}` | 6.3 |
| DELETE | `/api/v1/business/organizations/{organization}/surveys/{survey}` | 6.3 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/pages` | 6.5 |
| PATCH | `/api/v1/business/organizations/{organization}/pages/{page}` | 6.5 |
| DELETE | `/api/v1/business/organizations/{organization}/pages/{page}` | 6.5 |
| PUT | `/api/v1/business/organizations/{organization}/surveys/{survey}/pages/reorder` | 6.5 |
| POST | `/api/v1/business/organizations/{organization}/pages/{page}/questions` | 6.6 |
| PATCH | `/api/v1/business/organizations/{organization}/questions/{question}` | 6.7 |
| DELETE | `/api/v1/business/organizations/{organization}/questions/{question}` | 6.7 |
| POST | `/api/v1/business/organizations/{organization}/questions/{question}/duplicate` | 6.7 |
| PUT | `/api/v1/business/organizations/{organization}/pages/{page}/questions/reorder` | 6.7 |
| POST | `/api/v1/business/organizations/{organization}/questions/{question}/options` | 6.8 |
| PATCH | `/api/v1/business/organizations/{organization}/options/{option}` | 6.8 |
| DELETE | `/api/v1/business/organizations/{organization}/options/{option}` | 6.8 |
| PUT | `/api/v1/business/organizations/{organization}/questions/{question}/options/reorder` | 6.8 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/logic` | 7 |
| PUT | `/api/v1/business/organizations/{organization}/surveys/{survey}/logic` | 7 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/preview` | 8.1 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/preview/pages/{page}/validate` | 8.2 |

### Lifecycle va moderatsiya

| Method | To‘liq path | Bo‘lim |
| --- | --- | --- |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/submit-review` | 9.1 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/publish` | 9.5 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/pause` | 9.5 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/resume` | 9.5 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/close` | 9.5 |
| POST | `/api/v1/business/organizations/{organization}/surveys/{survey}/archive` | 9.5 |
| GET | `/api/v1/admin/moderation/surveys` | 9.2, 11.7 |
| GET | `/api/v1/admin/moderation/surveys/{survey}` | 9.2 |
| POST | `/api/v1/admin/moderation/surveys/{survey}/approve` | 9.3 |
| POST | `/api/v1/admin/moderation/surveys/{survey}/request-changes` | 9.4 |

### Respondent va results

| Method | To‘liq path | Bo‘lim |
| --- | --- | --- |
| POST | `/api/v1/respondent/surveys/{survey}/participate` | 10.2 |
| GET | `/api/v1/respondent/participations/{participation}` | 10.7 |
| PUT | `/api/v1/respondent/participations/{participation}/pages/{page}` | 10.3–10.6 |
| POST | `/api/v1/respondent/participations/{participation}/back` | 10.8 |
| POST | `/api/v1/respondent/participations/{participation}/complete` | 10.5 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/results/summary` | 11.2 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/results/questions/{question}` | 11.3 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/participations` | 11.4 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/participations/{participation}` | 11.5 |
| GET | `/api/v1/business/organizations/{organization}/surveys/{survey}/results/export` | 11.6 |

### Diagnostika

Quyidagilar public GET, body/token kerak emas:

| Method | Path | Response 200 |
| --- | --- | --- |
| GET | `/api/v1/health` | `{"status":"ok","version":"v1","timestamp":"2026-10-20T15:00:00+00:00"}` |
| GET | `/api/v1/auth/ping` | `{"status":"ok","context":"auth"}` |
| GET | `/api/v1/business/ping` | `{"status":"ok","context":"business"}` |
| GET | `/api/v1/respondent/ping` | `{"status":"ok","context":"respondent"}` |
| GET | `/api/v1/admin/ping` | `{"status":"ok","context":"admin"}` |

Ping muvaffaqiyati admin/business ruxsati borligini anglatmaydi; auth/ruxsatni haqiqiy resurs endpointida tekshiring.

<a id="limits"></a>
## 16. Hozir mavjud bo‘lmagan imkoniyatlar

Bu ro‘yxat frontend APIlarni taxmin qilib qurmasligi uchun:

- Password reset, email verification flow, refresh-token va cookie-only login APIlari yo‘q.
- Respondent uchun survey katalogi, alohida public survey detail, available-languages API va participation list yo‘q. Ma’lum survey ID va platformadan berilgan kontekst bilan participate qilinadi. Numeric ID public maxfiy link tokeni emas.
- Start yo‘li `/participate`; `/participation` emas. Admin yo‘li `/admin/moderation/surveys`; `/admin/surveys` emas. Til querysi preview/resultsda `locale`; `lang` emas.
- Organization a’zolarini invite/manage qilish endpointlari yo‘q; rollar permission modelida mavjud.
- Moderation history/reviewer comment’ni o‘qish APIsi yo‘q; request-changes POST commentni yozadi, lekin survey GET uni qaytarmaydi.
- Survey versioning, survey duplicate, questionni boshqa page’ga move, active strukturani tahrirlash va closed’ni reopen qilish yo‘q.
- Respondent locale’ini o‘zgartirish va completed javoblarni qayta tahrirlash yo‘q.
- Same-page reactive visibility, nested conditions, distinct disqualified, scoring/piping/carry-forward/randomization yo‘q.
- Public collectors/token links, anonymous response, KYC/MyID, to‘lov/reward yo‘q.
- Queued export job/status API, XLSX export, text result search va maxsus date-range filterlari yo‘q.

Mavjud UI uchun asosiy manba — shu fayldagi route va response kontraktlari. Imkoniyat bu ro‘yxatda bo‘lsa, uni mavjud endpointdan keladi deb hisoblamang.

# Dialog Frontend Integration Master Guide

Welcome to the Dialog API documentation for frontend engineers. This guide is your single entry point for integrating the Dialog survey platform with web (Next.js, React, Vue, Nuxt) and mobile client applications.

---

## 1. Quick Reference & Core Artifacts

| Resource | Path / URL | Description |
|:---|:---|:---|
| **Interactive Docs (HTML)** | `/docs` or [`public/docs/index.html`](../public/docs/index.html) | Complete interactive API explorer with Try It Out |
| **OpenAPI Specification** | [`public/docs/openapi.yaml`](../public/docs/openapi.yaml) | Standard OpenAPI 3.0.3 YAML for code generators |
| **Postman Collection** | [`postman/Dialog_API_Collection.postman_collection.json`](../postman/Dialog_API_Collection.postman_collection.json) | Ready-to-import Postman request collection |
| **Postman Environment** | [`postman/Dialog_Local_Environment.postman_environment.json`](../postman/Dialog_Local_Environment.postman_environment.json) | Pre-configured environment variables for local testing |

---

## 2. API Companion Guides

1. [**API Authentication (`API-AUTH.md`)**](API-AUTH.md)
   Implemented Sanctum Bearer token auth, headers, and the current limitation of cookie-only SPA authentication.
2. [**API Errors Envelope (`API-ERRORS.md`)**](API-ERRORS.md)
   Unified error response envelope, HTTP status mapping, error codes, and frontend error handlers.
3. [**API Enums Reference (`API-ENUMS.md`)**](API-ENUMS.md)
   All statuses, platform roles, organization roles, question types, and TypeScript types.
4. [**Localization Guide (`API-LOCALIZATION.md`)**](API-LOCALIZATION.md)
   Platform vs survey content localization, multi-language dictionaries (`uz`, `ru`, `en`), and resolved respondent payloads.
5. [**Survey Builder Guide (`API-SURVEY-BUILDER.md`)**](API-SURVEY-BUILDER.md)
   Step-by-step API guide to creating surveys, managing pages, questions, options, reordering, and moderation submission.
6. [**Respondent Flow (`API-RESPONDENT-FLOW.md`)**](API-RESPONDENT-FLOW.md)
   Participation lifecycle, backend-owned `SurveyFlow` navigation, answer submission formats, and idempotent completion.
7. [**Moderation & Lifecycle (`API-MODERATION.md`)**](API-MODERATION.md)
   Lifecycle transitions (`draft` → `pending_review` → `approved` → `active` → `paused` → `closed` → `archived`) and immutability invariants.
8. [**Results & Analytics (`API-RESULTS.md`)**](API-RESULTS.md)
   Summary metrics, question aggregates, respondent answer breakdown, and CSV data export.
9. [**MVP Conditional Logic (`API-SURVEY-LOGIC.md`)**](API-SURVEY-LOGIC.md)
   Builder rule API, type-specific AND/OR conditions, visibility, forward jumps, early completion, Back and stateless preview context.

---

## 3. Base URLs & Environments

- **Local Development**: `http://localhost:8000/api/v1`
- **CSRF Cookie Endpoint**: `http://localhost:8000/sanctum/csrf-cookie`
- **Documentation Web View**: `http://localhost:8000/docs`

### Local frontend CORS

In `local`/`testing`, HTTP and HTTPS origins on `localhost`, `127.0.0.1` and `[::1]` are allowed on any port (for example 3000, 5173 or 4200). Preflight OPTIONS allows the API methods and Authorization/JSON headers without requiring login. The actual request still requires a valid Bearer token where applicable. CORS headers also accompany API validation/authentication errors, so the frontend can read their error envelopes.

Credentialed browser requests are permitted for allowed origins, but this does not implement cookie-only SPA authentication. Continue sending `Authorization: Bearer <token>`. CSV downloads expose `Content-Disposition` for reading the filename.

Other origins require an explicit comma-separated allowlist:

```env
CORS_ALLOWED_ORIGINS=https://frontend.example.com,https://admin.example.com
CORS_ALLOW_LOCALHOST=false
```

Loopback origins default to disabled outside `local`/`testing`. If copying `.env.example` to production, explicitly set `CORS_ALLOW_LOCALHOST=false`; opt in with true only if local frontend access is intended. Wildcard `*` entries are ignored. After changing these values, run `php8.4 artisan config:clear` locally, or rebuild the configuration cache during deployment. CORS grants browser access to responses, not API authorization; never disable auth to fix CORS.

---

## 4. Predictable Development Demo Credentials

Run `php artisan migrate:fresh --seed` to initialize the database with predictable demo accounts and test data:

| Actor / Persona | Email | Password | Role / Access Scope |
|:---|:---|:---|:---|
| **Platform Admin** | `admin@dialog.uz` | `Secret123!` | Moderation queue review, platform administration |
| **Business Owner** | `owner@dialog.uz` | `Secret123!` | Owner of "Dialog Analytics Group", survey builder, results |
| **Respondent** | `respondent@dialog.uz` | `Secret123!` | Active survey respondent with completed participation |

### Pre-Seeded Survey Assets:
- **Organization**: `Dialog Analytics Group` (ID: `1`, INN: `"123456789"` for a fresh seed)
- **Active Survey**: "Mijozlar qoniqishi so‘rovi" (ID: `1`, locales: `['uz', 'ru', 'en']`, 2 pages, multiple questions, active with 1 completed response)
- **Draft Survey**: "Yangi mahsulot bo‘yicha fikrlar (Qoralama)" (ID: `2`, status: `draft`)

---

## 5. Golden Rules for Frontend Engineers

1. **Backend Owns Navigation**:
   Never calculate the next survey page on the client. Read typed `next` destinations from the backend; render `next_step` after non-final saves and `current_step` on preview/current-page reads.
2. **Handle the Standard Error Envelope**:
   All error responses have the format `{"error": {"code": "...", "message": "...", "fields": {...}}}`. Field errors map directly to input validation states.
3. **Surveys are Immutable Once Live**:
   Draft surveys can be edited. As soon as a survey is submitted and published, structural modifications (deleting/reordering/adding questions) are permanently locked.
4. **Localization is Object-Based**:
   Multilingual survey fields must be sent as dictionary objects keyed by ISO language code: `{"uz": "...", "ru": "...", "en": "..."}`.

## 6. User registration and organization onboarding

The registration form contains `name`, `email`, `password`, optional `password_confirmation`, and optional `locale`. Registration creates only the user and returns a Bearer token. Organization fields must be collected in a separate onboarding form after registration; respondents can skip this step.

Use `POST /api/v1/business/organizations` with the returned token and this JSON body:

```json
{"name":"Dialog Research","inn":"123456789"}
```

`name` and `inn` are required strings, maximum 255 characters. INN is trimmed and unique across the entire platform; keep it as a string, including leading zeros. Display `error.fields.inn` for HTTP 422 `VALIDATION_ERROR` when it is missing, invalid, or already in use. A successful HTTP 201 response contains `organization` and sets `current_user_role` to `owner`.

`GET /api/v1/auth/me` now returns `user` plus an `organizations` array of `{id, name, inn, current_user_role}`. Use this response to restore the organization switcher and permissions on startup. An empty array means the user has no memberships. `GET /api/v1/business/organizations` is still available to refresh the full organization resources separately. After creation, update the organization state from the response or reload `/auth/me`.

Existing organizations may have `inn: null` after the forward migration. Owners/managers can supply it through `PATCH /api/v1/business/organizations/{organization}` with `name` and `inn`. The current INN can be retained; another organization's INN or a null/empty replacement is rejected. See [the frontend handbook](FRONTEND-API-HANDBOOK.md#organizations) for complete examples.

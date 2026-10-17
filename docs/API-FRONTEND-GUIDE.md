# Dialog Frontend Integration Master Guide

Welcome to the Dialog API documentation for frontend engineers. This guide is your single entry point for integrating the Dialog survey platform with web (Next.js, React, Vue, Nuxt) and mobile client applications.

---

## 1. Quick Reference & Core Artifacts

| Resource | Path / URL | Description |
|:---|:---|:---|
| **Interactive Docs (HTML)** | `/docs` or [`public/docs/index.html`](file:///home/xojiakbar/PhpstormProjects/dl/public/docs/index.html) | Complete interactive API explorer with Try It Out |
| **OpenAPI Specification** | [`public/docs/openapi.yaml`](file:///home/xojiakbar/PhpstormProjects/dl/public/docs/openapi.yaml) | Standard OpenAPI 3.0.3 YAML for code generators |
| **Postman Collection** | [`postman/Dialog_API_Collection.postman_collection.json`](file:///home/xojiakbar/PhpstormProjects/dl/postman/Dialog_API_Collection.postman_collection.json) | Ready-to-import Postman request collection |
| **Postman Environment** | [`postman/Dialog_Local_Environment.postman_environment.json`](file:///home/xojiakbar/PhpstormProjects/dl/postman/Dialog_Local_Environment.postman_environment.json) | Pre-configured environment variables for local testing |

---

## 2. API Companion Guides

1. [**API Authentication (`API-AUTH.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-AUTH.md)  
   Sanctum SPA cookie flow (`/sanctum/csrf-cookie`), Bearer token auth, headers, and CORS troubleshooting.
2. [**API Errors Envelope (`API-ERRORS.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-ERRORS.md)  
   Unified error response envelope, HTTP status mapping, error codes, and frontend error handlers.
3. [**API Enums Reference (`API-ENUMS.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-ENUMS.md)  
   All statuses, platform roles, organization roles, question types, and TypeScript types.
4. [**Localization Guide (`API-LOCALIZATION.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-LOCALIZATION.md)  
   Platform vs survey content localization, multi-language dictionaries (`uz`, `ru`, `en`), and resolved respondent payloads.
5. [**Survey Builder Guide (`API-SURVEY-BUILDER.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-SURVEY-BUILDER.md)  
   Step-by-step API guide to creating surveys, managing pages, questions, options, reordering, and moderation submission.
6. [**Respondent Flow (`API-RESPONDENT-FLOW.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-RESPONDENT-FLOW.md)  
   Participation lifecycle, backend-owned `SurveyFlow` navigation, answer submission formats, and idempotent completion.
7. [**Moderation & Lifecycle (`API-MODERATION.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-MODERATION.md)  
   Lifecycle transitions (`draft` → `pending_review` → `approved` → `active` → `paused` → `closed` → `archived`) and immutability invariants.
8. [**Results & Analytics (`API-RESULTS.md`)**](file:///home/xojiakbar/PhpstormProjects/dl/docs/API-RESULTS.md)  
   Summary metrics, question aggregates, respondent answer breakdown, and CSV data export.

---

## 3. Base URLs & Environments

- **Local Development**: `http://localhost:8000/api/v1`
- **CSRF Cookie Endpoint**: `http://localhost:8000/sanctum/csrf-cookie`
- **Documentation Web View**: `http://localhost:8000/docs`

---

## 4. Predictable Development Demo Credentials

Run `php artisan migrate:fresh --seed` to initialize the database with predictable demo accounts and test data:

| Actor / Persona | Email | Password | Role / Access Scope |
|:---|:---|:---|:---|
| **Platform Admin** | `admin@dialog.uz` | `Secret123!` | Moderation queue review, platform administration |
| **Business Owner** | `owner@dialog.uz` | `Secret123!` | Owner of "Dialog Analytics Group", survey builder, results |
| **Respondent** | `respondent@dialog.uz` | `Secret123!` | Active survey respondent with completed participation |

### Pre-Seeded Survey Assets:
- **Organization**: `Dialog Analytics Group` (ID: `1`)
- **Active Survey**: "Mijozlar qoniqishi so‘rovi" (ID: `1`, locales: `['uz', 'ru', 'en']`, 2 pages, multiple questions, active with 1 completed response)
- **Draft Survey**: "Yangi mahsulot bo‘yicha fikrlar (Qoralama)" (ID: `2`, status: `draft`)

---

## 5. Golden Rules for Frontend Engineers

1. **Backend Owns Navigation**:  
   Never calculate the next survey page on the client. Always read `current_step.next` from the backend `SurveyFlow` response.
2. **Handle the Standard Error Envelope**:  
   All error responses have the format `{"error": {"code": "...", "message": "...", "fields": {...}}}`. Field errors map directly to input validation states.
3. **Surveys are Immutable Once Live**:  
   Draft surveys can be edited. As soon as a survey is submitted and published, structural modifications (deleting/reordering/adding questions) are permanently locked.
4. **Localization is Object-Based**:  
   Multilingual survey fields must be sent as dictionary objects keyed by ISO language code: `{"uz": "...", "ru": "...", "en": "..."}`.

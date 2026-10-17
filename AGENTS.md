# AGENTS.md — Dialog backend implementation rules

This file is the entry point for any coding agent working on the Dialog backend.

## 1. Read before coding

Read these files in order:

1. `docs/00-PROJECT-VISION-AND-MVP.md`
2. `docs/01-ARCHITECTURE.md`
3. `docs/02-MODULES-AND-CODE-STYLE.md`
4. `docs/03-DATABASE.md`
5. `docs/04-AUTH-AUTHORIZATION-ORGANIZATION.md`
6. `docs/05-API-STANDARDS.md`
7. `docs/06-SURVEY-BUILDER-AND-LOCALIZATION.md`
8. `docs/07-PARTICIPATION-SURVEY-FLOW-LOGIC.md`
9. `docs/08-MODERATION-AND-RESULTS.md`
10. `docs/09-TESTING-AND-DEVOPS.md`
11. `docs/10-FUTURE-EXTENSIONS.md`
12. `IMPLEMENTATION-PLAN.md`

## 2. Mandatory constraints

- Backend is a **Laravel 13 modular monolith**. Do not introduce microservices.
- PHP 8.4, PostgreSQL, Redis, Laravel Sanctum, Redis queue, Laravel Scheduler.
- API prefix is `/api/v1`.
- Route groups are `auth`, `business`, `respondent`, and `admin`.
- Primary keys are standard Laravel/PostgreSQL **BIGINT** IDs. Do not convert the project to UUID-first IDs.
- Use public random tokens only for resources that truly need a public/shareable identifier.
- Do not add survey versioning in the current MVP.
- Once a survey is active/published, its question/page structure is immutable in the current MVP.
- Do not add survey-logic database tables in the current MVP. The code must still use a `SurveyFlow` abstraction so logic can be added later without changing the frontend contract.
- Do not add MyID/KYC/payment/reward functionality now.
- Platform UI supports `uz`, `ru`, `en`.
- Survey content languages are selected by the business user when creating a survey. All selected-language survey titles are required.
- Builder APIs return translation maps; respondent APIs return strings resolved to the participation locale.
- Business logic must not live in controllers.
- Writes: `Controller -> FormRequest -> Action -> Model/Service`.
- Complex reads: `Controller -> Query -> Resource`.
- Services are only for reusable domain logic, not generic `*Service` wrappers for every model.
- Repository interfaces are not required by default.
- Cross-module Eloquent usage is allowed when it keeps the code simple and clear. Module boundaries are organizational, not microservice boundaries.
- Database migrations stay in Laravel's standard `database/migrations` directory.
- Do not add `SoftDeletes` everywhere. Use explicit lifecycle states where appropriate.
- All important authorization must be checked on the backend. Hiding UI controls is never authorization.
- Keep implementation simple. Do not build future features until the corresponding phase explicitly starts.

## 3. Agent workflow

For each task:

1. Read the relevant docs and the task checklist.
2. Inspect existing code before changing anything.
3. Prefer the smallest implementation that satisfies the documented behavior.
4. Add or update tests for domain-critical behavior.
5. Run formatting/static analysis/tests relevant to the changed area.
6. Mark only completed checklist items in `IMPLEMENTATION-PLAN.md`.
7. Do not silently change architecture decisions. If a decision conflicts with existing code, document the conflict instead of inventing a new architecture.

## 4. Non-blocking open decisions

The following are intentionally not frozen yet and must not be invented by an agent unless a task explicitly resolves them:

- Frontend framework (Vue/React/Nuxt/etc.).
- Exact public collector/distribution model and public-link token format.
- Exact local Redis installation/provisioning method.
- MyID provider details.
- Payment/reward provider details.
- Full conditional-logic rule editor and rule storage schema.

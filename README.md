# Dialog architecture MD package

This package contains the current backend architecture and implementation decisions for Dialog.

Start with `AGENTS.md`, then follow the ordered docs and `IMPLEMENTATION-PLAN.md`.

## Status

The core backend architecture is sufficiently defined to begin implementation of the MVP.

Some intentionally non-blocking decisions remain open and are listed in `AGENTS.md`. Agents must not invent those features.

## Key frozen decisions

- Laravel 13 / PHP 8.4 / PostgreSQL / Redis / Sanctum.
- REST `/api/v1`.
- Modular monolith; no microservices.
- Native local environment (Nginx, PHP-FPM, PostgreSQL, Composer; Redis required by app stack).
- Route contexts: auth/business/respondent/admin.
- Standard BIGINT PK/FK strategy.
- Survey is the root domain; no generic Study table now.
- No survey versioning now; active survey content is immutable.
- UZ/RU/EN platform localization.
- Survey languages chosen at creation; selected-language survey titles required.
- Survey content translations stored as JSONB maps.
- SurveyFlow abstraction exists now; full logic engine is later.
- Respondent login required; one participation per survey per user.
- Preview does not pollute real participation/results.

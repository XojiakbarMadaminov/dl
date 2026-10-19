# Documentation/code audit — 2026-10-19

## Scope and method

Compared the numbered architecture/domain documents and implementation checklist with routes, migrations, models, actions, validators, controllers, integration guides and tests. The initial PostgreSQL suite passed 42 tests, but did not cover the defects below. This is a source/test audit, not production infrastructure verification or a proof of concurrent behavior under load.

## Fixed discrepancies

| Finding | Resolution |
| --- | --- |
| Completion could bypass every required answer and close an inactive survey | Revalidate persisted answers before first completion, enforce active state, lock participation in a transaction; retries preserve timestamps |
| Start/save/complete could use stale state or race duplicate requests | Serialize starts on the survey row; lock/reload participation before save and completion |
| Completed starts errored instead of returning existing terminal state | Return HTTP 200 with the existing completed participation, never create another |
| Resume lacked saved answer data | Include locale and saved answer values on the current-participation read |
| Navigation used integers / `complete`, unlike the core typed contract | Use `{"type":"page","id":...}` / `{"type":"completed"}` consistently in preview and participation |
| Fractional IDs/ratings were silently truncated; duplicate selections reached the unique constraint | Reject malformed IDs, duplicate selections and non-integer ratings before persistence |
| Localized values could be nested objects rather than strings | Add nested translation-value validation to builder requests |
| Rating labels exposed translation maps to respondents | Resolve endpoint labels to the participation/preview locale |
| Used page titles and endpoint labels lacked selected-language completeness checks | Add structured missing-translation errors |
| Publish validation accepted empty pages and unattainable selection minima | Block both before moderation submission; validate effective rating defaults |
| Duplicate moderation/publication could repeat effects; lifecycle guards read stale state | Lock/reload survey within state-transition transactions; target-state retries are no-ops |
| Deleting changes-requested surveys erased review history | Restrict hard deletion to drafts without moderation/participation history through `DeleteSurveyAction` |
| Result list/analytics/export selected different records | Reuse `SurveyParticipationsQuery` with status and participation-locale filters across all four |
| Business result lists serialized unnecessary account fields | Restrict nested user selection to ID, name and email |
| CSV promised a BOM but omitted it; cells could be interpreted as spreadsheet formulas | Emit UTF-8 BOM, explicitly disable CSV escape character, neutralize dangerous cell prefixes, load records in batches |
| Integration guides advertised unsupported auth/roles/statuses and incorrect answer/result shapes | Correct guides, controller documentation and Scribe configuration to implemented behavior |
| Checklist claimed conditional/unverified work was complete | Reopen password reset, queued export and actual production worker/scheduler verification |

## Architecture conflicts retained explicitly

These are not silently resolved by rewriting architectural decisions or destructive migration changes:

1. **Moderation storage**: `03-DATABASE.md` defines one row per review round with `submitted_by`, `submitted_at`, `status`, `reviewed_by`, `reviewed_at`. The existing migration/model instead stores separate `submitted` and decision events with `moderator_id`, `decision`, `decided_at`. Submission actor is not recorded on the submission event itself. History is now protected, but converting existing data requires a deliberate, tested forward migration/backfill; the core design remains authoritative.
2. **Audit storage**: core docs name `actor_id`, `subject_type`, `subject_id`, `metadata`, `user_agent`; code uses `user_id`, `entity_type`, `entity_id`, `payload` and has no user-agent column. Actual submission/publication/resume event names are `survey.submitted_for_review`, `survey.published`, `survey.resumed`, rather than all the sample names in the core document. Do not assume the target columns exist in reporting SQL.
3. **Layering**: several existing page/question/option and organization mutations still execute directly in controllers; moderation comments do not yet consistently use FormRequests. Some complex reads serialize directly rather than through Query/Resource. The audit extracts respondent answer requests, result filtering and survey deletion; the subsequent logic implementation also extracts preview validation into a FormRequest/Action. The remaining architectural refactor is not complete.

## Conditional work and operational limits

- SPA session/cookie transport is recommended, not currently implemented. Existing API integration must use Bearer tokens; a CSRF cookie alone does not establish authentication.
- Password-reset endpoints are absent; their checklist item was conditional and is now unchecked.
- Export is synchronous, not queued. Batching reduces model memory, but the response string still grows with the CSV. A heavy-export job/status/download contract is not invented here.
- Production queue workers, cron/scheduler, backups and restore execution were not accessed. A runbook is not proof they run on the deployed server.
- The default CLI is PHP 8.5.11. Final formatting, static analysis and tests were also run explicitly with PHP 8.4.26 to match the required PHP 8.4 series.
- Basic conditional logic was subsequently approved for MVP on 2026-10-19 and implemented under `API-SURVEY-LOGIC.md`. Advanced logic, public collectors, versions, KYC, payments and new question types remain out of scope.

## Client compatibility

Clients must adopt typed `next` destinations. Non-final answer saves return `next_step`; final saves return `current_step` and `can_complete: true`. CSV now matches the list by default, including in-progress participations; send `status=completed` to retain completed-only exports. Detailed integration examples are in `API-RESPONDENT-FLOW.md` and `API-RESULTS.md`.

## Validation

Run `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --no-progress`, and `php artisan test --compact`. Regression coverage includes invalid/missing answers, stale-model completion/save, duplicate lifecycle requests, history preservation, shared filters, profile field restriction, CSV formula handling and localized rating labels. These tests do not simulate separate simultaneous database connections.

Final local result on PHP 8.4.26: Pint passed, PHPStan passed with zero errors, and all 55 tests / 360 assertions passed against PostgreSQL. Scribe generation completed without warnings; HTML, OpenAPI and both published/importable Postman collections were regenerated from the updated source documentation using the isolated test database.

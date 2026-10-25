# Dialog backend — Implementation plan

Agents should mark `[x]` only after the implementation and relevant tests are complete.

## Phase 0 — Bootstrap and standards

- [x] Create/verify Laravel 13 project on PHP 8.4.
- [x] Configure PostgreSQL.
- [x] Configure Redis connection, cache/queue as required.
- [x] Install/configure Laravel Sanctum.
- [x] Establish `/api/v1` route structure.
- [x] Create module directory convention.
- [x] Configure Pint.
- [x] Configure static analysis.
- [x] Add baseline CI workflow.
- [x] Add standard API error envelope.

## Phase 1 — Identity and organization

- [x] Implement user registration with email/password.
- [x] Implement login/logout/me.
- [x] Include the current user's organizations and membership roles in `/auth/me`, with an empty list for users without memberships and organization-isolation tests.
- [ ] Implement password reset if included by current product task. No reset endpoints/actions exist yet.
- [x] Add platform locale (`uz`, `ru`, `en`).
- [x] Add platform role (`user`, `admin`).
- [x] Create organizations.
- [x] Create organization membership model.
- [x] Business onboarding creates organization + owner membership atomically.
- [x] Separate user registration from organization onboarding; require a unique string INN for new organizations, preserve legacy records, and update frontend contracts and regression tests.
- [x] Implement organization membership authorization/policies.
- [x] Add tests for organization isolation and admin privilege.

## Phase 2 — Survey builder foundation

- [x] Create `surveys` migration/model.
- [x] Add survey status enum.
- [x] Add survey selected locales/default locale.
- [x] Store survey localized title/description/welcome/thank-you content as JSONB.
- [x] Validate selected locale set (`uz`, `ru`, `en`).
- [x] Require survey title for every selected locale on create.
- [x] Create `survey_pages`.
- [x] Create `survey_questions`.
- [x] Create `survey_question_options`.
- [x] Implement initial question types.
- [x] Implement question settings validation.
- [x] Implement page/question/option reorder operations.
- [x] Implement copy/delete operations where specified.
- [x] Prevent edits outside editable survey states.
- [x] Implement builder Resources returning translation maps.
- [x] Add builder feature tests.

## Phase 3 — Localization completeness and preview

- [x] Implement `SurveyTranslationValidator`.
- [x] Return structured missing-translation errors.
- [x] Implement preview using production validation/flow logic without real analytics records.
- [x] Implement `SurveyFlow` with linear navigation.
- [x] Implement `AnswerValidator` foundation.
- [x] Add tests for all selected-language validation.

## Phase 4 — Moderation and publishing

- [x] Create `survey_moderations`.
- [x] Implement `SurveyPublishValidator`.
- [x] Implement submit-for-review action.
- [x] Implement admin moderation list/detail.
- [x] Implement approve action.
- [x] Implement request-changes action with comment.
- [x] Implement allowed status transition guards.
- [x] Implement activate/publish behavior.
- [x] Ensure active structure is immutable.
- [x] Write important audit records.
- [x] Add moderation/transition tests.

## Phase 5 — Respondent participation

- [x] Create `participations`.
- [x] Add unique `(survey_id, user_id)` constraint.
- [x] Store participation locale.
- [x] Create `answers`.
- [x] Create `answer_options`.
- [x] Implement start/resume participation.
- [x] Return first page through `SurveyFlow`.
- [x] Implement save-page-answers endpoint/action.
- [x] Validate only applicable questions.
- [x] Persist page answers atomically.
- [x] Return backend-calculated `next` destination.
- [x] Implement complete action idempotently.
- [x] Block completed-answer editing.
- [x] Add ownership/security tests.
- [x] Add duplicate-submit tests.

## Phase 6 — Basic results and export

- [x] Implement participation list for business.
- [x] Implement participation detail/answers.
- [x] Implement started/completed counts.
- [x] Implement completion rate.
- [x] Implement single-choice distribution.
- [x] Implement multiple-choice distribution.
- [x] Implement rating distribution/average.
- [x] Implement text answer list.
- [x] Add locale/filter support required by UI.
- [x] Implement CSV export respecting filters.
- [ ] Queue export if it becomes heavy. Current CSV export is synchronous and loads participations in batches.

## Phase 7 — Hardening

- [x] Configure localhost/loopback frontend CORS, explicit production origins, credentialed preflight and API error-response regression tests.
- [x] Review indexes using real query patterns.
- [x] Add rate limiting where appropriate.
- [x] Review N+1 queries.
- [x] Add/verify audit coverage for critical operations.
- [x] Verify sensitive-data logging rules.
- [ ] Verify production queue worker configuration on the actual server (runbook exists).
- [ ] Verify scheduler configuration on the actual server (runbook exists).
- [x] Add backup/restore runbook.
- [x] Run full end-to-end acceptance flow.

## Documentation/code audit — 2026-10-19

Details and explicit architecture conflicts: `docs/AUDIT-DOCS-CODE.md`.

- [x] Enforce persisted-answer validation and transactional/idempotent completion.
- [x] Serialize duplicate starts and participation writes; prevent stale-model completed-answer updates.
- [x] Use typed `SurveyFlow` destinations in respondent and preview responses.
- [x] Validate answer payload types, distinct choice IDs and integer rating bounds.
- [x] Validate effective question settings and block empty/impossible pages before review.
- [x] Resolve rating labels and validate configured page/rating label translations.
- [x] Make submit/decision/publication retries safe and preserve surveys with history.
- [x] Share result filters with CSV and restrict business result user profile fields.
- [x] Add CSV BOM and formula neutralization.
- [x] Correct integration guides for actual auth, enums, payloads and response keys.
- [ ] Reconcile moderation-round storage with the event schema without losing existing history.
- [ ] Reconcile audit column/action naming and user-agent coverage with the architecture document.
- [ ] Complete remaining controller-to-Action/FormRequest and Query/Resource extraction.

## MVP conditional logic — approved 2026-10-19

- [x] Define the MVP rule/operator contract and update the previous deferred-scope decisions.
- [x] Add survey rule storage and participation visited-page history with a forward migration.
- [x] Add authorized read/atomic-replace builder endpoints with editable-state guards and audit coverage.
- [x] Validate survey-local references, type-specific operators, earlier-page visibility and forward-only navigation.
- [x] Implement AND/OR conditions, deterministic priorities and linear fallback in SurveyFlow.
- [x] Implement conditional visibility, page branching and early completion.
- [x] Validate only applicable questions and prevent submission of unvisited/skipped pages.
- [x] Remove newly hidden/skipped persisted answers after route-changing edits and preserve result/export consistency.
- [x] Implement actual-history Back with ownership, active-state and completion guards.
- [x] Use the same logic in stateless preview and revalidate rules before review/publication.
- [x] Add feature/operator regression tests.

## API acceptance audit — 2026-10-20

- [x] Exercise registration/login/logout with real Bearer tokens through multilingual builder, conditional preview, moderation/publication and respondent completion.
- [x] Cover all five question types, AND/OR, navigation priority, Back, resume, branch-change cleanup and results/export in the authenticated journey.
- [x] Fix normalized-email uniqueness, optional password confirmation, validated device names and duplicate/non-list reorder payloads with regression tests.
- [x] Check reached-page authorization before answer validation and preserve its stable flow error code.
- [x] Connect configured auth/API rate limits to routes and preserve HTTP retry headers in structured errors.

## Future phases — do not implement until explicitly approved

- [ ] Same-page reactive visibility, nested groups and additional advanced operators.
- [ ] Distinct screening/disqualification status and analytics (MVP early completion is implemented).
- [ ] Piping/carry-forward/randomization/scoring.
- [ ] Survey versioning.
- [ ] Public collectors/links/invitations/embed.
- [ ] Advanced audience targeting/quotas.
- [ ] MyID/KYC.
- [ ] Payments/rewards/payouts.
- [ ] Advanced question catalogue.
- [ ] Interview module.
- [ ] Usability-testing module.

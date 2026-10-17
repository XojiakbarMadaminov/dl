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
- [x] Implement password reset if included by current product task.
- [x] Add platform locale (`uz`, `ru`, `en`).
- [x] Add platform role (`user`, `admin`).
- [x] Create organizations.
- [x] Create organization membership model.
- [x] Business onboarding creates organization + owner membership atomically.
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
- [x] Queue export if it becomes heavy.

## Phase 7 — Hardening

- [x] Review indexes using real query patterns.
- [x] Add rate limiting where appropriate.
- [x] Review N+1 queries.
- [x] Add/verify audit coverage for critical operations.
- [x] Verify sensitive-data logging rules.
- [x] Verify production queue worker configuration.
- [x] Verify scheduler configuration.
- [x] Add backup/restore runbook.
- [x] Run full end-to-end acceptance flow.

## Future phases — do not implement until explicitly approved

- [ ] Conditional survey logic engine.
- [ ] AND/OR and advanced operators.
- [ ] Screening/disqualification.
- [ ] Piping/carry-forward/randomization/scoring.
- [ ] Survey versioning.
- [ ] Public collectors/links/invitations/embed.
- [ ] Advanced audience targeting/quotas.
- [ ] MyID/KYC.
- [ ] Payments/rewards/payouts.
- [ ] Advanced question catalogue.
- [ ] Interview module.
- [ ] Usability-testing module.

# Dialog — Project vision and MVP scope

## Product direction

Dialog is being built **survey-first**, while keeping the architecture capable of growing into a broader research platform later.

Current product direction:

- Build a strong survey workflow first.
- Do not build microservices.
- Do not introduce generic research abstractions unless a real feature needs them.
- Future research types (interview, usability testing, recordings, etc.) may become separate domains later.

For the current codebase, **Survey is the root product/domain concept**. There is no `studies` table in the MVP.

## Primary actors

### Business user

A user who belongs to an organization and can create/manage surveys according to the organization role.

### Respondent

A logged-in user who can participate in surveys available to them.

### Platform administrator / moderator

A privileged platform actor who reviews surveys, approves them, or requests changes. Platform admin rights are not self-service.

A single account can be both a respondent and an organization member. `business` and `respondent` are usage contexts, not mutually exclusive global user roles.

## MVP end-to-end outcome

The first complete product flow must support:

1. Register/login using email and password.
2. After registration, separate authenticated business onboarding creates an organization with a name and unique INN; the creator becomes owner. Registration itself creates only the user.
3. Business creates a draft survey.
4. Business chooses survey languages (`uz`, `ru`, `en`) and a default survey language.
5. Business fills required survey titles for every selected language.
6. Business creates pages, questions and options.
   Business can configure conditional visibility, forward page branching, early completion and AND/OR groups through builder APIs.
7. Business previews the survey.
8. Business submits the survey for moderation.
9. Admin approves the survey or returns it with a comment.
10. Approved survey becomes active/published.
11. Respondent logs in, starts a participation, answers page by page, resumes if interrupted, and completes once.
12. Business sees basic results and response details.
13. CSV export is supported in the results phase.

## Explicitly not in the MVP

- MyID / KYC integration.
- Payments, rewards, wallets, payouts.
- Microservices.
- Survey versioning.
- Advanced logic (piping, carry-forward, randomization, scoring, nested condition groups, distinct disqualification) and a visual frontend rule editor.
- Advanced audience targeting and quotas.
- Interview module.
- Usability-testing module.
- Audio/video recording workflows.
- Advanced question catalogue beyond the initially implemented types.

## Initial question types

The initial question set is:

- `single_choice`
- `multiple_choice`
- `short_text`
- `long_text`
- `rating`

## Source basis

The source plan originally deferred conditional logic. Product-owner decisions bring UZ/RU/EN localization and, on 2026-10-19, basic conditional logic into the MVP. The implemented rule contract and explicit advanced-feature boundaries are in `API-SURVEY-LOGIC.md`.

Source references:

- `Akalar muloqoti.pdf` — sections 1, 2, 3, 4, 5, 6, 7, 9.
- `Dialog_Full_BPMN.html` — P00–P09, P11, P15 and question catalogue.

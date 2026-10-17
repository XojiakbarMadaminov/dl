# Dialog — Modules and code style

## Proposed module layout

```text
app/
└── Modules/
    ├── Identity/
    ├── Organization/
    ├── Survey/
    ├── Participation/
    ├── Moderation/
    ├── Results/
    └── Platform/
        └── Audit/
```

A module creates only the subdirectories it actually needs.

Typical structure:

```text
Survey/
├── Models/
├── Actions/
├── Queries/
├── Services/
├── Enums/
├── Policies/
└── Http/
    ├── Controllers/
    │   ├── Business/
    │   ├── Respondent/
    │   └── Admin/
    ├── Requests/
    └── Resources/
```

`Admin` is not a domain module. Admin is an actor/interface. Moderation logic belongs to the Moderation domain.

## Laravel standard directories kept centrally

Keep these in Laravel's standard locations:

```text
database/migrations/
database/factories/
database/seeders/
routes/
```

Do not distribute migrations inside module packages unless a future decision explicitly changes this.

## Route layout

```text
routes/
├── api.php
└── api/
    └── v1/
        ├── auth.php
        ├── business.php
        ├── respondent.php
        └── admin.php
```

Public API namespace:

```text
/api/v1/auth/...
/api/v1/business/...
/api/v1/respondent/...
/api/v1/admin/...
```

## Coding rules

- Controllers are orchestration only.
- Use Form Requests for request validation/authorization where appropriate.
- Use Policies for resource authorization.
- Use PHP backed enums for important application states/types.
- Use Actions for business state changes and important commands.
- Use Query classes for complex/reused reads.
- Use API Resources for API serialization.
- Use transactions for multi-record state changes that must be atomic.
- Prefer Laravel-native features over custom frameworks/abstraction layers.
- Repository pattern is not required by default.
- Interfaces are introduced only when there are multiple implementations or a real boundary (for example external provider adapters later).
- Avoid static helper classes for domain behavior.
- Avoid service locators and hidden global state.
- Keep methods small enough to express one coherent operation, but do not split code into tiny classes without value.

## Naming examples

Good:

```text
CreateSurveyAction
UpdateQuestionAction
ReorderSurveyPagesAction
SubmitSurveyForReviewAction
ApproveSurveyAction
StartParticipationAction
SavePageAnswersAction
CompleteParticipationAction
SurveyFlow
SurveyPublishValidator
SurveyTranslationValidator
```

Avoid vague names:

```text
SurveyManager
CommonService
HelperService
ProcessDataService
Utils
```

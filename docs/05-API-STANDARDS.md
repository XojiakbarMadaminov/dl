# Dialog — REST API standards

## Base prefix

```text
/api/v1
```

## Route groups

```text
/api/v1/auth/...
/api/v1/business/...
/api/v1/respondent/...
/api/v1/admin/...
```

The prefixes represent usage contexts/actors, not separate backends.

## One backend for all clients

The same backend serves:

- business web;
- admin web;
- respondent web;
- future mobile apps.

Do not create separate mobile business logic or separate mobile services.

## Business examples

```text
GET    /api/v1/business/organizations/{organization}/surveys
POST   /api/v1/business/organizations/{organization}/surveys
GET    /api/v1/business/organizations/{organization}/surveys/{survey}
PATCH  /api/v1/business/organizations/{organization}/surveys/{survey}
POST   /api/v1/business/organizations/{organization}/surveys/{survey}/submit-review
```

Builder resources may use nested endpoints such as:

```text
POST   /api/v1/business/.../surveys/{survey}/pages
POST   /api/v1/business/.../pages/{page}/questions
PATCH  /api/v1/business/.../questions/{question}
POST   /api/v1/business/.../questions/{question}/options
PUT    /api/v1/business/.../surveys/{survey}/pages/reorder
PUT    /api/v1/business/.../pages/{page}/questions/reorder
```

Avoid sending the entire survey structure for every tiny edit.

## Respondent examples

```text
GET    /api/v1/respondent/surveys/{survey}?lang=ru
POST   /api/v1/respondent/surveys/{survey}/participation
GET    /api/v1/respondent/participations/{participation}
PUT    /api/v1/respondent/participations/{participation}/pages/{page}
POST   /api/v1/respondent/participations/{participation}/complete
```

## Admin examples

```text
GET    /api/v1/admin/surveys
GET    /api/v1/admin/surveys/{survey}
POST   /api/v1/admin/surveys/{survey}/approve
POST   /api/v1/admin/surveys/{survey}/request-changes
```

## Builder vs respondent localization contract

Builder endpoints return translations as maps:

```json
{
  "title": {
    "uz": "Mijozlar fikri",
    "ru": "Отзывы клиентов",
    "en": "Customer feedback"
  }
}
```

Respondent endpoints return content resolved to one locale:

```json
{
  "locale": "ru",
  "title": "Отзывы клиентов"
}
```

Respondent clients should not receive all translation maps unless a specific UI requires language switching data.

## SurveyFlow response

The frontend must not calculate the next destination itself.

Example:

```json
{
  "next": {
    "type": "page",
    "id": 12
  }
}
```

Terminal examples:

```json
{
  "next": {
    "type": "completed"
  }
}
```

Future:

```json
{
  "next": {
    "type": "disqualified"
  }
}
```

## Error format

Use a stable structured error envelope:

```json
{
  "error": {
    "code": "SURVEY_NOT_EDITABLE",
    "message": "Active survey cannot be edited.",
    "fields": {}
  }
}
```

Validation example:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed.",
    "fields": {
      "title.uz": ["The Uzbek title is required."]
    }
  }
}
```

Frontend behavior should depend on stable `code` values, not parsing message text.

## Suggested domain error codes

- `VALIDATION_ERROR`
- `FORBIDDEN`
- `SURVEY_NOT_EDITABLE`
- `SURVEY_NOT_ACTIVE`
- `UNSUPPORTED_SURVEY_LOCALE`
- `PARTICIPATION_ALREADY_COMPLETED`
- `PARTICIPATION_NOT_FOUND`
- `REQUIRED_ANSWER_MISSING`
- `INVALID_ANSWER`
- `TRANSLATION_INCOMPLETE`

## Idempotency expectations

At minimum, these operations must be safe against duplicate client submission:

- participation complete;
- publish/activate transition;
- other irreversible state transitions.

A double click must not create two completed participations or duplicate side effects.

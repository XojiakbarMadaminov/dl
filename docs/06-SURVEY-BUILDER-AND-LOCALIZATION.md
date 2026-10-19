# Dialog — Survey builder and localization

## Two separate localization domains

Never mix:

```text
Platform UI localization
!=
Survey content localization
```

### Platform UI

The product UI supports at least:

- Uzbek (`uz`)
- Russian (`ru`)
- English (`en`)

Frontend stores platform translation dictionaries (for example i18n JSON files). API/domain logic should return stable codes where possible instead of forcing the frontend to translate backend prose.

### Survey content

Survey-authored content is stored in PostgreSQL and belongs to the survey.

## Survey creation flow

The business user chooses survey languages before filling survey titles.

Example UI:

```text
Languages
[x] Uzbek
[x] Russian
[ ] English

Default language
Uzbek
```

Rules:

- at least one survey locale is required;
- default locale must be one of the selected locales;
- supported MVP locale codes are only `uz`, `ru`, `en`;
- every selected locale requires a survey title at creation;
- unselected locale titles are not required.

Example request:

```json
{
  "locales": ["uz", "ru"],
  "default_locale": "uz",
  "title": {
    "uz": "Xizmat sifati",
    "ru": "Качество обслуживания"
  }
}
```

## Translation storage

Use JSONB maps for localized author content.

Survey:

```json
{
  "uz": "Mijozlar fikri",
  "ru": "Отзывы клиентов",
  "en": "Customer feedback"
}
```

Question:

```json
{
  "uz": "Xizmatdan mamnunmisiz?",
  "ru": "Вы довольны обслуживанием?",
  "en": "Are you satisfied with the service?"
}
```

Option:

```json
{
  "uz": "Ha",
  "ru": "Да",
  "en": "Yes"
}
```

Do not create separate translation tables in the MVP unless a concrete limitation appears.

## Draft translation completeness

To keep authoring practical:

- survey titles for selected locales are required immediately at survey creation;
- during draft editing, question/option translations may temporarily be incomplete;
- `submit for moderation` must run a complete translation validation for every selected locale.

Before moderation submission, all selected locales must have the required respondent-facing content for:

- survey title;
- required survey intro/welcome content if enabled;
- question titles;
- option labels;
- required page/content labels if those fields are used;
- completion/thank-you content if configured as required by product UX.

A locale with incomplete published content must not be offered to respondents.

Page titles and rating endpoint labels (`settings.min_label`, `settings.max_label`) are optional, but when a non-empty translation map is configured it must cover every selected locale. Their respondent/preview payloads are resolved strings, not maps. Translation map values must be strings or null, never nested objects.

## Translation validator

Implement a dedicated domain validator such as:

```text
SurveyTranslationValidator
```

It must return structured missing-field information suitable for both frontend display and moderation-submission blocking.

## Question representation

Builder response example:

```json
{
  "id": 42,
  "type": "multiple_choice",
  "title": {
    "uz": "Qaysi xizmatlardan foydalandingiz?",
    "ru": "Какими услугами вы пользовались?",
    "en": "Which services did you use?"
  },
  "description": {},
  "required": true,
  "position": 2,
  "settings": {
    "min_selections": 1,
    "max_selections": 3,
    "randomize_options": false
  },
  "options": []
}
```

Frontend should implement a `QuestionRenderer` keyed by `type`.

## Builder persistence strategy

Use resource-level CRUD rather than sending the full survey structure on each edit.

Support:

- update survey metadata/translations;
- create/update/delete/reorder pages;
- create/update/copy/delete/reorder questions;
- create/update/delete/reorder options;
- autosave-friendly small requests;
- preview.
- read/replace conditional rules using `GET/PUT /surveys/{survey}/logic`; see `API-SURVEY-LOGIC.md`.

## Publish/editability rule

Current MVP:

- `draft` and `changes_requested` content is editable;
- `pending_review` is locked for review;
- `approved`/`active` structure is not edited directly;
- `active` survey structure is immutable;
- no survey versioning yet.

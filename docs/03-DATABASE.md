# Dialog — Database design

## Key strategy

Use standard Laravel/PostgreSQL `BIGINT` primary keys and `BIGINT` foreign keys.

```php
$table->id();
$table->foreignId('survey_id')->constrained();
```

Do not make UUID the default identifier strategy.

If a resource needs a non-sequential public/shareable identifier, add a dedicated random token/public identifier only to that resource (for example future survey collector links).

## Core tables

### users

```text
id                  BIGINT PK
name                varchar
email               varchar unique
email_verified_at   timestamp nullable
password            varchar
locale              varchar default 'uz'
platform_role       varchar default 'user'
status              varchar default 'active'
created_at
updated_at
```

Notes:

- `locale` is platform UI locale (`uz`, `ru`, `en`).
- `platform_role` represents platform privilege (`user`, `admin`), not business/respondent context.

### organizations

```text
id                  BIGINT PK
name                varchar
created_by          BIGINT FK -> users.id
status              varchar default 'active'
created_at
updated_at
```

### organization_members

```text
organization_id     BIGINT FK
user_id             BIGINT FK
role                varchar
joined_at           timestamp
```

Constraint:

```text
UNIQUE (organization_id, user_id)
```

Organization roles:

- `owner`
- `manager`
- `researcher`
- `viewer`

### surveys

```text
id                  BIGINT PK
organization_id     BIGINT FK
created_by          BIGINT FK -> users.id
status              varchar

default_locale      varchar
locales             jsonb

title               jsonb
description         jsonb nullable
welcome_message     jsonb nullable
thank_you_message   jsonb nullable
internal_note       text nullable

published_at        timestamp nullable
closed_at           timestamp nullable
archived_at         timestamp nullable

created_at
updated_at
```

Example:

```json
{
  "locales": ["uz", "ru", "en"],
  "title": {
    "uz": "Mijozlar fikri",
    "ru": "Отзывы клиентов",
    "en": "Customer feedback"
  }
}
```

`internal_note` is internal business content and is not survey-localized in the MVP.

### survey_pages

```text
id                  BIGINT PK
survey_id           BIGINT FK
position            integer
title               jsonb nullable
description         jsonb nullable
created_at
updated_at
```

Constraint:

```text
UNIQUE (survey_id, position)
```

### survey_questions

```text
id                  BIGINT PK
page_id             BIGINT FK

type                varchar
title               jsonb
description         jsonb nullable
is_required         boolean default false
position            integer
settings            jsonb default '{}'

created_at
updated_at
```

Initial types:

- `single_choice`
- `multiple_choice`
- `short_text`
- `long_text`
- `rating`

Examples for `settings`:

```json
{
  "min_selections": 1,
  "max_selections": 3,
  "randomize_options": false
}
```

```json
{
  "min": 1,
  "max": 5
}
```

Principle:

- common relational data -> columns;
- question-type-specific configuration -> `settings` JSONB.

### survey_question_options

```text
id                  BIGINT PK
question_id         BIGINT FK
label               jsonb
position            integer
created_at
updated_at
```

Keep option identity stable across locales. Locale changes must not create a different semantic option.

### survey_moderations

```text
id                  BIGINT PK
survey_id           BIGINT FK
submitted_by        BIGINT FK -> users.id
submitted_at        timestamp
status              varchar
reviewed_by         BIGINT nullable FK -> users.id
reviewed_at         timestamp nullable
comment             text nullable
created_at
updated_at
```

Moderation statuses:

- `pending`
- `approved`
- `changes_requested`

Every resubmission creates a new moderation round so history is preserved.

### survey_logic_rules

MVP decision updated on 2026-10-19. One rule row stores:

```text
id                  BIGINT PK
survey_id           BIGINT FK -> surveys.id, cascade delete
action              varchar (show_question, skip_to_page, complete)
source_page_id      BIGINT nullable
target_page_id      BIGINT nullable
target_question_id  BIGINT nullable
priority            integer
match               varchar (all, any)
conditions          jsonb
created_at
updated_at
```

Index `(survey_id, priority)`. Conditions are a bounded JSONB list, not a separate table. Page/question IDs intentionally retain broken references after draft edits (no FK/cascade on these fields); application validation rejects cross-survey/deleted/forward-condition/backward-target references at rule writes, preview, review submission and publication. See `API-SURVEY-LOGIC.md` for the complete schema and semantics.

### participations

```text
id                  BIGINT PK
survey_id           BIGINT FK
user_id             BIGINT FK
locale              varchar
status              varchar
current_page_id     BIGINT nullable FK -> survey_pages.id
page_history        jsonb default []
started_at          timestamp
completed_at        timestamp nullable
last_activity_at    timestamp
created_at
updated_at
```

Constraint:

```text
UNIQUE (survey_id, user_id)
```

MVP statuses:

- `in_progress`
- `completed`

MVP early finish uses `completed`; a distinct screening status such as `disqualified` remains deferred. `page_history` records visited pages for Back; legacy in-progress rows derive history from the compatible current path until the next write.

### answers

```text
id                  BIGINT PK
participation_id    BIGINT FK
question_id         BIGINT FK
text_value          text nullable
number_value        numeric nullable
json_value          jsonb nullable
created_at
updated_at
```

Constraint:

```text
UNIQUE (participation_id, question_id)
```

Current mapping:

- short/long text -> `text_value`
- rating -> `number_value`
- choice questions -> answer row + `answer_options`
- `json_value` is reserved for genuinely complex future answer types; do not use it for all answers.

### answer_options

```text
answer_id           BIGINT FK
option_id           BIGINT FK
```

Constraint:

```text
UNIQUE (answer_id, option_id)
```

### audit_logs

```text
id                  BIGINT PK
actor_id            BIGINT nullable FK -> users.id
organization_id     BIGINT nullable FK -> organizations.id
action              varchar
subject_type        varchar
subject_id          BIGINT
metadata            jsonb nullable
ip_address          inet/varchar nullable
user_agent          text nullable
created_at
```

Audit only important state-changing operations in the MVP, such as:

- `survey.created`
- `survey.submitted`
- `survey.changes_requested`
- `survey.approved`
- `survey.activated`
- `survey.paused`
- `survey.closed`
- `survey.archived`

## Survey status lifecycle

Application enum:

```text
draft
pending_review
changes_requested
approved
active
paused
closed
archived
```

Editable content states:

- `draft`
- `changes_requested`

Question/page/option structure is immutable once active in the current MVP.

## Delete strategy

Do not add `SoftDeletes` to every model.

Recommended MVP behavior:

- draft survey with no participation: hard delete may be allowed;
- survey with participation/history: do not hard delete; archive it;
- moderation/participation/answers are historical records and should not be casually deleted.

## Database integrity

Use database constraints, not only Laravel validation:

- foreign keys;
- unique constraints;
- useful check constraints where appropriate;
- indexes for common organization/status/survey/user queries.

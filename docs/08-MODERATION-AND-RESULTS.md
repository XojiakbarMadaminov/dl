# Dialog — Moderation and basic results

## Survey lifecycle

Application survey statuses:

```text
draft
  -> pending_review
       -> changes_requested -> pending_review
       -> approved -> active
active <-> paused
active/paused -> closed
closed -> archived
```

Exact transition rules belong in Actions/domain guards, not only in frontend button visibility.

## Submit for moderation

Before a survey can enter `pending_review`, backend validation must verify at least:

- survey title exists for every selected locale;
- at least one answerable question exists;
- question configuration is complete;
- choice questions have valid options;
- min/max settings are consistent;
- no empty invalid pages remain;
- all required selected-locale translations are complete;
- logic rules have valid survey-local references, type-specific operators and forward-only navigation targets; revalidate at review submission and publication.

Use dedicated validators, for example:

```text
SurveyPublishValidator
SurveyTranslationValidator
```

## Admin decisions

Admin can:

- approve;
- request changes with a comment.

A business user cannot approve their own survey through business permissions.

Every moderation round is stored in `survey_moderations` so review history is not lost.

## Active survey immutability

Current MVP rule:

- active survey question/page/option structure cannot be edited;
- versioning is not implemented;
- if a changed structure is needed, the MVP can use a copy/new survey flow until versioning is deliberately introduced later.

## Basic results MVP

Business results should include:

### Participation list

- respondent platform identity marker/nickname as permitted by product privacy rules;
- start time;
- completion time;
- status;
- duration;
- locale;
- answer detail action.

Do not expose future KYC documents/internal risk notes to business users.

### Summary metrics

- started participations;
- completed participations;
- completion rate;
- question distributions.

Examples:

- single choice: count and percentage per option;
- multiple choice: count/percentage per option; totals may exceed 100% because one respondent can select multiple options;
- rating: distribution and average;
- text: answer list/search later as needed.

### Export

MVP export:

- CSV first;
- export must respect the same filters applied to the visible result set.

Excel/XLSX can be added later.

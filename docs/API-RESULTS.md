# Dialog results and CSV API

All routes are under `/api/v1/business/organizations/{organization}/surveys/{survey}` and require authenticated organization read permission. Organization/survey/participation/question ownership is checked by the backend.

## Shared filters

List, summary, question aggregate and CSV export use the same participation query:

- `status=in_progress|completed`: omit to include both statuses.
- `participation_locale=uz|ru|en`: filter on the participation's stored locale.
- `locale=uz|ru|en`: select the display language for question/option labels; this is **not** a participation filter. Defaults to the survey default, with default-language fallback for missing labels.

Invalid filter values return HTTP 422 `VALIDATION_ERROR`. CSV now includes the same records as the list by default; pass `status=completed` for completed-only exports.

## Summary

`GET .../results/summary`

```json
{"survey_id":1,"started_count":120,"completed_count":96,"in_progress_count":24,"completion_rate":80}
```

Counts apply to the filtered set. Completion rate is `round(completed_count / started_count * 100, 1)`, or zero when there are no starts.

## Question aggregates

`GET .../results/questions/{question}`

Choice response:

```json
{
  "question_id":101,
  "type":"single_choice",
  "title":"Savol",
  "total_responses":4,
  "distribution":[
    {"option_id":501,"label":"Ha","count":3,"percentage":75},
    {"option_id":502,"label":"Yo‘q","count":1,"percentage":25}
  ]
}
```

- Single/multiple choice: `distribution` contains each option's count and percentage of answers to that question. Multiple-choice percentages may sum to more than 100%.
- Rating: `average` (rounded to two decimals), and `distribution` entries `{"rating":5,"count":3,"percentage":75}` for each configured rating value.
- Text: `sample_answers` contains up to 50 most recent non-null text answers.
- Every type returns `question_id`, `type`, `title`, `total_responses`. In-progress answers count unless excluded by the status filter.

## Participation list and detail

`GET .../participations?status=completed&page=1`

Returns Laravel pagination fields including `data`, `current_page`, `per_page` (20), and `total`. Records include participation identifiers, locale, status and timestamps. The nested `user` is restricted to `id`, `name`, `email`; no password, token, platform role or account status is exposed.

`GET .../participations/{participation}`

Returns a `participation` object with identifiers/status/locale/timestamps, basic `user`, and `answers`. An answer has `question_id`, localized `question_title`, `type`, `text_value`, `number_value`, `selected_options` (`id`, localized `label`). Choice answers use selected options; `text_value` is null.

## CSV

`GET .../results/export?status=completed&participation_locale=ru&locale=uz`

- Content-Type: `text/csv; charset=UTF-8`.
- Attachment filename: `survey_{id}_results_{Ymd_His}.csv`.
- UTF-8 BOM, followed by columns `Participation ID`, `User ID`, `Locale`, `Started At`, `Completed At`, then `Q{id}: {localized question title}` columns.
- Choice cells contain comma-separated localized option labels; text and rating cells contain their values.
- Spreadsheet-formula prefixes in cells are neutralized with an apostrophe.
- Participations are loaded in batches of 100; CSV generation is still synchronous. There is no queued export job/download API yet.

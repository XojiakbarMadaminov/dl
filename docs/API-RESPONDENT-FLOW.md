# Dialog respondent participation API

All endpoints require Sanctum Bearer authentication. Navigation is owned by the backend, not calculated by the client.

## Start or resume

`POST /api/v1/respondent/surveys/{survey}/participate`

Optional body: `{"locale":"ru"}`. Omitting the locale uses the survey default for a new participation. A resumed participation retains its original locale.

A new or in-progress participation returns HTTP 201:

```json
{
  "participation_id": 14,
  "status": "in_progress",
  "locale": "ru",
  "survey": {"id": 1, "title": "Опрос", "welcome_message": null},
  "current_step": {
    "page": {"id": 10, "position": 1, "title": null, "description": null, "questions": []},
    "progress": {"current_step": 1, "total_steps": 2, "percent": 50, "is_first_page": true, "is_last_page": false},
    "next": {"type": "page", "id": 11}
  }
}
```

A published physical page must contain a question, but conditional visibility can result in an empty visible questions array. Save that page with `{"answers":{}}` to continue. All respondent-facing content, including rating endpoint labels, is resolved to strings rather than translation maps.

Starting again after completion returns HTTP 200 with the existing `participation_id`, `status: "completed"`, `locale`, `completed: true` and `thank_you_message`. It does not create another record. Surveys must be active to start/resume through this endpoint.

## Save page answers

`PUT /api/v1/respondent/participations/{participation}/pages/{page}`

`answers` is an object keyed by question ID. Values are direct scalars/arrays, **not** objects such as `{"option_id":501}`.

| Type | Value | Example |
| --- | --- | --- |
| single_choice | One integer option ID | `{"101":501}` |
| multiple_choice | Distinct integer option IDs | `{"103":[503,504]}` |
| rating | Integer inside configured bounds | `{"104":5}` |
| short_text / long_text | String | `{"105":"Ajoyib xizmat"}` |

```json
{"answers":{"101":501,"102":"Ajoyib xizmat"}}
```

Each save replaces this page's answer set atomically. Omitted/empty optional answers clear previously saved values. Required answers, option membership, distinct selections, min/max selections and text limits are validated. Invalid answers return HTTP 422 `INVALID_ANSWER` with `answers.{questionId}` fields; malformed envelopes return `VALIDATION_ERROR`.

Non-final page response:

```json
{
  "next": {"type":"page","id":11},
  "can_complete": false,
  "next_step": {
    "page": {"id":11,"position":2,"title":null,"description":null,"questions":[]},
    "progress": {"current_step":2,"total_steps":2,"percent":100,"is_first_page":false,"is_last_page":true},
    "next": {"type":"completed"}
  }
}
```

Final page response contains `next: {"type":"completed"}`, `can_complete: true`, and `current_step` for that page. This destination indicates that the client can request completion; it does not finalize participation by itself.

**Contract change:** old integer/string `next` values are replaced by the typed destination above, consistently for preview and respondent flows.

## Resume saved work

`GET /api/v1/respondent/participations/{participation}`

An in-progress response includes `participation_id`, `status`, `locale`, `current_step`, and `answers` keyed by question ID. Each saved answer contains `text_value`, `number_value`, and `option_ids`. A completed participation returns `completed: true` without a current page.

## Complete

`POST /api/v1/respondent/participations/{participation}/complete`

```json
{"completed":true,"status":"completed","thank_you_message":"Rahmat!"}
```

The survey must still be active and persisted answers must pass the same validation as a page save, including all required questions on the applicable path. The participation must reach that path's final page. Repeating completion returns HTTP 200 without changing the completion timestamp. Completed answers cannot be edited. Ownership is checked on every participation endpoint; access to someone else's participation returns HTTP 403.

## Conditional flow and Back

MVP supports visibility based on earlier pages, forward jumps, AND/OR and early completion without changing typed `next` destinations. Navigation returned by a read is provisional until the current answers are saved. Progress uses physical page counts, not predicted branch length. Re-saving an earlier visited page recalculates the path and removes newly inapplicable answers from results.

`POST /api/v1/respondent/participations/{participation}/back` returns the GET participation envelope for the actually visited previous page, with saved answers. First-page Back is a no-op; completed/inactive sessions cannot navigate Back. Skipped/unvisited pages cannot be submitted directly. See `API-SURVEY-LOGIC.md` for the full contract.

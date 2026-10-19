# Dialog MVP survey logic API

Scope decision: on 2026-10-19 the product owner moved basic conditional logic into the MVP. Existing linear surveys remain compatible. All endpoints use Sanctum Bearer authentication under `/api/v1`.

## Builder endpoints

Base: `/api/v1/business/organizations/{organization}/surveys/{survey}`.

- `GET /logic` returns `{"rules":[...]}` ordered by priority, then ID. Organization viewers may read.
- `PUT /logic` atomically replaces the entire rule set. Requires the organization's `editSurvey` permission and survey state `draft` or `changes_requested`. Send `{"rules":[]}` to clear it. Rule IDs are regenerated; clients must not treat them as stable across replacements.
- Pending review, approved, active, paused, closed and archived surveys cannot change rules.
- Maximum 100 rules, maximum 20 conditions per rule. Replacement and its `survey.logic_replaced` audit event are transactional. The audit records rule count, not condition text.

Create pages/questions/options first, then reference their numeric IDs:

```json
{
  "rules": [
    {
      "action": "show_question",
      "target_question_id": 202,
      "priority": 0,
      "match": "all",
      "conditions": [
        {"question_id": 101, "operator": "equals", "value": 501}
      ]
    },
    {
      "action": "skip_to_page",
      "source_page_id": 10,
      "target_page_id": 12,
      "priority": 10,
      "match": "any",
      "conditions": [
        {"question_id": 101, "operator": "equals", "value": 502}
      ]
    }
  ]
}
```

In this example question 101 belongs to page 10, question 202 to a later page 11, and options 501/502 belong to question 101. Selecting 501 shows question 202 on page 11. Selecting 502 skips page 11 and continues at page 12.

## Actions and timing

| Action | Required fields | Meaning |
| --- | --- | --- |
| `show_question` | `target_question_id` | Show the question only when the condition group matches; otherwise hide it. One visibility rule per question. No source/target page fields. |
| `skip_to_page` | `source_page_id`, `target_page_id` | After validating/saving the source page, continue at the target page. No question target. |
| `complete` | `source_page_id` | After validating/saving the source page, offer early completion. No targets. |

Conditions for visibility may reference **earlier pages only**. Same-page dynamic show/hide is not part of this contract: place dependent questions on a later page. Navigation conditions may reference earlier pages or the source page itself, never future pages. All references must belong to this survey.

Navigation targets must be strictly later in `(position, id)` order. Backward jumps are rejected, preventing loops by construction. At a source page the first matching navigation rule wins: smaller `priority` first, then smaller ID (input order for ties in a replacement). If nothing matches, continue to the next page in order, or complete at the end. Visibility rules do not compete with navigation rules.

`match: "all"` means AND; `match: "any"` means OR. Flat condition groups only; nested boolean trees are deferred.

## Operators and values

| Question type | Operators | Value |
| --- | --- | --- |
| `single_choice` | `equals`, `not_equals` | Integer option ID belonging to that question |
| `multiple_choice` | `contains`, `not_contains` | One integer option ID belonging to that question |
| `rating` | `equals`, `not_equals`, `greater_than`, `less_than` | Integer within configured rating bounds |
| `short_text`, `long_text` | `equals`, `not_equals`, `contains`, `not_contains` | Nonblank text within the question length limit; literal, case-sensitive comparison, no regex |
| Every type | `answered`, `not_answered` | Omit `value` or use null |

Missing/blank answers do not match comparison operators, including negative comparisons. Use `not_answered` explicitly. Hidden/skipped answers are treated as missing. AND/OR combines the condition outcomes.

Malformed payloads return 422 `VALIDATION_ERROR`. Invalid references, operators, values, duplicate visibility targets or backward/self jumps return 422 `INVALID_SURVEY_LOGIC` with `rules.{index}` / condition field errors.

## Participation and navigation

Existing start, read, page save and complete endpoints keep their typed destinations:

```json
{"next":{"type":"page","id":12},"can_complete":false,"next_step":{"page":{"id":12}}}
```

```json
{"next":{"type":"completed"},"can_complete":true,"current_step":{"page":{"id":10}}}
```

These snippets show routing fields only; normal localized question payloads and progress remain present. The frontend must save the page and use the resulting destination. `next` on a read/initial preview is provisional until the current page's answers are submitted.

- Only visible questions appear in respondent payloads; hidden required questions do not block saving/completion. Hidden/unknown answer keys are ignored, not persisted.
- A page may contain zero visible questions. Save it with `{"answers":{}}` to continue. Empty physical pages are still rejected before review.
- Only the current page or an earlier page in the current visited history can be saved. Unvisited/skipped pages return 422 `INVALID_PAGE_FLOW`.
- Re-saving an earlier visited page truncates forward history and recalculates the route. Now-hidden/skipped answers and choice pivots are deleted in the same transaction, so they cannot leak into results/CSV. Still-applicable answers are retained.
- Completion revalidates only the applicable path's questions and requires reaching its final page. Early completion is a normal `completed` participation, not a distinct screening/disqualification status. It still requires the explicit complete request and is idempotent.
- Progress uses physical page ordinal/count (not a prediction of answered branch length). `is_last_page` reflects the currently calculated terminal destination.

`POST /api/v1/respondent/participations/{participation}/back` returns the same envelope as the current-participation GET, including saved answers. It returns to the actually visited previous page, not `position - 1`. First-page Back is a no-op. Ownership, active-survey and completed-participation guards apply. Existing in-progress sessions without history derive a compatible initial history from their current linear position; completed sessions are unchanged.

## Stateless preview

`POST {base}/preview/pages/{page}/validate` accepts:

```json
{
  "locale": "uz",
  "previous_answers": {"101": 502},
  "answers": {"303": 5}
}
```

`previous_answers` uses the same direct scalar/array format as normal page answers and belongs to earlier applicable pages. The action validates earlier pages and the current page, applies the same visibility/navigation engine, and returns `valid` plus `current_step`. No participation, answer or analytics records are created. Keep accumulated preview answers and visited-page history in frontend memory; restart or prune them after editing earlier answers. Initial preview still uses GET `/preview`.

## Publish validation and editing references

Rules are revalidated on replacement, preview, review submission and publication. Deleting options/questions/pages or reordering/changing question types in a draft may invalidate rules; repair/remove those rules before preview/review/publication. No automatic deletion silently disables a rule.

Storage uses one `survey_logic_rules` table with JSONB conditions and the survey cascade FK. Source/target IDs deliberately have no database FK: they retain broken references after draft edits so validation can report them instead of silently changing routing. `participations.page_history` is a JSONB list of visited page IDs. No survey versions or separate condition table are introduced.

## Deferred

Distinct `disqualified` status/analytics, same-page reactive visibility, nested groups, piping, carry-forward, randomization, scoring and a visual frontend rule editor are not implemented by these backend endpoints.

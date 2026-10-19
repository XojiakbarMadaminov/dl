# Dialog Moderation & Survey Lifecycle

This document defines the moderation workflow, status transitions, role authorizations, and audit tracking across all stages of a survey.

---

## 1. Complete Lifecycle State Machine

```text
draft -> pending_review -> approved -> active
             |                        |  ^
             v                        v  |
       changes_requested           paused
             |
             +-> pending_review

active / paused -> closed -> archived
```

---

## 2. Transition Rules & Authorization Matrix

| Transition | Action Endpoint | Required Role | Pre-condition | Post-condition |
|:---|:---|:---|:---|:---|
| **Submit Review** | `POST /business/.../submit-review` | Business `owner`/`manager`/`researcher` | `draft` or `changes_requested`. Survey must have ≥1 page with questions and complete translations. | Status becomes `pending_review`. Content locked. |
| **Approve** | `POST /admin/.../approve` | Platform `admin` | `pending_review` | Status becomes `approved`. Ready to publish. |
| **Request Changes**| `POST /admin/.../request-changes` | Platform `admin` | `pending_review`. Mandatory reviewer comment. | Status becomes `changes_requested`. Content unlocked for editing. |
| **Publish** | `POST /business/.../publish` | Business `owner`/`manager`/`researcher` | `approved` | Status becomes `active`. Responses accepted. Immutability locked. |
| **Pause** | `POST /business/.../pause` | Business `owner`/`manager`/`researcher` | `active` | Status becomes `paused`. New respondents blocked. |
| **Resume** | `POST /business/.../resume` | Business `owner`/`manager`/`researcher` | `paused` | Status becomes `active`. Responses accepted. |
| **Close** | `POST /business/.../close` | Business `owner`/`manager`/`researcher` | `active` or `paused` | Status becomes `closed`. Permanently closed to responses. |
| **Archive** | `POST /business/.../archive` | Business `owner`/`manager`/`researcher` | `closed` | Status becomes `archived`. Read-only historic state. |

---

## 3. Immutability Invariant

> [!WARNING]
> Once a survey enters `approved`, `active`, `paused`, `closed`, or `archived` status, its structural content (**pages, questions, options, settings, and locales**) is permanently **immutable**.
> Any update attempt on these entities will be rejected with HTTP 422:
> `{"error": {"code": "SURVEY_NOT_EDITABLE", "message": "Survey cannot be modified in its current status: active."}}`

---

## 4. Admin Moderation Endpoints

### 1. View Moderation Queue
Endpoint: `GET /api/v1/admin/moderation/surveys?status=pending_review`

### 2. Review Survey Hierarchy
Endpoint: `GET /api/v1/admin/moderation/surveys/{survey}`
Returns survey, organization details, creator information, and all nested pages and questions.

### 3. Approve Survey
Endpoint: `POST /api/v1/admin/moderation/surveys/{survey}/approve`
Response:
```json
{
  "survey": {
    "id": 1,
    "status": "approved"
  },
  "message": "Survey approved successfully."
}
```

### 4. Request Changes
Endpoint: `POST /api/v1/admin/moderation/surveys/{survey}/request-changes`
Request:
```json
{
  "comment": "Iltimos, ruscha tarjimadagi xatoliklarni to'g'rilang va 2-savol variantlarini kengaytiring."
}
```
Response:
```json
{
  "survey": {
    "id": 1,
    "status": "changes_requested"
  },
  "message": "Changes requested successfully."
}
```

## Duplicate requests and deletion

Submit/approve/request-changes/publish lock the survey row inside a transaction and return without repeating moderation/audit records if already at the requested target state. Publication retries preserve `published_at`.

Hard deletion is restricted to drafts with no moderation or participation history. A survey returned for changes cannot be deleted along with its review history. The moderation database currently stores events, not the round schema in the architecture document; see `AUDIT-DOCS-CODE.md`.

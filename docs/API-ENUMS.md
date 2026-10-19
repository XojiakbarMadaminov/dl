# Dialog API enum reference

Values below reflect the current PHP backed enums, not future capabilities.

| Enum | Values |
| --- | --- |
| PlatformLocale | `uz`, `ru`, `en` |
| PlatformRole | `user`, `admin` |
| UserStatus | `active`, `inactive` |
| OrganizationStatus | `active`, `suspended` |
| OrganizationRole | `owner`, `manager`, `researcher`, `viewer` |
| ParticipationStatus | `in_progress`, `completed` |
| QuestionType | `single_choice`, `multiple_choice`, `short_text`, `long_text`, `rating` |
| ModerationDecision (current event storage) | `submitted`, `approved`, `changes_requested`, `rejected` |

`rejected` exists in the enum but no MVP reject endpoint is exposed. `editor`, `analyst`, and `abandoned` are not valid roles/statuses. The documented moderation-round target differs from current event storage; see `AUDIT-DOCS-CODE.md`.

## Organization permissions

- Owner and manager: manage organization and memberships, edit surveys, view results.
- Researcher: edit surveys and view results, but cannot manage the organization.
- Viewer: read organization/surveys/results; cannot mutate surveys.
- Platform admin: moderation access and policy-level organization read access, without organization edit permission by default.

## Survey lifecycle

```text
draft -> pending_review -> approved -> active
             |                        |  ^
             v                        v  |
       changes_requested           paused
             |                        |
             +-> pending_review       +-> closed -> archived
                                      ^
active -------------------------------+
```

Only `draft` and `changes_requested` content is editable. Paused and closed surveys reject both new answers and first-time completion. No revert-to-draft endpoint exists. Duplicate submit/approve/request-changes/publish requests at their target state return without duplicate side effects.

## Question configuration

- Choice questions require at least two options before submission for review.
- Multiple choice uses `min_selections` / `max_selections`, not `min_choices` / `max_choices`.
- Rating uses integer `min` / `max` (defaults 1 and 5); effective maximum must exceed minimum.
- Text uses configurable `max_length` (defaults 255 / 5000).

## TypeScript

```typescript
export type PlatformLocale = 'uz' | 'ru' | 'en';
export type PlatformRole = 'user' | 'admin';
export type UserStatus = 'active' | 'inactive';
export type OrganizationRole = 'owner' | 'manager' | 'researcher' | 'viewer';
export type OrganizationStatus = 'active' | 'suspended';
export type SurveyStatus = 'draft' | 'pending_review' | 'changes_requested' | 'approved' | 'active' | 'paused' | 'closed' | 'archived';
export type QuestionType = 'single_choice' | 'multiple_choice' | 'short_text' | 'long_text' | 'rating';
export type ParticipationStatus = 'in_progress' | 'completed';
export type ModerationDecision = 'submitted' | 'approved' | 'changes_requested' | 'rejected';
export type SurveyDestination = { type: 'page'; id: number } | { type: 'completed' };
```

# Dialog — Auth, authorization and organization model

## Authentication

Current authentication method:

- email + password
- Laravel Sanctum

Recommended transport:

- first-party web SPA: Sanctum stateful/session-cookie authentication;
- native mobile app later: Sanctum token authentication.

The domain must not depend on whether the caller is web or mobile.

## Future identity providers

MyID is explicitly out of the MVP.

Do not mix future MyID logic into survey/business code. When MyID is implemented later, it should be an identity/authentication provider concern.

## Platform roles

Current platform-level roles:

- `user`
- `admin`

Users cannot self-promote to `admin`.

## Organization roles

Organization membership determines business permissions:

- `owner`
- `manager`
- `researcher`
- `viewer`

The exact permission matrix should be centralized in policies/authorization rules, not duplicated in controllers.

## Business onboarding

Current decision:

```text
Register user (POST /api/v1/auth/register, user only)
  -> authenticated business onboarding (POST /api/v1/business/organizations, name + unique inn)
  -> creator becomes OWNER
```

Registration does not accept organization details as part of its documented payload and never creates an organization. The organization and owner membership are created atomically by the separate business endpoint. New organizations require an INN string unique across the platform; legacy rows may retain null until an owner/manager fills it in. `/auth/me` returns only the caller's organizations with their INNs and membership roles, or an empty array.

Survey ownership belongs to the organization, not directly to the creator.

`created_by` is audit/attribution data, not ownership.

## Respondent access

Current MVP decisions:

- respondent must be authenticated;
- one respondent can have only one participation per survey;
- in-progress participation is resumed;
- completed participation cannot be edited in the MVP.

## Authorization principles

- Backend authorization is authoritative.
- Frontend visibility is UX only.
- A business user can access only organizations they belong to.
- A respondent can access only their own participation.
- An admin moderation endpoint must verify platform-admin permission.
- Business users cannot approve their own survey through admin behavior.

## Organization API context

Business routes must have an explicit, backend-validated organization context.

Recommended route style:

```text
/api/v1/business/organizations/{organization}/surveys
```

or an equivalent explicit organization context if the final route naming is later simplified.

Do not trust an organization ID merely because the frontend supplied it. Membership/permission must be checked on every relevant request.

# Dialog authentication API

## Implemented transport

The current API uses Laravel Sanctum **Bearer tokens**. Registration and login return a `user` resource plus a plaintext `token`. Send it in `Authorization: Bearer <token>` on subsequent requests.

Although first-party SPA cookie authentication is recommended by `04-AUTH-AUTHORIZATION-ORGANIZATION.md`, the current login action does not establish a session and the stateful API middleware is not enabled. Calling `/sanctum/csrf-cookie` alone does not log a client in. Do not integrate a cookie-only flow until it is implemented and tested.

## Endpoints

- `POST /api/v1/auth/register`: `name`, `email`, `password`, optional `password_confirmation` (must match when supplied), and optional `locale` (uz/ru/en). Creates only the user; no organization fields belong in the registration form. Emails are trimmed/lowercased before uniqueness validation. Returns HTTP 201 with `user` and `token`.
- `POST /api/v1/auth/login`: `email`, `password`, optional `device_name` (string, maximum 255 characters; defaults to `web`). Returns HTTP 200.
- `GET /api/v1/auth/me`: requires Bearer authentication; returns `user` and a top-level `organizations` array containing only the authenticated user's memberships, most recent first. Each entry contains the organization's `id`, `name`, `inn`, and `current_user_role` (`owner`, `manager`, `researcher`, or `viewer`). Users without memberships receive `organizations: []`; platform admins also receive only organizations they belong to. `user.platform_role` remains separate from organization roles.
- `POST /api/v1/auth/logout`: revokes the current token; returns a success message.

```http
POST /api/v1/auth/login
Accept: application/json
Content-Type: application/json

{"email":"owner@example.com","password":"your-password"}
```

```json
{"user":{"id":2,"name":"Owner","email":"owner@example.com","locale":"uz","platform_role":"user","status":"active"},"token":"2|example-token"}
```

```http
GET /api/v1/auth/me
Accept: application/json
Authorization: Bearer 2|example-token
```

```json
{
  "user": {
    "id": 2,
    "name": "Owner",
    "email": "owner@example.com",
    "locale": "uz",
    "platform_role": "user",
    "status": "active",
    "created_at": "2026-10-17T12:00:00.000000Z"
  },
  "organizations": [
    {
      "id": 1,
      "name": "Dialog Analytics Group",
      "inn": "123456789",
      "current_user_role": "owner"
    }
  ]
}
```

Frontend startup can use `/auth/me` for both the profile and organization context. This organization summary is returned by `/auth/me`; registration and login continue to return `user` and `token`. Backend permissions are still checked on each organization-scoped request.

## Separate organization onboarding

After registration, use the returned token directly; another login is unnecessary. Business users create their organization through this separate authenticated endpoint:

```http
POST /api/v1/business/organizations
Authorization: Bearer 2|example-token
Accept: application/json
Content-Type: application/json

{"name":"Dialog Analytics Group","inn":"123456789"}
```

Both fields are required strings, maximum 255 characters. INN is trimmed and must be unique across all organizations; send it as a JSON string to preserve leading zeros. A duplicate or invalid INN returns HTTP 422 `VALIDATION_ERROR` with `error.fields.inn`. Organization creation and owner membership are atomic, and the HTTP 201 response contains `organization` with `id`, `name`, `inn`, `status`, `created_by`, `current_user_role`, and `created_at`. Refresh `/auth/me` or add this organization to the frontend state after creation. Respondents can skip organization onboarding.

`PATCH /api/v1/business/organizations/{organization}` requires owner/manager permission, `name` (required string), and optionally `inn` (a non-empty unique string). Keeping the organization's own INN is allowed; sending null to clear it is rejected. Older organizations may have `inn: null` after the migration and can be completed through this endpoint.

Do not log tokens or passwords. Invalid credentials return HTTP 401 `INVALID_CREDENTIALS`; inactive accounts cannot log in (HTTP 403 `ACCOUNT_INACTIVE`). Missing/revoked tokens return HTTP 401 `UNAUTHORIZED`. Request validation uses the standard error envelope. Backend messages are currently English; no Accept-Language negotiation is implemented.

Password-reset endpoints are not implemented. The database token table alone does not constitute an available reset flow.

Registration and login share a limit of 20 requests per minute per IP. Authenticated APIs share a limit of 120 requests per minute per user. Exceeding either returns HTTP 429 `TOO_MANY_REQUESTS` with `Retry-After` and rate-limit headers.

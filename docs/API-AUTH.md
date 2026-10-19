# Dialog authentication API

## Implemented transport

The current API uses Laravel Sanctum **Bearer tokens**. Registration and login return a `user` resource plus a plaintext `token`. Send it in `Authorization: Bearer <token>` on subsequent requests.

Although first-party SPA cookie authentication is recommended by `04-AUTH-AUTHORIZATION-ORGANIZATION.md`, the current login action does not establish a session and the stateful API middleware is not enabled. Calling `/sanctum/csrf-cookie` alone does not log a client in. Do not integrate a cookie-only flow until it is implemented and tested.

## Endpoints

- `POST /api/v1/auth/register`: `name`, `email`, `password`, `password_confirmation`, optional `locale` (uz/ru/en). Returns HTTP 201.
- `POST /api/v1/auth/login`: `email`, `password`, optional `device_name`. Returns HTTP 200.
- `GET /api/v1/auth/me`: requires Bearer authentication; returns `user`.
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

Do not log tokens or passwords. Invalid credentials return HTTP 401 `INVALID_CREDENTIALS`; inactive accounts cannot log in (HTTP 403 `ACCOUNT_INACTIVE`). Missing/revoked tokens return HTTP 401 `UNAUTHORIZED`. Request validation uses the standard error envelope. Backend messages are currently English; no Accept-Language negotiation is implemented.

Password-reset endpoints are not implemented. The database token table alone does not constitute an available reset flow.

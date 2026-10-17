# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

For SPA clients, Sanctum uses session cookies with CSRF protection (`/sanctum/csrf-cookie`). For token clients (mobile or API testing), pass the header `Authorization: Bearer <plainTextToken>` returned by `/api/v1/auth/login` or `/api/v1/auth/register`.

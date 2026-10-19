# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Pass Authorization: Bearer <plainTextToken> using the token returned by /api/v1/auth/login or /api/v1/auth/register. Cookie-only SPA authentication is not implemented.

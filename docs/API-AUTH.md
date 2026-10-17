# Dialog API Authentication Guide

This document describes authentication workflows for frontend applications integrating with Dialog, including Single Page Applications (Next.js, Vue, Nuxt, React, Vite) and API clients using Bearer tokens.

---

## 1. Authentication Mechanisms

Dialog supports two primary authentication modes via Laravel Sanctum:

1. **SPA Session Cookie Authentication (Recommended for Web Browsers)**
   - Uses `HttpOnly` secure cookies and CSRF protection.
   - Ideal for client-side web apps on the same top-level domain.

2. **Bearer Token Authentication (Mobile Apps & API Integrations)**
   - Uses the `Authorization: Bearer <token>` header.
   - Returned upon successful login or registration.

---

## 2. SPA Cookie Authentication Flow

For browser-based SPAs:

### Step 1: Initialize CSRF Protection
Before sending login or register requests, call the CSRF endpoint:
```http
GET /sanctum/csrf-cookie
Host: api.dialog.uz
```
The server responds with HTTP 204 and sets the `XSRF-TOKEN` cookie.

### Step 2: Login or Register
Send the credentials along with `X-XSRF-TOKEN` (most HTTP libraries like Axios do this automatically):
```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json

{
  "email": "owner@dialog.uz",
  "password": "Secret123!"
}
```
Response:
```json
{
  "user": {
    "id": 2,
    "name": "Dilshod Karimov",
    "email": "owner@dialog.uz",
    "locale": "uz",
    "platform_role": "user",
    "status": "active",
    "created_at": "2026-10-17T12:00:00.000000Z"
  },
  "token": "2|AbCd...token"
}
```

### Step 3: Fetch Current User
On subsequent page reloads, verify the session:
```http
GET /api/v1/auth/me
Accept: application/json
```

### Step 4: Logout
```http
POST /api/v1/auth/logout
Accept: application/json
```

---

## 3. Bearer Token Flow (Mobile & Postman)

Pass the token returned from `/api/v1/auth/login` in the `Authorization` header for every request:

```http
GET /api/v1/business/organizations
Authorization: Bearer 2|AbCd...token
Accept: application/json
```

---

## 4. Frontend Client Setup Example (Axios)

```typescript
import axios from 'axios';

export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api/v1',
  withCredentials: true, // Required for Sanctum session cookies
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Attach Bearer token fallback if present in localStorage
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('dialog_token');
  if (token && !config.headers.Authorization) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Automatic CSRF init helper
export async function initializeCsrf() {
  await axios.get('http://localhost:8000/sanctum/csrf-cookie', {
    withCredentials: true,
  });
}
```

---

## 5. Troubleshooting Common Authentication Issues

### 419 CSRF Token Mismatch / Unknown Status
- **Cause**: The `/sanctum/csrf-cookie` endpoint was not called prior to `POST /api/v1/auth/login`, or the `X-XSRF-TOKEN` header was omitted.
- **Fix**: Call `GET /sanctum/csrf-cookie` before making login/registration calls. Ensure `withCredentials: true` is configured in Axios/Fetch.

### 401 Unauthorized
- **Cause**: Session expired, cookie missing, or Bearer token is invalid/revoked.
- **Fix**: Clear cached user in frontend store and redirect the user to `/login`.

### CORS (Cross-Origin Resource Sharing) Errors
- **Cause**: Frontend origin (e.g. `http://localhost:3000`) is not listed in backend `config/cors.php`.
- **Fix**: Ensure `SANCTUM_STATEFUL_DOMAINS` or `CORS_ALLOWED_ORIGINS` includes your frontend origin.

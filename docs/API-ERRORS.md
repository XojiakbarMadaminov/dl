# Dialog API Error Handling Guide

This document defines the unified error format used across all Dialog REST APIs (`/api/v1/*`), common error codes, HTTP status mapping, and actionable handling strategies for frontend engineers.

---

## 1. Unified Error Envelope

Every 4xx and 5xx response from Dialog APIs is formatted consistently:

```json
{
  "error": {
    "code": "ERROR_CODE_STRING",
    "message": "Human-readable description of what went wrong.",
    "fields": {
      "field_name": [
        "First specific validation message for field_name."
      ]
    }
  }
}
```

### Response Attributes:
- `error.code` (`string`): Machine-readable uppercase identifier (e.g., `VALIDATION_ERROR`, `SURVEY_NOT_EDITABLE`).
- `error.message` (`string`): Localized, user-facing summary message suitable for toasts or alerts.
- `error.fields` (`Record<string, string[]>`): Object mapping invalid request parameters to arrays of error messages. Guaranteed to be an empty object `{}` when the error is not field-specific.

---

## 2. Standard Error Codes & HTTP Statuses

| HTTP Status | Error Code | Description | Frontend Recommended Action |
|:---|:---|:---|:---|
| **400** | `BAD_REQUEST` | Generic invalid request syntax or malformed JSON | Check payload format; report to developer |
| **401** | `UNAUTHORIZED` | Unauthenticated request or expired token/session | Clear stored credentials; redirect to `/login` |
| **401** | `INVALID_CREDENTIALS` | Invalid email or password during login | Highlight credentials input and display error |
| **403** | `FORBIDDEN` | Authenticated user lacks required permissions | Display unauthorized banner or redirect to dashboard |
| **403** | `ACCOUNT_INACTIVE` | User account is suspended or pending verification | Display account inactive modal |
| **404** | `NOT_FOUND` | Resource not found or does not belong to organization | Display 404 screen or redirect to entity list |
| **409** | `CONFLICT` | Concurrent update conflict or unique constraint | Prompt user to refresh data and retry |
| **422** | `VALIDATION_ERROR` | Request payload failed schema or business validation rules | Map `error.fields` directly to form input errors |
| **422** | `SURVEY_NOT_EDITABLE` | Survey is in a non-editable state (`active`, `closed`, `archived`) | Lock builder UI; show "Survey is locked" banner |
| **422** | `INVALID_STATUS_TRANSITION` | Attempted invalid lifecycle transition (e.g. publishing draft without review) | Refresh survey status and update action buttons |
| **422** | `SURVEY_NOT_ACTIVE` | Respondent attempted to answer non-active survey | Show "Survey not open" screen |
| **422** | `UNSUPPORTED_SURVEY_LOCALE`| Requested locale is not enabled for this survey | Default to survey `default_locale` |
| **422** | `CANNOT_DELETE_LAST_PAGE` | Attempted to delete the only remaining page in survey | Show alert: "Survey must contain at least 1 page" |
| **500** | `INTERNAL_SERVER_ERROR` | Unexpected server crash or exception | Display global error fallback toast |

---

## 3. Handling Validation Errors (HTTP 422)

When `error.code === "VALIDATION_ERROR"`, `error.fields` contains exact key paths:

### Example: Localized Field Validation
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The title.uz field is required.",
    "fields": {
      "title.uz": [
        "Title is required for selected locale: uz"
      ],
      "locales": [
        "At least one locale must be selected."
      ]
    }
  }
}
```

### Frontend Implementation Snippet (TypeScript / React / Vue):
```typescript
interface ApiErrorEnvelope {
  error: {
    code: string;
    message: string;
    fields: Record<string, string[]>;
  };
}

export function handleApiError(
  err: any,
  setFieldError?: (field: string, message: string) => void
) {
  const envelope: ApiErrorEnvelope = err.response?.data;

  if (!envelope?.error) {
    console.error("Unknown error response", err);
    return;
  }

  const { code, message, fields } = envelope.error;

  if (code === "VALIDATION_ERROR" && setFieldError) {
    Object.entries(fields).forEach(([fieldName, messages]) => {
      setFieldError(fieldName, messages[0]);
    });
  } else if (code === "UNAUTHORIZED") {
    window.location.href = "/login";
  } else {
    // Show toast or snackbar
    showToast({ type: "error", title: code, message });
  }
}
```

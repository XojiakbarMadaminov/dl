# Introduction

Complete REST API reference for the Dialog survey research platform. Covers Authentication, Business Survey Management, Respondent Participation Flow, and Admin Moderation.

<aside>
    <strong>Base URL</strong>: <code>http://dl.test</code>
</aside>

This documentation provides the complete API contract for frontend integration of the Dialog platform.

### Conventions & Principles
- **Base URL**: `/api/v1`
- **Error Envelope**: All error responses return HTTP 4xx/5xx with the standard envelope: `{"error": {"code": "ERROR_CODE", "message": "Human readable message", "fields": {...}}}`.
- **Multilingual Fields**: Multi-locale fields (`title`, `description`, etc.) accept JSON objects keyed by ISO language codes (`uz`, `ru`, `en`).
- **Respondent Flow**: Progression through survey pages is fully directed by the backend `SurveyFlow` response contract.


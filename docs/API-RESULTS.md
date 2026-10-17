# Dialog Survey Results & Analytics Guide

This guide describes how to consume survey analytics, aggregate statistics, individual response data, and export CSV spreadsheets for business users.

---

## 1. High-Level Summary Metrics

Endpoint: `GET /api/v1/business/organizations/{organization}/surveys/{survey}/results/summary`

Returns overview metrics for dashboards:
```json
{
  "survey_id": 1,
  "started_count": 120,
  "completed_count": 96,
  "in_progress_count": 24,
  "completion_rate": 80.0
}
```

- `completion_rate`: Calculated as `round((completed_count / started_count) * 100, 2)` (or `0.0` if no starts).

---

## 2. Question-Level Aggregate Statistics

Endpoint: `GET /api/v1/business/organizations/{organization}/surveys/{survey}/results/questions/{question}?locale=uz`

Returns distribution metrics tailored by question type:

### A. Choice Questions (`single_choice`, `multiple_choice`):
```json
{
  "question_id": 101,
  "type": "single_choice",
  "title": "Xizmatimizdan qanchalik tez-tez foydalanasiz?",
  "total_answers": 96,
  "options": [
    {
      "id": 501,
      "label": "Har kuni",
      "count": 72,
      "percentage": 75.0
    },
    {
      "id": 502,
      "label": "Haftada bir necha marta",
      "count": 24,
      "percentage": 25.0
    }
  ]
}
```

### B. Rating Questions (`rating`):
```json
{
  "question_id": 104,
  "type": "rating",
  "title": "Xizmat sifatini baholang (1-5)",
  "total_answers": 96,
  "average": 4.65,
  "min": 1,
  "max": 5
}
```

### C. Text Questions (`short_text`, `long_text`):
```json
{
  "question_id": 105,
  "type": "short_text",
  "title": "Qo‘shimcha fikrlaringiz",
  "total_answers": 48
}
```

---

## 3. Respondent Participations & Detail

### List Participations (Paginated):
Endpoint: `GET /api/v1/business/organizations/{organization}/surveys/{survey}/participations?status=completed&page=1`

Response includes paginated records with respondent basic profile and timestamps:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 14,
      "survey_id": 1,
      "user_id": 3,
      "locale": "uz",
      "status": "completed",
      "started_at": "2026-10-17T10:00:00.000000Z",
      "completed_at": "2026-10-17T10:05:00.000000Z",
      "user": {
        "id": 3,
        "name": "Anvar Alimov",
        "email": "respondent@dialog.uz"
      }
    }
  ],
  "total": 96,
  "per_page": 20
}
```

### Individual Participation Answer Breakdown:
Endpoint: `GET /api/v1/business/organizations/{organization}/surveys/{survey}/participations/{participation}`

Response:
```json
{
  "participation": {
    "id": 14,
    "status": "completed",
    "locale": "uz",
    "started_at": "2026-10-17T10:00:00.000000Z",
    "completed_at": "2026-10-17T10:05:00.000000Z",
    "user": {
      "id": 3,
      "name": "Anvar Alimov",
      "email": "respondent@dialog.uz"
    },
    "answers": [
      {
        "question_id": 101,
        "question_title": "Xizmatimizdan qanchalik tez-tez foydalanasiz?",
        "type": "single_choice",
        "text_value": "Har kuni",
        "number_value": null,
        "selected_options": [
          { "id": 501, "label": "Har kuni" }
        ]
      },
      {
        "question_id": 104,
        "question_title": "Xizmat sifatini baholang (1-5)",
        "type": "rating",
        "text_value": null,
        "number_value": 5,
        "selected_options": []
      }
    ]
  }
}
```

---

## 4. Exporting Results to CSV

Endpoint: `GET /api/v1/business/organizations/{organization}/surveys/{survey}/results/export?locale=uz`

- **Content-Type**: `text/csv; charset=UTF-8`
- **Content-Disposition**: `attachment; filename="survey_1_results_20261017_120000.csv"`
- Standard UTF-8 BOM encoding for flawless rendering in Excel and Google Sheets.
- Columns include: `participation_id`, `respondent_id`, `respondent_name`, `locale`, `started_at`, `completed_at`, followed by each question title column with the respondent's answered text, number, or comma-separated option labels.

# Dialog API Localization Guide

This guide explains how localization works across Dialog, separating **Platform Interface Localization** from **Survey Content Localization**.

---

## 1. Two Tiers of Localization

1. **Platform Interface Localization**
   - Applies to UI strings, system notification messages, error messages, and the user's preferred language (`users.locale`).
   - Stored as the user profile locale (`uz`, `ru`, `en`) for client UI preferences. The backend does not currently negotiate `Accept-Language`; error messages are English and clients should translate stable error codes.

2. **Survey Content Localization**
   - Applies to survey titles, descriptions, page titles, question titles, and choice option labels.
   - Surveys can be configured with one or more languages selected from `['uz', 'ru', 'en']`.
   - The backend stores localized content as a JSON object keyed by locale: `{"uz": "...", "ru": "...", "en": "..."}`.

---

## 2. Multi-Language Survey Configuration

When creating or updating a survey, the client specifies:
- `default_locale`: The primary language (e.g. `"uz"`). Must belong to `locales`.
- `locales`: Array of enabled languages (e.g. `["uz"]`, `["uz", "ru"]`, or `["uz", "ru", "en"]`).

### Example 1: Single-Language Survey (Uzbek only)
```json
{
  "default_locale": "uz",
  "locales": ["uz"],
  "title": {
    "uz": "Mijozlar qoniqishi so‘rovi"
  },
  "description": {
    "uz": "Xizmat sifatini oshirish uchun fikringiz muhim"
  }
}
```

### Example 2: Bilingual Survey (Uzbek + Russian)
```json
{
  "default_locale": "uz",
  "locales": ["uz", "ru"],
  "title": {
    "uz": "Mijozlar qoniqishi so‘rovi",
    "ru": "Опрос удовлетворенности клиентов"
  },
  "description": {
    "uz": "Xizmat sifatini oshirish uchun fikringiz muhim",
    "ru": "Ваше мнение важно для улучшения качества услуг"
  }
}
```

### Example 3: Trilingual Survey (Uzbek + Russian + English)
```json
{
  "default_locale": "uz",
  "locales": ["uz", "ru", "en"],
  "title": {
    "uz": "Mijozlar qoniqishi so‘rovi",
    "ru": "Опрос удовлетворенности клиентов",
    "en": "Customer Satisfaction Survey"
  },
  "welcome_message": {
    "uz": "Assalomu alaykum!",
    "ru": "Здравствуйте!",
    "en": "Hello and welcome!"
  }
}
```

---

## 3. Question & Option Localization

In the survey builder, all nested entities (pages, questions, options) follow the identical dictionary pattern:

### Question Payload:
```json
{
  "type": "single_choice",
  "is_required": true,
  "title": {
    "uz": "Qaysi xizmatimizdan foydalandingiz?",
    "ru": "Какой услугой вы воспользовались?",
    "en": "Which service did you use?"
  }
}
```

### Option Payload:
```json
{
  "label": {
    "uz": "Yetkazib berish",
    "ru": "Доставка",
    "en": "Delivery"
  }
}
```

### Validation Rule:
When submitting a survey for moderation review (`submit-review`), **all selected survey locales must have non-empty text strings** for titles, questions, and option labels. Missing translations will trigger validation errors detailing the exact missing locale keys.

---

## 4. Resolved Localized Responses in Respondent Flow

When a respondent participates in a survey (`POST /api/v1/respondent/surveys/{survey}/participate`), they specify their participation language via the `locale` parameter.

In the respondent flow:
- The backend resolves all multilingual text strings to the requested language.
- The respondent client receives a clean, flat string instead of multi-locale maps, minimizing frontend complexity:

```json
{
  "current_step": {
    "page": {
      "id": 1,
      "title": "Xizmatlardan foydalanish",
      "questions": [
        {
          "id": 1,
          "type": "single_choice",
          "title": "Qaysi xizmatimizdan foydalandingiz?",
          "options": [
            {
              "id": 1,
              "label": "Yetkazib berish"
            }
          ]
        }
      ]
    }
  }
}
```
Question titles and option labels fall back to `survey.default_locale`. Optional descriptions, page labels and rating endpoint labels resolve to null when absent. Published required content must pass translation validation.

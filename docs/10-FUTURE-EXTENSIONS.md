# Dialog — Future extensions (not current implementation scope)

This document exists so agents do not accidentally build future functionality now while still preserving obvious extension points.

## Survey conditional logic

Future implementation may add:

```text
survey_logic_rules
survey_logic_conditions
```

or another deliberately designed rule model.

Do not add these tables in the current MVP.

The extension point is `SurveyFlow`.

## Survey versioning

Not in the MVP.

Current rule: active/published survey content is immutable.

If versioning becomes a real requirement later, design it as a dedicated migration/feature rather than adding unused version tables now.

## Public survey distribution / collectors

Future distribution may include:

- public link;
- private invitation;
- email campaign;
- QR code;
- embed;
- channel-specific reporting.

Do not use sequential survey IDs as a security mechanism. If public/shareable access is introduced, use dedicated random public tokens/collector records and explicit access rules.

## MyID / KYC

Future identity verification is separate from current email/password auth. Provider-specific data must not leak into survey/business modules.

## Payments and rewards

Future payment/reward functionality must use its own explicit money and ledger rules. It is intentionally excluded from current migrations and actions.

## Advanced question types

The source design includes future types such as:

- dropdown
- email
- number
- date
- star rating
- NPS
- slider
- ranking
- matrix
- image choice
- screening
- content/welcome/completion/disqualification screens

Do not add generic complexity for these types before they are scheduled.

## Other research types

Future platform scope may include interview and usability-testing modules.

Do not introduce a generic `Study` aggregate/table in the MVP just because these features may exist later. Revisit the abstraction when at least one additional research type is actually designed for implementation.

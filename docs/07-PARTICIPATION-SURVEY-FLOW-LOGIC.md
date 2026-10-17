# Dialog — Participation, SurveyFlow and future survey logic

## Respondent participation rules

Current MVP decisions:

- respondent must be logged in;
- one participation per `(survey_id, user_id)`;
- starting again resumes an `in_progress` participation;
- completed participation is immutable in the MVP;
- answers are saved page by page;
- double-submit must not create duplicate completion;
- the selected survey locale is stored on the participation.

## Start flow

```text
Respondent opens active survey
  -> chooses/uses supported locale
  -> start participation
  -> backend creates or resumes participation
  -> backend returns the first applicable page
```

Reject start when:

- survey is not active;
- requested locale is not supported by the survey;
- authorization/access rules fail;
- participation is already completed (return completed state, do not create another record).

## Save page flow

```text
Client submits answers for current page
  -> backend resolves applicable questions
  -> AnswerValidator validates only applicable questions
  -> answers are persisted atomically
  -> SurveyFlow calculates next destination
  -> response returns `next`
```

The frontend does not calculate the next page.

## SurveyFlow

Create a domain service/component:

```text
SurveyFlow
```

Responsibilities:

- determine the initial page;
- determine which questions are applicable/visible;
- determine the next destination;
- support back-navigation history/semantics when logic is introduced;
- return terminal states such as `completed` (and `disqualified` later).

### MVP implementation

MVP flow is linear:

```text
Page 1 -> Page 2 -> Page 3 -> Complete
```

Do not create logic-rule tables yet.

Do not hard-code navigation in controllers or frontend using `position + 1` assumptions. Linear navigation belongs inside `SurveyFlow`.

## Validation

`AnswerValidator` must validate by question type.

Examples:

- required question cannot be empty if the question is applicable;
- single choice: exactly one valid option;
- multiple choice: only valid options and respect min/max selections;
- short/long text: string rules/limits;
- rating: value inside configured min/max.

When future logic hides a required question, that hidden question must not block progress.

## Preview

Preview must exercise the same question validation and SurveyFlow behavior, but must not create real participation/answer data that can leak into analytics.

Recommended approach:

- dedicated preview endpoints or stateless preview requests;
- reuse `AnswerValidator` and `SurveyFlow`;
- no real `participations` record for normal preview.

## Future conditional logic supported by the source design

The source materials define future capabilities including:

1. show a question based on a previous answer;
2. skip to another page;
3. screening-based early finish/disqualification;
4. combine conditions with AND/OR;
5. pipe a previous answer into later text;
6. carry forward previously selected options;
7. randomize option order;
8. question-type-specific operators;
9. priority when multiple navigation rules match;
10. loop prevention and broken-reference validation;
11. clear/exclude downstream answers that become inapplicable after an earlier answer changes;
12. make Back return to the actually visited previous page.

When this feature is implemented later, add dedicated logic rule storage and replace only the internal `SurveyFlow` algorithm. The respondent API contract should remain stable.

## Future logic invariants

- hidden required questions do not block navigation;
- broken rule references block publishing;
- rules may not create infinite loops;
- if an earlier answer changes the route, now-inapplicable downstream answers must not remain in final results;
- if no rule matches, default sequence continues.

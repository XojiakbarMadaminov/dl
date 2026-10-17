# Dialog — Backend architecture

## Stack

- Laravel 13
- PHP 8.4
- PostgreSQL
- Redis
- Laravel Sanctum
- Redis-backed queues
- Laravel Scheduler
- REST API under `/api/v1`
- Modular monolith

## Local environment

The developer's local environment is native, not Docker-first:

- Nginx
- PHP-FPM
- PostgreSQL
- Composer
- Redis is required by the application stack; exact local installation/provisioning is not prescribed here.

Do not rewrite the project into a Docker-only local workflow unless explicitly requested.

## Production environment

- Nginx
- PHP-FPM
- PostgreSQL
- Redis
- queue workers
- Laravel Scheduler

## Architecture style

Dialog is a **modular monolith**.

This means:

- one Laravel application;
- one deployment unit;
- one relational database;
- domain-oriented code organization;
- no service-to-service network boundaries;
- no attempt to make modules behave as future microservices.

### Module boundaries

Module boundaries are for maintainability and ownership, not hard runtime isolation.

Allowed:

- cross-module Eloquent relationships;
- cross-module queries when clear and reasonable;
- database foreign keys across domains;
- transactions spanning multiple modules.

Avoid:

- circular dependencies;
- giant controllers;
- generic God services;
- unnecessary contracts/interfaces/repositories;
- putting unrelated code into `Shared`/`Support` just to avoid dependency decisions.

## Core application patterns

### Write path

```text
Controller
  -> FormRequest
  -> Action
  -> Model / domain Service
```

Examples:

- `CreateSurveyAction`
- `UpdateSurveyAction`
- `SubmitSurveyForReviewAction`
- `StartParticipationAction`
- `SavePageAnswersAction`
- `CompleteParticipationAction`
- `ApproveSurveyAction`
- `RequestSurveyChangesAction`

### Read path

```text
Controller
  -> Query
  -> API Resource
```

Use Query classes for read-heavy or reusable list/detail retrieval. Simple direct Eloquent reads do not need a Query class just for ceremony.

### Domain services

Use services only for reusable domain algorithms, for example:

- `SurveyFlow`
- `SurveyPublishValidator`
- `SurveyTranslationValidator`
- `AnswerValidator`

Do not create generic `SurveyService`, `UserService`, etc. if they become grab-bags of unrelated methods.

## Survey navigation architecture

The respondent frontend must never assume that the next page is simply `current_position + 1`.

All respondent navigation goes through:

```text
Participation
  -> SurveyFlow
  -> next destination
```

In the MVP `SurveyFlow` is linear. Later, conditional logic can replace the internal decision algorithm without changing the respondent API contract.

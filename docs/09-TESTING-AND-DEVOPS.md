# Dialog — Testing, quality and DevOps rules

## Required development quality tools

Recommended baseline:

- Laravel Pint
- Larastan/PHPStan compatible with the chosen Laravel/PHP versions
- PHPUnit/Pest according to project preference; do not mix styles without reason
- CI checks for formatting/static analysis/tests

## Critical automated tests

Prioritize domain-risk tests over mechanical CRUD coverage.

Must cover:

- organization isolation;
- platform admin authorization;
- survey editable vs immutable states;
- allowed/forbidden survey status transitions;
- selected locale validation;
- translation completeness before moderation;
- question-type answer validation;
- one participation per respondent per survey;
- save/resume flow;
- page-by-page persistence;
- completed participation immutability;
- duplicate complete request/idempotency;
- respondent cannot access another user's participation;
- moderation approve/request-changes permissions;
- preview does not affect real analytics;
- SurveyFlow linear fallback and conditional navigation;
- rule ownership, immutability, operators, cross-survey/broken references and loop prevention;
- hidden-required validation, AND/OR, priority, early completion and actual-history Back;
- branch-change cleanup in results/export and stateless preview parity.

## Transactions

Use database transactions for operations such as:

- creating organization + owner membership during onboarding;
- persisting a page's answer set when partial writes would be invalid;
- state transitions that also create moderation/audit records;
- participation completion and associated final state changes.

## Queue

Redis-backed queue is part of the stack.

Do not queue work that must complete synchronously for data correctness. Good future queue candidates include:

- exports;
- emails/notifications;
- heavy result recomputation;
- provider webhooks/integrations later.

## Scheduler

Use Laravel Scheduler for scheduled application jobs. Do not create ad-hoc cron scripts outside the application unless necessary.

## Logging

- structured application logs;
- include request/correlation context where useful;
- do not log passwords/tokens/sensitive answer content unnecessarily;
- domain-critical state changes also use `audit_logs` where required.

## Production deployment baseline

- Nginx
- PHP-FPM
- PostgreSQL
- Redis
- queue worker process supervision
- scheduler trigger
- environment-specific `.env`
- database migrations as a controlled deployment step
- backups and restore procedure

Exact server topology is a separate deployment task; do not prematurely split services across multiple servers.

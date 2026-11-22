# JK Michaels Professional Mastery Platform

Laravel 12 + Inertia 2 + Vue 3 LMS for learners, facilitators, content reviewers, sponsors, organization administrators and platform administrators.

## Current readiness

The enterprise audit and first remediation batch are documented here:

- [Comprehensive audit and findings](docs/11-ENTERPRISE-AUDIT-2026-10-05.md)
- [Specification and 59-feature traceability matrix](docs/12-REQUIREMENTS-TRACEABILITY.md)
- [Implementation record and release gates](docs/13-IMPLEMENTATION-AND-RELEASE-PLAN.md)
- [Verification results and screenshots](docs/qa/VERIFICATION.md)

The platform is **not yet approved for an enterprise production launch**. Safe private media/SCORM delivery, full tenant policies, recurring billing, completion/certificates, unified content revisions and operational evidence remain release work. Historical docs describe intentions as well as implementation; use the current audit to distinguish them.

## Local development

Requirements: PHP 8.2+ with the extensions required by Composer and the selected database, Composer, and a Node.js version supported by the locked Vite version. CI currently uses PHP 8.3 and Node 20. Use a local development database.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Configure the local database in `.env`, then run migrations against that development database:

```powershell
php artisan migrate
npm run build
php artisan serve
```

Use `npm run dev` for Vite development. Configure optional payment, AI, mail, storage and meeting services separately; a visible screen does not imply the external service is configured. See `docs/06-DEPLOYMENT-DEVOPS.md` for the intended infrastructure and the release plan for unverified dependencies.

## Verification

```powershell
php artisan test --without-tty
php docs/qa/workspace-inventory.php
composer validate --strict
composer audit
npm audit
npm run build
php vendor/bin/pint --test
```

Tests use an isolated fixture environment and in-memory SQLite connections, with unexpected HTTP calls blocked. They do not load the developer `.env`. The local suite passes 39 tests / 203 assertions; build, dependency audits, Composer validation and repository-wide Pint pass. MySQL/provider/concurrency/device testing is still required.

## Authoring and rollout

Admin and facilitator authoring now use the same lesson editor and validation contract. Course types are `self_paced`, `live`, `certified` and `bootcamp`; levels are `beginner`, `intermediate` and `advanced`. A free course has price zero. Readiness checks operate on saved content before review/publication.

The new assessment migration adds question snapshots, draft responses and deadlines. Apply it in staging before deploying the updated controllers. Legacy in-progress attempts without snapshots restart explicitly; retain completed history and communicate the transition. See the release plan for rollout and rollback details.

The October audit preview uses synthetic data under ignored `tmp/`; it is not a production environment or a deployable dataset.

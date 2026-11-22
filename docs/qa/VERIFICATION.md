# Verification record — 5 October 2026

Project: **JK Michaels**, `C:\JK Michaels app`; source baseline `a1eba2381311ae98a2d7bf2e44b45f1c45caff1f`.

## Current verification — subsequent enterprise work

The current source remains uncommitted and the combined application has not been deployed. Another agent is working on authoring in this same folder; the results below describe the recorded snapshots rather than certifying later edits automatically.

| Check | Recorded result | Boundary |
|---|---|---|
| Current backend / SQLite | **153 tests, 1,208 assertions passed**, PHP 8.2.30 | Synthetic in-memory database; [log](phpunit-current-results.txt) |
| Current backend / MariaDB | **153 tests, 1,208 assertions passed**, PHP 8.2.26 / MariaDB 10.6.21 | Dedicated synthetic database on the authorized host; [log](mariadb-current-results.txt); archive SHA-256 in summary |
| Offline JavaScript | **12 tests passed** | Seven encrypted-store/privacy/retry tests, three worker cache/fallback tests and two integration-feedback tests |
| Frontend | **Build passed** | Latest recorded build includes contact import, prerequisite UI, checkout recovery and refund review; later concurrent changes need their own build |
| Production-copy SQL migration | **117 data tables, 1,366 rows, 45 migrations, 204 foreign-key checks passed** | [Evidence](sql-migration-rehearsal.json); canonical hashes checked before pending migrations; raw snapshot retained; live DB unchanged |
| Isolated restored runtime | **HTTP and scheduler → database queue → worker → cache checks passed** | [Evidence](isolated-runtime-verification.json); synthetic learner with migrated data; application UID; loopback/token-protected listener stopped afterwards; actual copied legacy resource scanned and migrated privately |
| Production malware scanner | **Clean and EICAR probes passed** | [Evidence](scanner-verification.json); application UID; project signature updater active; new application scanner configuration still pending |
| Production document isolation | **PDF metadata/raster and PPTX conversion passed; invalid paths, symlinks, host filesystem/network/environment access rejected** | [Evidence](document-sandbox-verification.json); updated utilities and root-owned wrappers; application environment wiring still pending |
| Paystack test API | **Subscription confirmation/linkage/non-renewing cancellation and processed full refund verified** | [Redacted evidence](commerce-provider-evidence.json); existing simulated charge, isolated database; actual inbound provider webhook/renewal cycle still pending |
| Historical learning | **130 enrollments checked; 32 require evidence review** | No historical status/date changed and no certificates issued; classification requested from user |
| Email | **Authorized message received in inbox** | User confirmed delivery; no campaign or additional recipient was contacted |
| Backup | **Same-host restore passed** | 118 tables including migration ledger; code/storage hashes match; daily Contabo service user-confirmed and independent verification deferred |

The SQL rehearsal exposed historical data issues that empty databases missed. `premium` was a commercial label in the delivery field; the migration retains it as `legacy_commercial_type` and assigns `self_paced` without changing prices. Unknown labels abort. Resource MIME values now fit the schema. Date-only columns normalize their historical time components to SQL `DATE`, with affected counts recorded; this is a semantic comparison, not byte-identical SQLite storage. The original snapshot remains protected for recovery.

New browser evidence on synthetic fixtures includes:

- Mock-exam draft creation with a five-question easy/hard blueprint: [draft proof](mock-exam-draft-verified.png).
- Saving a reading, seeing its expiry in Downloads, reading it and synchronizing a pending completion with **“1 accepted; 0 pending”**: [library](offline-library-verified.png), [sync](offline-reading-sync-verified.png).
- A fresh tab did not have the prior tab's decryption key and displayed no saved readings. Logout, changed session, account switching and revocation are covered by automated tests.
- A dedicated application origin was actually stopped after saving a reading. Reload rendered the anonymous cached reader, the encrypted reading remained usable, and failed sync retained progress. After restarting the origin, the server recorded exactly one receipt, 100% lesson/course completion and one credential; a fresh browser tab displayed 100% completion. [Unavailable origin](offline-origin-unavailable.png), [recovered course](offline-origin-recovered.png), [database evidence](offline-origin-verification.json). Operating-system airplane mode and native devices remain untested.
- Organization invitations, department assignment and filtered reports: [people](organization-people-desktop.png), [reports](organization-report-desktop.png), [mobile reports](organization-report-mobile.png).
- Internship learner submission and sponsor rubric approval: [rubric](internship-rubric-desktop.png), [mobile rubric](internship-rubric-mobile.png). See [the full continuation record](ORGANIZATION-AND-INTERNSHIP-2026-10-05.md).
- Administrator contact fields and typed CSV preview/commit: [imported contacts](crm-import-complete.png), [mobile form](crm-contact-form-mobile.png).
- Prerequisite graph editor and learner gate: [saved rules](learning-prerequisites-saved.png), [blocked learner continuation](learning-prerequisites-learner.png).
- Billing refund review and pending checkout recovery: [review dialog](billing-refund-review.png), [order recovery](order-payment-recovery.png). Browser did not submit any provider payment/refund.
- The other agent's authoring and protected-reader implementation has separate records: [authoring studio](AUTHORING-STUDIO-2026-10-05.md), [document reader](PROTECTED-DOCUMENT-READER-2026-10-05.md). The host sandbox complements that reader; its configuration must be included in deployment.

The [operational runbook](../../ops/README.md) documents the production boundary, required environment settings, systemd units and cutover/rollback precautions. The original results below remain as historical baseline evidence. **No unrestricted enterprise-release approval follows from these tests.**

## Production email verification — subsequent remediation

Production now uses the authenticated SMTP account documented in the ignored deployment file. TLS and SMTP authentication passed, and Laravel's cached configuration was rebuilt as the application owner. The previous environment and cached configuration were backed up with restricted access before the change; no credentials are recorded in this report.

At the user's explicit request, one **“JK Michaels — SMTP delivery test”** message was sent to `emichael24@gmail.com` through the production Laravel mail transport at **2026-10-05 03:37:13 UTC**. The user subsequently confirmed **“Received in inbox.”** This establishes end-to-end delivery for that message. It does not validate every queued notification, campaign, password-reset or reminder workflow. A private delivery ledger prevents this verification script from sending the message again.

The results below describe the initial remediation baseline. Subsequent code changes and their expanded test coverage must be reconciled before the final release decision; the whole code batch has not been deployed by this email configuration change.

## Initial automated evidence

| Check | Result | Limit |
|---|---|---|
| PHP feature/unit suite | **39 tests, 203 assertions pass** | SQLite in memory, synthetic fixtures; not a MySQL concurrency/load test |
| Primary workspace GET smoke test | **61 pages return HTTP 200** | Empty-data rendering; no claim that every interaction works |
| Route/controller inventory | **421 routes, 80 controller files, zero unresolved actions** | Static reflection of route targets |
| Literal Inertia page inventory | **Zero missing page files** | Dynamic page names/client-only failures are not fully covered |
| Literal navigation scan | **Zero unmatched static GET destinations** across Vue pages/layouts and shared navigation | Dynamic template URLs must be exercised separately; not an external-link crawler |
| Production frontend build | **Pass** | Compilation/bundle generation, not browser behavioral coverage |
| Composer validation | **Pass, strict** | Dependency metadata |
| Composer security audit | **No advisories/abandoned packages reported** | Advisory data at scan time; not application security proof |
| npm security audit | **Zero reported vulnerabilities** | Advisory data at scan time; not application security proof |
| Changed PHP files | Formatted with Pint | Route imports were hoisted and regression checks rerun after formatting |
| Repository-wide Pint | **Pass** | Existing style violations in 154 additional files were cleaned; route inventory and tests rerun afterward |

Supporting logs and inventories are retained in this directory. A machine-readable summary records exact result counts. These results apply to the local patched source, not an upstream commit or production deployment.

## New regression coverage

- Both admin and facilitator can create free courses and update paid price, discount and duration; invalid enum values/discounts are rejected.
- Facilitator module → full lesson fields → edit → review submission; under-review edits and unrelated owners denied; thumbnail ownership and schedule validation.
- Course approval metadata persists; empty courses and unapproved facilitator publication are rejected.
- Unenrolled/suspended/hidden/drip access cannot complete/download/commit protected lessons.
- Assessment question assignment and answer keys persist; foreign questions and expired answers are rejected; unfinished review is denied; unanswered items affect score; API does not expose answer keys.
- Sponsor/facilitator child-resource scope, organization overview scope, global ERP restriction, account deactivation, protected legacy orders and paid manual subscriptions.
- Invalid/absent/stale provider signatures, wrong amounts and repeated supported payment fulfillment.

Tests are in `tests/Feature/EnterpriseReadinessTest.php`, `CourseAuthoringTest.php`, `WorkspaceNavigationTest.php` and repaired existing feature tests. `tests/TestCase.php` loads an isolated fixture environment, uses an in-memory SQLite database for every connection, array mail/cache/session, and blocks unexpected HTTP calls. It does not load developer/production `.env` credentials.

## Initial browser evidence

The real Laravel application ran at `http://127.0.0.1:8900`, using an isolated synthetic SQLite file under ignored `tmp/jk-enterprise-audit/`. All accounts and course content were synthetic. No production migration, real payment, external campaign or learner communication was performed.

- Admin sign-in and course list/edit/readiness inspected.
- Admin curriculum → new article lesson → title/summary/content/duration → article preview → save → curriculum count increased → reopen with persisted fields: **passed**.
- Desktop editor captured in [lesson-editor-desktop.png](lesson-editor-desktop.png).
- Editor at **390×844** captured in [lesson-editor-mobile.png](lesson-editor-mobile.png). Document width was 385 px at a 390 px viewport; no document-level horizontal overflow in that inspected state.
- Mobile navigation opens as a dialog, exposes links and closes with Escape; desktop viewport restored afterward.
- Facilitator sign-in/dashboard inspected; page-level broken quick links found and repaired in source.
- Facilitator draft course → curriculum → shared editor → update summary → save → visible success: **passed**. Course counts and server-side status filtering were additionally repaired and regression tested.
- Organization administrator, SME reviewer, sponsor and learner sign-in destinations and rendered landing pages were inspected. Screenshots are retained here; this is landing-page coverage, not every role-specific transaction.
- Learner dashboard → lesson player → newly authored “Build a project charter” lesson: **passed**, with the saved title, summary and article visible. See [learner-authored-lesson.png](learner-authored-lesson.png).
- Remaining role/detail and manual journey coverage is stated in the audit; HTTP smoke checks must not be described as full browser testing of every workspace.

Browser viewport, local build and synthetic data are not representative performance measurements. No screen-reader conformance, automated accessibility score, full provider integration, native device, SCORM conformance, backup restore, staging rollout, penetration test or load-test pass is claimed.

## Reproduction

With dependencies installed and PHP on PATH:

```powershell
php artisan test --without-tty
php docs/qa/workspace-inventory.php
composer validate --strict
composer audit
npm audit
npm run build
php vendor/bin/pint --test
```

All listed checks passed locally. The inventory regenerates only `workspace-inventory-2026-10-05.json`; the baseline inventory is retained. Passing these checks does not replace the outstanding production-database and integration gates.

## Coverage boundaries

### Background imports and workspace button — latest continuation

The local preview now queues CSV imports up to 50 MB with no fixed contact-count cap, persistent progress, cancellation/resume and protected error reports. Real browser/queue verification imported 5,000 synthetic contacts from a 2.16 MB file while the administrator left the page, then showed the saved result with one duplicate and one invalid row. The workspace switcher is a labelled button with keyboard-accessible choices and a current-workspace indicator. No application release was deployed to production.

Full SQLite and isolated MariaDB suites each passed **162 tests / 1,279 assertions**; final SQL migration rollback checks passed **2 tests / 21 assertions**. The Vite build, 12 JavaScript checks and touched-file PHP formatting checks passed. See [implementation, evidence and deployment requirements](BACKGROUND-CONTACT-IMPORTS-2026-10-05.md).

### Overall audit limits

This audit covers all role/workspace areas and all major specification sections at source level, all 59 PDF feature claims, targeted protected operations, and the selected browser journeys above. It does **not** certify every route, role combination, question type, provider or production requirement. The remaining P0/P1 gates are explicit in [the release plan](../13-IMPLEMENTATION-AND-RELEASE-PLAN.md).

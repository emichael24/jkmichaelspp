# CRM and learning-boundary implementation — 5 October 2026

This continuation addresses the requested contact fields/import and additional A04/A06/A08/A09/A15 release work. It does not close every A01–A17 finding or certify the production release.

## Administrator contact journey

- Canonical `type` is used throughout create/edit/filter/persistence. The old screen sent `contact_type`, offered an unsupported Customer value and displayed statuses not accepted by the server.
- Contact information: first/last name, email, phone, WhatsApp, company, job title, source, type, status, notes, address lines, city, state/region, postal code and country. Only first/last name, type and status are mandatory for individual entry. Creating/importing assigns ownership to the acting administrator.
- The current UI accepts CSV files up to 50 MB with no fixed contact-count cap and processes them through a durable background queue. First/last name and email remain required; the chosen default type and per-row overrides are preserved. Duplicated/archived emails and invalid rows are skipped with a report while valid rows continue. Unknown/duplicate headers fail before queueing. See [background import implementation and evidence](BACKGROUND-CONTACT-IMPORTS-2026-10-05.md).
- The original encrypted, administrator-bound 15-minute preview API remains for compatibility. The UI now uses durable upload receipts and byte-offset checkpoints, committing 100-record chunks atomically. Retries preserve completed chunks and never overwrite existing contacts. Contact data and notes are excluded from audit changes. No enrollment or communication is triggered.
- Create/edit share labelled fields and error states. Modal focus management, Escape recovery and mobile single-column layout use the existing accessible dialog component. Table regions support keyboard scrolling, filters have labels and icon actions have names.
- Adjacent activity and deal controls now send their actual database/controller field names. Activity completion uses the correct HTTP method; history comes from the loaded relationship. Contact detail rows use compiled render functions rather than an unsupported runtime template.

Six CRM tests cover persistence, filtering, activity history, import validation/replay, actor binding, expiry, tampering, archived duplicates and role/deactivation boundaries. Synthetic browser acceptance created Nora Demo with company/source/address and imported Kemi Demo as Prospect plus Sam Demo as Partner. No external message was sent.

Browser evidence: [form](crm-contact-form-desktop.png), [saved contact](crm-contact-saved.png), [CSV preview](crm-import-preview.png), [completed import](crm-import-complete.png), [mobile form](crm-contact-form-mobile.png). At 390 px the document width was 390 px and no form inputs lacked a programmatic label. These checks are not a screen-reader or WCAG certification.

## Learning and private-content changes

- Resource routes require published resource and course plus current enrollment. Direct private-file requests use the same resource entitlement. Unscanned documents/packages fail closed when scanning is required; unsupported legacy paths require migration. Course catalog payloads exclude file URLs, lesson bodies and hidden-module counts.
- Saved AI conversation reads recheck their course/lesson context. The shared learning guard checks account and organization status even outside session middleware, including SCORM bearer URLs.
- Prerequisite graphs are editable only by platform administrators at `/admin/learning-rules`. Self references, cycles and unpublished prerequisite courses fail validation. Requirements gate enrollment, course-product checkout, lessons, resources and AI context. Learner course pages identify unfinished prerequisites. Curriculum snapshots and edition cloning retain prerequisite IDs. Existing free-text `prerequisites` remain descriptive; IDs are never guessed from prose.
- `media:migrate-legacy-resources` inventories first; `--apply` scans, copies and hashes a local resource before updating all shared resource references and retiring the public file. The private asset is retained for recovery. External/missing/unsafe paths and failed scans remain unresolved and fail the command. The isolated production-copy run migrated its one real legacy resource with a successful scan/hash verification; the live original was unchanged.
- `media:cleanup-uploads` removes fragments idle for more than 24 hours, skips held locks/current files and keeps lock inodes to avoid racing a waiting upload. Scheduled hourly. It does not delete finalized media.
- `learning:reconcile` records a private report comparing enrollment completion against published lessons/quizzes. `--apply` uses the normal completion service for eligible active accounts; unsupported historical completion remains unchanged and is flagged. There is no automatic revocation or invented historical credential. The production-copy report checked 130 enrollments and flagged 32 completed records without current-curriculum evidence. No certificates were issued and no historical dates/statuses changed. User classification remains pending.

## Validation and rollout

Combined SQLite and MariaDB: **153 tests / 1,208 assertions passed**. Frontend production build passed. Targeted tests cover 5 new boundary cases, 3 media operations cases, 2 historical reconciliation cases and 2 prerequisite graph/access cases, in addition to the 6 CRM cases. The SQL rehearsal passed: 117 source tables, 1,366 rows, 45 migrations and 204 foreign-key checks; isolated HTTP/queue/scanner/resource migration also passed.

New forward migrations: `2026_10_05_100004_complete_crm_contacts` and `2026_10_05_100005_add_enforced_course_prerequisites`. Existing contact values are preserved. Reverting the contact type migration refuses to discard Customer records. Production deployment, provider lifecycle acceptance, historical review resolution and other open audit gates remain pending. SSO and independent Contabo verification remain user-deferred.

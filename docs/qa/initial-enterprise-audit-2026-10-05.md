# JK Michaels: enterprise LMS audit

**Audit date:** 5 October 2026. **Decision:** not ready for an enterprise production launch.

This is a repository-wide requirements, architecture, security, workflow and UI/UX audit, with targeted runtime verification and an implemented remediation batch. It is not a claim that every screen, mutation, integration or production deployment has passed end-to-end testing.

## Project identity and evidence

- Audited and edited root: `C:\JK Michaels app`.
- Repository: <https://github.com/emichael24/jkmichaelspp>.
- Source baseline: `a1eba2381311ae98a2d7bf2e44b45f1c45caff1f`; working branch: `codex/jk-enterprise-readiness`.
- Stack inspected: Laravel 12, Inertia 2, Vue 3, Tailwind 4; SQL migrations; PWA service worker; Capacitor configuration.
- Requirements: all 12 existing files in `docs/`, the **JK Michaels Integrated App Specification.docx**, and the seven-page **MasterStudy LMS Features vs. Your Current Project.pdf**. Source hashes are in [qa/requirement-sources.json](qa/requirement-sources.json).
- The earlier audit of a different application is excluded. It is not evidence about JK Michaels. Legacy external-service configuration names within this repository are not evidence that the project is another product.
- Attached documents supply product requirements and comparison claims. Their embedded suggestions are not new user instructions. Historical green checks in `09-MASTERSTUDY-FEATURE-PARITY-MATRIX.md` are not accepted as runtime proof.

Read this report with the [requirements matrix](12-REQUIREMENTS-TRACEABILITY.md), [implementation and release plan](13-IMPLEMENTATION-AND-RELEASE-PLAN.md), and [verification record](qa/VERIFICATION.md).

## Executive assessment

JK Michaels contains a substantial product foundation across learning, assessments, cohort operations, internships, commerce and administration. The main problem is incomplete integration between those parts. Several pages existed while their submitted routes or field names were wrong; some services represented intended behavior without completing it. The older parity report therefore overstated readiness.

The implemented batch fixes course and lesson authoring contracts, introduces a consistent workspace shell, establishes shared learning-access checks, improves assessment integrity, closes selected cross-account authorization gaps, and strengthens payment verification. These are real code changes with regression coverage. They do not complete tenancy, recurring billing, safe media hosting, curriculum versioning, accessibility conformance or production operations.

Priority definitions: **P0** blocks handling enterprise/private learner data or money; **P1** blocks a dependable enterprise release; **P2** is an enhancement or a later product-parity item. A partial fix retains its release blocker until the outstanding acceptance criteria pass.

## All-workspace coverage

The current route inventory contains **421 routes**, **80 routed controller files**, no unresolved controller actions and no missing literal Inertia page targets. The baseline recorded 417 routes and a missing campaign detail page. Route existence is weaker evidence than a functioning user journey.

| Workspace | Scope inspected | Implemented in this batch | Remaining enterprise gaps |
|---|---|---|---|
| Learner | Onboarding, catalog, enrollment, lesson player, quiz/mock exams, study practice, analytics, community, orders, notifications, certificates, internships, portfolio | Shared shell; access checks on lessons/assessments/AI context; safe article rendering; persisted assessment snapshot and deadline; private certificate access | Completion rules, full grading types, certificate issuance, offline reconciliation, accessibility and private community controls |
| Facilitator | Courses, curriculum, cohort membership, plans, assignments, grading, meetings, learner 360, interventions, AI studio, internship oversight | Working authoring routes; full shared lesson editor; thumbnail save; course review readiness; ownership checks on selected child resources | Immutable revisions, large-cohort performance, bulk operations, rubric moderation, actual notification delivery, external meeting integrations |
| SME reviewer | Pending queue, content library, review, versions, question bank, blueprint coverage, item analytics, flags | Shared shell; sanitized review content; pending-state/self-approval guards | Two governance paths disagree; question publication and comprehensive version snapshots need repair; calibrated item analytics unverified |
| Sponsor | Assigned interns, progress, submissions, reviews, scorecards, meetings | Shared shell; meeting ownership checks | End-to-end internship completion, certificate trigger, rubric validation and longitudinal report accuracy need coverage |
| Organization administrator | Role routing, organization/users/departments models, reports/ERP access | Dedicated organization-scoped overview and search; correct login destination; global ERP limited to platform admin | Organization invitations/imports, departments, program assignment, line managers, SSO/SCIM, billing, tenant report/export policies |
| Platform administrator | Content, approvals, users, billing, subscriptions, reports, settings, CRM/deals, campaigns, AI, audit logs, launch checklist | Shared shell; course contracts/readiness; billing data contract/currency aggregation; missing campaign detail page; selected payment security fixes | Durable settings, full audit capture, safe uploads, operational monitoring and accurate launch checklist evidence |
| Public/authentication | Portal, login, registration, reset/verification, public certificate/portfolio | Accessible login controls; deactivated account protection; removed inert organization sign-in button | Actual enterprise identity integration, authentication abuse controls, public-data minimization and realistic product claims |

All these workspaces are included in the audit. Automated workspace smoke coverage exercises 61 primary GET pages, plus organization and targeted authoring/detail routes. That does not mean all 421 routes were exercised with realistic data or every permission combination.

## Findings and current disposition

### A01 — Course creation fields conflict with the database — P1, repaired

`Admin/CourseController` accepted course types `free/premium/enterprise` and level `expert` although the migration accepts `self_paced/live/certified/bootcamp` and three levels. Facilitator forms submitted `discounted_price`, unsupported `cohort_based`, a blank free-course price and an unsaved duration. This can fail on the production SQL enum or silently lose information.

Both roles now submit canonical values, a zero free price and `discount_price`; duration persists and discount validation prevents a sale price above the base price. `CourseAuthoringTest` exercises create/update and rejected invalid values for both roles. SQLite tests cannot prove all MySQL behaviors, so a MySQL staging run is still required.

### A02 — Facilitator curriculum actions do not match routes — P1, repaired

The UI posted to `submit-for-review`, saved/deleted lessons without the module segment, submitted an unused `content_url`, and targeted a nonexistent thumbnail route. The editor offered a `quiz` lesson type absent from the lesson schema. Facilitators could not author an article properly through that interface.

Routes and payloads now match. Both roles use `Components/Admin/LessonEditor.vue` and `LessonAuthoringService`: text/video/audio/PDF/slides/mixed content, summary, transcript, key terms, visibility, download permission and scheduling. Facilitator create/edit routes enforce ownership and module/lesson binding. Thumbnail upload is validated and scoped. Failed deletions no longer disappear optimistically from the local curriculum. Questions remain a separate assessment workflow.

### A03 — Course governance is split and revisions are incomplete — P1, partial

`CourseReadinessService` now prevents publishing/submitting an empty or incomplete course. Approval metadata persists, self-approval is rejected, and facilitator-origin courses need approval before admin publication. Tests exercise submit → review → approve → publish.

However, `Admin/CourseApprovalController` and `ContentWorkflowService` remain separate state machines. The generic publisher writes course-like attributes to other content models; questions use `status`. Version snapshots do not reliably freeze the full curriculum and its child resources. Admin editing can affect published content, and facilitator messages mention recall/new versions without a complete revision flow. Implement one transactional lifecycle with immutable published revisions, reviewers, diffs, archive and explicit recall.

### A04 — Learning entitlement was enforced inconsistently — P0, partial

Shared `LearningAccessService` now checks publication, active/completed enrollment, module/lesson visibility, previews and release scheduling across lesson access, progress, downloads, SCORM commits, quiz/mock entry and AI lesson/course context. Tests reject unenrolled, suspended, hidden and unreleased access.

This does not make public storage private. Notes, bookmarks, resources, analytics and all other related reads/writes still need a complete entitlement-policy matrix. The learner dashboard can still list an enrolled but unpublished course; the access guard blocks its lesson access, but discovery should also respect publication. Prerequisite text is not an enforced prerequisite graph.

### A05 — Assessment attempts could leak answers or change under the learner — P0/P1, partial

New attempt snapshots preserve question assignment and answer keys; public API payloads exclude correctness; resume uses the same questions; answers must belong to the attempt; server deadlines apply; unanswered assigned questions count in the denominator; review is unavailable before completion. Submitted API attempts use transactional locking. Legacy in-progress attempts without snapshots are abandoned and restarted explicitly.

Open: `Question::isCorrect()` sorts all answers, so ordering cannot be graded correctly. A nine-type question UI is not nine complete grading strategies. Difficulty distribution, manual grading, accommodations and true pause semantics remain incomplete. Attempt-start/max-attempt concurrency, web submit races, repeated-answer statistics and question-deletion retention require tests and design. Migration: `2026_10_04_000001_preserve_assessment_attempt_questions.php`.

### A06 — Cross-account child resources lacked consistent checks — P0, partial

Ownership/binding checks were added for sponsor meetings, facilitator cohort members and learner 360, assignments/submissions, weekly-plan items, meetings, simulation attempts and AI course generation. Organization administrators can no longer enter the global ERP workspace. Deactivated users are rejected at login and existing sessions are terminated.

This is not a completed tenant-isolation audit. In particular, `ForumController` detail/reply/search paths do not apply the membership filtering used by the forum index; guessed private forum/thread IDs can bypass that index filter. Review study groups, direct messages, evidence downloads, exports, AI jobs and every nested mutation using adversarial multi-organization fixtures before release.

### A07 — Payment callbacks needed fail-closed verification — P0, partial

Paystack, Flutterwave and Stripe webhooks now fail closed when secrets/signatures are absent or invalid. Stripe signature timestamps have a five-minute tolerance. Payment confirmation checks reference, amount and currency, locks payment/order records and makes supported order fulfillment idempotent. The legacy callback checks order ownership and server-side totals. The actual Paystack webhook path is exempted from browser CSRF checks. Tests cover invalid signatures, stale Stripe signatures, mismatched amounts and duplicate fulfillment.

Open: recurring subscription callbacks can repeat provider/local subscription creation; paid-plan entitlements, renewal/cancellation/reconciliation and transactional outbox behavior remain incomplete. Direct manual activation of paid plans is now rejected. Provider refunds are not implemented; the admin action now reports that limitation instead of changing local status as if a refund occurred. Tax invoices, refund settlement, coupon concurrency and alternate-reference duplicate payments remain release work. No real payment was made in verification.

### A08 — Uploads and SCORM delivery are unsafe for enterprise use — P0, open

`Admin/CourseMediaController::uploadVideo` trusts chunk metadata and appends with a text-oriented append helper, risking binary corruption and upload collisions. `uploadScorm` extracts archives into public storage without explicit entry/path/type/expanded-size controls, and active package HTML is served on the application origin. Public resource storage also bypasses learner entitlements.

Required: authenticated upload sessions, bounded chunks, content checks and binary assembly; quarantine and malware scanning; ZIP entry traversal/symlink/type/size controls; manifest validation; private object storage with short-lived access; isolated SCORM origin and restricted bridge. Test malicious packages, duplicate/out-of-order chunks, interrupted uploads and unauthorized reads. This batch does not claim safe SCORM compliance.

### A09 — Progress and completion do not meet the specification — P1, open

`LessonController` and `ScormController` implement completion independently. Ordinary progress/SCORM messages can regress completed state or rewrite timestamps. Course denominator queries can include lessons in unpublished modules. The feature specification's practice/check threshold is not a dependable completion gate. Certificate issuance helpers exist but are not called by completion controllers; downloadable certificate output is HTML rather than a generated PDF.

Create one idempotent completion service driven by explicit course rules, published curriculum revision, assessment results and completion events. Issue/revoke certificates once, retain evidence, render real PDF/QR artifacts and verify their public payload is minimal.

### A10 — Organization workspace is a foundation, not enterprise tenancy — P0/P1, partial

The new `/organization` view restricts people and enrollment totals to the authenticated organization and supports scoped search. This repairs the former role redirect and avoids exposing platform administration. There is still no complete organization administration journey matching DOCX P371–418. Tenant-aware imports, invitations, departments, manager access, program assignments, seats, exports, retention and billing need implementation with policies, not just navigation.

### A11 — Platform settings acknowledge changes without saving — P1, open

`Admin/SettingsController::update` validates inputs and returns success without persistent settings writes. The index supplies several hard-coded enabled/default values. Administrators cannot rely on these controls for registration, verification, retention or notification policy. Add typed persistent settings, actual enforcement and tests that reload after save; until then the screen should clearly identify controls as unavailable.

### A12 — Audit logs do not establish an audit trail — P1, open

`AuditService` and audit log pages exist, but searching application callers did not find systematic writes from mutation controllers. A viewer cannot prove who changed entitlements, grades, settings or published content. Capture relevant domain events transactionally, redact secrets, include tenant/actor/correlation IDs, define retention and test tamper restrictions.

### A13 — Notifications and campaigns overstate delivery — P1, open

`NotificationService` stores notification data and proposed channel names; that does not send a push or generic email. Digest code and provider delivery need completion. Campaign execution requires queued, idempotent delivery with per-recipient results, opt-in, quiet hours, frequency limits, retry/backoff and cancellation. The new campaign detail page fixes a missing view, not the delivery system. No campaign or external message was sent during this work.

### A14 — Offline support needs secure synchronization — P0/P1, partial

Service-worker v6 stops caching authenticated/API responses and public storage wholesale, clears previous application caches and retains failed sync requests instead of discarding them. This reduces shared-device data exposure.

The queued `/api/progress/sync` contract is not implemented, quiz sync is incomplete, and account-scoped downloaded data/revocation/conflict semantics are unverified. PWA installability is not full offline learning. Capacitor configuration is not evidence of tested native builds, push, crash reporting or the DOCX device budgets.

### A15 — UI shells and controls are inconsistent — P1, partial

Implemented a common `WorkspaceShell`, role-aware searchable navigation, working destinations, mobile drawer/quick navigation, skip link, focus states, flash/error summary and a dedicated organization overview. Replaced duplicated layouts for the primary roles. The new lesson editor groups details, content, reinforcement and publishing settings; includes preview and unsaved-change protection. Shared inputs now pass attributes to the actual input and connect labels/errors. DOMPurify sanitizes selected article/transcript/review rendering. Login no longer offers an inert SSO button.

Open: consistent tables/filter states, empty states across data-heavy screens, meaningful bulk actions, accessible dialogs everywhere, rich text authoring beyond basic HTML, media uploads/previews, large-list performance, contrast across legacy dashboards, full keyboard/screen-reader audit, screen-specific error recovery, localization and dated marketing claims. This is a professional UI foundation and a redesigned authoring journey, not a complete redesign of every screen.

### A16 — Analytics and gamification need correctness evidence — P1, open

Readiness/mastery, error patterns, cohort health, gradebook, leaderboards and reports are implemented at varying depths. Empty-data HTTP success does not establish score calibration or export accuracy. Repeated answers, sparse datasets, zero denominators, manual grades, late submissions, currency and time-zone boundaries need deterministic fixtures and reconciliation. Validate recommendations before using them for consequential learner judgments.

### A17 — Dependencies improved; operational readiness unproven — P1, partial

PHP and JavaScript lockfiles were updated within the existing stack, and dependency audits were rerun. No dependency scan proves absence of application vulnerabilities. The repository-wide PHP style baseline was cleaned and Pint now passes. CI still needs a MySQL job and frontend behavior checks. Production queue workers, scheduler, object storage, email, provider secrets, backups/restore drills, observability, incident response, load targets, accessibility and mobile packaging were not verified against an actual environment.

## UI/UX audit and acceptance criteria

| Journey | Observed issue | Implemented direction | Acceptance still required |
|---|---|---|---|
| Find work | Different sidebars, wrong paths, unclear role identity | Shared grouped search and real role switching | Task testing with each persona; discoverability of cohort-specific tools |
| Create course | Invalid options and failed/ignored saves | Canonical choices, bound labels, validation summary | Guided creation, draft recovery, bulk authoring, rich descriptions |
| Build curriculum | Broken facilitator URLs and generic content field | Scoped routes and shared full lesson editor | Reordering, cloning, reliable upload flow, concurrent editing and revision comparison |
| Publish | A button without a dependable readiness contract | Saved-content checklist and approval checks | Integrated SME lifecycle, preview as learner and immutable releases |
| Learn | Inconsistent entitlement gates; unsafe HTML | Shared access checks and sanitized article view | Keyboard/player controls, captions/transcripts quality, completion rules, offline recovery |
| Assess | Fragile resume and misleading scores | Snapshot, server deadline and assigned-question denominator | Every question type, accommodations, pause behavior, autosave status and network failures |
| Operate cohorts | Many detail pages, limited evidence of happy/error paths | Shared shell and selected object scope fixes | Realistic cohorts, rubric grading, resubmission, meetings and bulk feedback |
| Administer an organization | No dedicated scoped landing | Scoped people/learning overview | Invitations, department/program assignments, seats, reports and SSO |
| Buy/manage billing | False refund feedback and incomplete recurrence | Verified one-off payment checks; truthful refund limitation | Provider sandboxes, subscription ledger, invoices/tax, refunds and reconciliation |
| Use mobile | Dense navigation and inconsistent controls | Drawer, quick links, stacked lesson editor, touch sizing | All key tasks at 360/390/768 px, zoom, screen reader and offline devices |

Manual browser checks covered actual Laravel pages at `127.0.0.1:8900`, backed only by a synthetic SQLite database under ignored `tmp/`. The old static `8898/lesson-editor.html` is not evidence of application behavior. Screenshots in `docs/qa/` show the running implementation. Mobile lesson layout was checked at 390×844 with no document-level horizontal overflow and an operable navigation drawer. This is not a WCAG 2.2 AA certification.

## Release recommendation

Keep the application in controlled development/staging. Complete the P0 gates in the implementation plan before using real enterprise learner data or enabling production billing. Then validate one full journey for each persona on the intended database and services, with explicit evidence for failures and unauthorized access as well as success. Feature parity and infrastructure readiness must be tracked separately.

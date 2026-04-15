# JK Michaels Platform - Market Readiness Roadmap

**Prepared:** April 13, 2026
**Scope reviewed:** `docs/01-07`, `docs/GAP-ANALYSIS-REPORT.md`, `docs/09-MASTERSTUDY-FEATURE-PARITY-MATRIX.md`, `docs/10-SPRINT-BACKLOG.md`, `composer.json`, `package.json`, `routes/web.php`, `tests/*`, `capacitor.config.json`

## 1. Executive Summary

The project already has strong breadth. It covers the major LMS domains: courses, lessons, quizzes, mock exams, analytics, gamification, internship workflows, sponsor review, storefront, community, certificates, and admin tooling.

The current problem is not lack of ideas. It is the gap between:

1. the platform described in the documentation,
2. the UI surface already scaffolded in Vue, and
3. the backend, QA, operational, and commercial maturity required for a real launch.

The fastest path to a market-ready product is:

1. close the highest-value product gaps that affect acquisition, activation, retention, and reporting,
2. install hard delivery gates around testing, CI, monitoring, payments, and data integrity,
3. reduce documentation drift so the team is building from one source of truth.

## 2. Current-State Reality Check

Observed from the repo:

| Area | Observed state | Market-readiness implication |
|---|---|---|
| Backend stack | Laravel 12, PHP `^8.2`, Sanctum, Spatie Permission, Inertia | Solid base, but current dependencies do not yet match the full platform described in docs |
| Frontend stack | Vue 3, Vite, Pinia, Chart.js, Plyr | Good foundation, but no evidence yet of PWA plugin, test tooling, or UI quality gates |
| Route surface | Very broad route coverage in `routes/web.php` | Breadth is high, but breadth without completion increases defect risk |
| Docs vs code | Docs target Redis, Horizon, Reverb, S3, Meilisearch, multi-provider payments, monitoring, CI/CD | These are not yet reflected in current package dependencies or repo structure |
| Testing | 5 test files total | Coverage is too thin for a launch candidate |
| CI/CD | Baseline CI now exists in `.github/workflows/ci.yml` | Quality gates have started, but coverage and deployment automation are still too thin for launch confidence |
| Mobile/PWA | `capacitor.config.json` exists | Mobile intent is present, but installability/offline/push hardening is not yet proven |
| Governance | Gap analysis already identifies many partial or stub pages | The platform needs a strict release scope, not more uncontrolled surface area |

## 2A. MasterStudy Benchmark Position

This roadmap now uses the benchmark audit in `docs/09-MASTERSTUDY-FEATURE-PARITY-MATRIX.md` as the explicit translation of `MasterStudy LMS Features vs. Your Current Project.docx`.

Benchmark summary:

| Status | Count |
|---|---:|
| Verified | 28 |
| Partial | 14 |
| Missing | 17 |

Important benchmark conclusions:

- The benchmark docx is directionally useful, but no longer accurate as a source of truth.
- Several features it marks as "implemented" are only partial in the repo today.
- Several features it marks as "missing" now have meaningful foundations in code, especially AI tooling, subscriptions, memberships, SCORM, and meeting-platform support.
- The latest implementation pass closed several launch-critical gaps: learner ratings, certificate download, SCORM learner delivery, drip scheduling, and facilitator gradebook.

What this changes in practice:

- The launch plan should not treat MasterStudy parity as a single "feature-add" backlog.
- The first priority is to finish partial systems already in the codebase.
- The second priority is to close the highest-value missing benchmark features:
  - subscriptions and memberships if recurring revenue matters at launch,
  - taxes and trials if public ecommerce is in launch scope,
  - gradebook and manual enrollment if facilitator-led delivery is core,
  - drip content, course expiry, course trailer, and SCORM completion if content delivery quality is a differentiator.
- The sprint-level execution order now lives in `docs/10-SPRINT-BACKLOG.md`.

## 3. Strategic Gaps To Close

These are the gaps that most directly block a credible market release.

### A. Product gaps

- Onboarding does not yet deliver the adaptive setup flow described in the product docs.
- Learner dashboard intelligence is incomplete: focus areas, countdowns, pass-likelihood cues, and stronger next-step guidance are missing.
- Course discovery is not strong enough for conversion: advanced filters, sorting, reviews, and resources are incomplete.
- Lesson experience is missing several important media features and learning accelerators.
- Mock exam reporting is not yet differentiated enough to justify premium positioning.

### B. Admin and B2B gaps

- Admin dashboard and reports are not at executive-ready quality.
- Sponsor and SME overview dashboards are incomplete or missing.
- Facilitator 360 and intervention tooling need stronger decision support.
- Audit and compliance reporting are not yet launch-grade.

### C. Commercial gaps

- Storefront exists, but commercial readiness requires hardened checkout, webhook handling, receipts, failed-payment recovery, and reconciled order states.
- Social proof and conversion assets are incomplete.
- Packaging strategy is not yet explicit: single-course, cohort, bootcamp, internship track, enterprise seat sales.

### D. Platform and operations gaps

- CI exists, but it is still minimal and does not cover the full critical path.
- Very limited automated test coverage.
- Documentation claims exceed implemented platform capabilities.
- Monitoring, observability, queue strategy, backups, and incident handling are not yet codified in repo-level workflows.

### E. Launch discipline gaps

- There is no explicit beta gate, launch gate, or acceptance checklist tied to business-critical user flows.
- The current execution plan is broad, but not yet converted into release-control criteria.

## 4. Recommended Delivery Order

Do not try to finish every documented module before launch. Ship in layers.

### Phase 0 - Truth and Delivery Foundation

Objective: make the repo trustworthy and shippable.

Must complete:

- Expand CI for install, build, and test enforcement.
- Expand automated coverage around auth, enrollment, quiz attempts, mock attempts, purchases, certificates, and role access.
- Align docs with the actual implemented stack and mark aspirational items clearly.
- Define seeded demo data for stakeholder reviews.
- Add error logging/monitoring plan and environment checklist.
- Decide the initial launch scope and freeze non-essential modules.

Exit criteria:

- Every pull request has automated checks.
- Critical learner/admin flows have regression coverage.
- The team can state exactly which modules are production-ready and which are deferred.

### Phase 1 - Conversion and Retention Core

Objective: improve the parts of the product users touch first and most often.

Must complete:

- Full onboarding flow per spec.
- Learner dashboard focus areas, exam countdown, and pass-likelihood messaging.
- Course catalog advanced filters and sorting.
- Course detail reviews/resources tabs.
- Lesson experience upgrades: transcript, chapters, speed controls, flashcard actions.
- Mock exam report upgrades: confidence analysis, weakness clusters, seven-day plan.

Exit criteria:

- A new learner can register, onboard, choose a path, enroll, learn, take assessments, and understand what to do next without facilitator help.
- Course pages are credible enough to convert paid traffic.

### Phase 2 - Admin, Sponsor, and SME Readiness

Objective: make the platform operational for internal teams and enterprise buyers.

Must complete:

- Real admin dashboard metrics and charts.
- Report pages with export support.
- Sponsor dashboard and meeting hub.
- SME dashboard and version-diff workflow.
- Facilitator Learner 360 completion.
- Gradebook and manual enrollment if facilitator-led cohorts are part of the launch offer.
- Audit log detail views and role-aware oversight.

Exit criteria:

- A platform admin can monitor adoption and commercial performance.
- A sponsor can review interns without manual workarounds.
- An SME can govern question/content quality with traceability.

### Phase 3 - Revenue Hardening

Objective: make monetization reliable and supportable.

Must complete:

- Harden checkout and webhook replay safety.
- Reconcile order lifecycle states end to end.
- Complete subscriptions and memberships if launch pricing depends on recurring access.
- Add taxes and trial logic if self-serve public sales are in scope.
- Add receipts/invoices and clearer payment history.
- Ensure certificates and completion rules are deterministic.
- Define launch packaging and pricing model in-product.

Exit criteria:

- A payment can be initiated, confirmed, retried, refunded, and audited without data drift.
- Completion and certificate issuance are trusted by support and customers.

### Phase 4 - Mobile, Offline, and Performance

Objective: make the platform resilient on real learner devices.

Must complete:

- Complete SCORM learner integration if imported packages are part of launch content.
- Real PWA installability and offline strategy.
- Download manager backed by actual storage behavior.
- Push notification strategy.
- Performance profiling against documented budgets.
- Capacitor smoke test flows for Android.

Exit criteria:

- Core learning flows remain usable on constrained mobile networks.
- Android packaging is not just configured; it is verified.

### Phase 5 - Launch Hardening

Objective: reduce launch-day surprises.

Must complete:

- UAT across learner, facilitator, sponsor, SME, admin.
- Backup and restore verification.
- Queue failure and payment failure drills.
- Support playbook for account, payment, certificate, and access issues.
- Production analytics dashboard for business and support teams.

Exit criteria:

- The team has signed off on operational response, not only feature completion.

## 5. Priority Build List

This is the recommended implementation order for the next work cycle.

1. CI expansion and enforcement
2. Critical-path automated tests
3. Full onboarding rebuild
4. Learner dashboard intelligence upgrade
5. Course catalog filters/sorting
6. Course detail reviews/resources
7. Lesson media upgrades
8. Mock exam report upgrade
9. Admin dashboard repair
10. Admin reports
11. Sponsor dashboard + meeting hub
12. SME dashboard + version diff
13. Commerce hardening
14. PWA/offline hardening
15. Launch/UAT checklist enforcement

### Benchmark Addendum

These are the MasterStudy-driven items that should be consciously decided, not left implicit:

- `Pre-launch if recurring revenue matters:`
  - subscriptions
  - memberships
  - taxes
  - trials
- `Pre-launch if facilitator-led delivery is core:`
  - gradebook
  - manual enrollment
  - progress reset
- `Pre-launch if course delivery parity is part of the sales narrative:`
  - drip content
  - course expiry/pause
  - dedicated course player
  - course trailer
  - SCORM completion
- `Post-launch by default unless there is a direct business case:`
  - built-in live streaming
  - native Zoom/Google Meet provisioning
  - Google Classroom integration
  - external audio providers
  - wishlist
  - drag-and-drop certificate builder

## 6. What To Defer Until After Launch

These should not delay the first market-ready release unless they are core to the business model:

- Deep forum/community expansion
- Advanced study-group experiences
- Employer-facing public portfolio marketplace
- Complex AI tutoring flows
- Heavy enterprise integrations like SSO/SCIM
- Full SCORM/xAPI interoperability

## 7. Release Gates

### Beta gate

- Registration, login, onboarding, enrollment, lesson completion, quiz attempt, mock attempt, storefront checkout, certificate view, and admin reporting all work end to end.
- CI is green.
- Critical defects are triaged and reproducible.
- Seeded demo accounts exist for every role.

### Launch gate

- Payment webhooks are idempotent.
- Role access rules are verified.
- Certificate issuance rules are deterministic.
- Backups and restore have been tested.
- Monitoring and alerting are configured.
- UAT signoff exists for learner, facilitator, sponsor, SME, and admin journeys.

## 8. Notes On Benchmarking

The benchmark for this roadmap is now explicitly grounded in:

1. `MasterStudy LMS Features vs. Your Current Project.docx`,
2. `docs/09-MASTERSTUDY-FEATURE-PARITY-MATRIX.md`, and
3. `docs/10-SPRINT-BACKLOG.md`

The roadmap should therefore be read together with the parity matrix and sprint backlog, not as a standalone feature list.

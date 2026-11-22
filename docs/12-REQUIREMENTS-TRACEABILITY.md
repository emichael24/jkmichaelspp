# JK Michaels requirements traceability

Audit date: 5 October 2026. Companion to [the audit](11-ENTERPRISE-AUDIT-2026-10-05.md) and [execution plan](13-IMPLEMENTATION-AND-RELEASE-PLAN.md).

**Status discipline:** `Partial` means code exists but the full requirement is incomplete or insufficiently tested. `Present` means a narrower feature has identifiable implementation; it is not production certification. `Missing` means no meaningful implementation was identified in this inspection. `Unverified` means external/runtime evidence was unavailable. Test evidence is stated separately. Do not convert these categories into a single enterprise-readiness percentage.

## Source register

| Source | Role in this audit | Reconciliation |
|---|---|---|
| `JK Michaels Integrated App Specification.docx` | Product/persona/workflow and nonfunctional requirements | XML paragraph locators below refer to the extracted document paragraph sequence, including gaps for empty paragraphs |
| `MasterStudy LMS Features vs. Your Current Project.pdf` | 28 existing-feature claims and 31 gap claims | All 59 assessed below; PDF pages cited; embedded closing suggestions are not requirements authorization |
| `docs/01-PROJECT-OVERVIEW.md` | Product goals and roles | Compared with real role middleware, navigation and workspaces |
| `docs/02-DATABASE-SCHEMA.md` | Intended entities and relations | Actual migrations/models are implementation evidence; enum and field mismatches identified |
| `docs/03-API-SPECIFICATION.md` | Intended API contracts | Actual route inventory is authoritative for what is callable; documented endpoints do not prove implementation |
| `docs/04-FEATURE-SPECIFICATIONS.md` | Detailed feature acceptance criteria | Section-by-section reconciliation below |
| `docs/05-FRONTEND-ARCHITECTURE.md` | Frontend structure and components | Compared with Vue/Inertia pages, shared inputs, layouts and lesson player |
| `docs/06-DEPLOYMENT-DEVOPS.md` | Infrastructure and operations target | Subsequent TLS/SMTP, same-host restore, SQL-copy migration, isolated HTTP/queue and scanner/parser probes are recorded; rollout, load and monitoring remain |
| `docs/07-EXECUTION-PLAN.md` | Original build sequence | Historical plan, not a completion ledger; superseded for release gating by document 13 |
| `docs/08-MARKET-READINESS-ROADMAP.md` | Later readiness plan | Reconciled with current defects and verified remediation |
| `docs/09-MASTERSTUDY-FEATURE-PARITY-MATRIX.md` | Earlier code-only parity audit | Overclaims on certificates, safe SCORM, notifications and operational maturity are qualified below |
| `docs/10-SPRINT-BACKLOG.md` | Earlier backlog | Retained as historical input; current release gates are in document 13 |
| `docs/CAPACITOR-SETUP.md` | Mobile packaging instructions | Setup instructions are not tested device artifacts |
| `docs/GAP-ANALYSIS-REPORT.md` | Historical gap analysis | Revalidated using current source and targeted tests, not inherited green checks |

Exact input hashes are preserved in [qa/requirement-sources.json](qa/requirement-sources.json). Requirement extracts are local audit scratch; the original documents remain unchanged.

## Integrated specification: every major requirement area

| ID | Source / requirement | Status after remediation | Implementation evidence and remaining gap | Release work |
|---|---|---|---|---|
| S01 | DOCX P16–34: learner, facilitator, SME, organization admin, intern, sponsor, optional managers/leads | Partial | Role middleware and shared navigation cover six main workspaces; intern is a learner journey. Organization people, invitations, departments and reports are implemented. Manager/team-lead program permissions remain | E04 |
| S02 | P50–86: onboarding, goals, baseline, daily plan, quick win, continue learning, coach | Partial | `OnboardingController`, `DashboardController`, onboarding/dashboard pages. No integrated test proves diagnostic personalization, plan changes and notification follow-through | E09 |
| S03 | P88–125: course discovery, multi-format learning, resources, notes, offline | Partial | Both authoring roles, private media, protected PDF/PPTX reader and encrypted reading-only offline sync are implemented. Full player accessibility, search index, device and large-media acceptance remain | E02/E03/E07 |
| S04 | P127–159: assessments, confidence, blueprints, adaptive behavior, pause, analytics | Partial | Immutable assessment snapshots/deadlines, nine grading strategies, exact domain/difficulty quotas and pause are implemented and tested. Formal accommodations, cross-device ordering and browser coverage remain | E05 |
| S05 | P127–159: verified certificates/QR | Partial | Course and reviewed-internship completion use unique credential identities and private PDF/QR verification. Latest-version rubric review, all-milestone coverage and replay protections are tested; historical reconciliation remains | E06 |
| S06 | P162–178: simulations, assignments, rubrics, resubmissions | Partial | Versioned internship submissions, bounded rubric scoring and stale-review rejection are tested. Simulation/assignment paths exist; broader branching, private evidence uploads and full assignment rubric coverage remain | E08 |
| S07 | P180–215: mastery and spaced repetition | Partial | `MasteryService`, `SpacedRepetitionService`, practice pages. Deterministic algorithm, interleaving and source-to-review coverage needed | E09 |
| S08 | P180–215: error patterns and targeted remedies | Partial | `ErrorPatternService` exists. Minimum samples, time/confidence classification and appropriate linked interventions unverified | E09 |
| S09 | P180–215: scoped AI coach with citations/content QA | Partial | AI controllers/services and access checks exist. External provider, retrieval/citations, model evaluation, cost limits, privacy and hallucination defenses unverified | E10 |
| S10 | P218–238: XP, badges, streaks, missions, ranking | Partial | Tenant-scoped ranking and a unique reward-event ledger prevent repeat completion/daily XP. Broader anti-abuse, mission criteria and time-zone boundaries remain | E09 |
| S11 | P218–238: progress, readiness, learner/manager dashboards/reports | Partial | Gradebook course/tenant boundaries, empty-assessment cases and deterministic latest-attempt scores have a two-organization regression. Wider analytics calibration, grade/export reconciliation and load acceptance remain | E04/E09 |
| S12 | P240–260: moderated community, groups and messages | Partial | Forum/group/message access and tenant boundaries were strengthened and adversarial fixtures added. Complete legacy membership/export and real-time/moderation acceptance remain | E01 |
| S13 | P240–260: calendars, events, meetings | Partial | Event/facilitator/sponsor meeting flows; selected binding checks repaired. Provider calendar/conference integrations and delivery unverified | E08/E10 |
| S14 | P262–268: commerce, three providers, bundles, discounts, internship purchase | Partial | Configured-provider checkout, draft-product/bundle guards, coupon locking, duplicate/pending-order recovery and a provider-verified full-refund ledger are implemented. Real test subscription confirmation/link/cancellation passed; recurring-refund policy, inbound webhook/renewal acceptance, tax/invoices and broader provider parity remain | E03 |
| S15 | P270–300: inbox, email/push, preferences, quiet hours, frequency, opt-in | Partial | Encrypted durable outbox, preferences/quiet hours, daily/weekly digest, consent, delivery limits and campaign audience snapshots are implemented. One authorized SMTP message reached the inbox; live queued/push/sequence acceptance remains | E11 |
| S16 | P302–310: WCAG 2.2 AA, dyslexia/high contrast/color settings, accommodations | Partial | Shared labels, skip navigation, focus, reduced motion and responsive editor improved. No full assistive-technology audit or accommodations engine | E12 |
| S17 | P302–310: downloads and offline behavior | Partial | Encrypted account/session/tab-isolated readings, 24-hour leases, quotas, authenticated replay-safe sync and logout cleanup are implemented. Browser save/read/sync and automated isolation/revocation tests pass; actual disconnected devices/native packaging remain | E07 |
| S18 | P312–369: internship tracks, boards, tasks and evidence history | Partial | Versioned learner submissions, sponsor rubric approval, all-milestone completion, retained evidence and computed scorecards are implemented and tested. Browser learner-to-sponsor journey passed. Private file uploads and complete device/portfolio acceptance remain | E08 |
| S19 | P312–369: sponsor scorecards, meetings, portfolios and completion credential | Partial | Sponsor review and scorecard scales, server-computed averages, all-task/all-milestone credential eligibility and tenant assignment rejection are tested. Historical reconciliation, private evidence upload and complete meeting/portfolio acceptance remain | E06/E08 |
| S20 | P371–418: organization people, bulk import, departments, deactivation | Partial | Implemented locally: scoped invitations/CSV, one-time acceptance, global active-seat and reserved-seat checks, departments/manager contacts, member deactivation and session/token cleanup. Browser and SQLite/MariaDB evidence recorded; production rollout pending | E04 |
| S21 | P371–418: SSO, SCIM, HR integration | Deferred by user | Organization SSO explicitly deferred by the user; no provider or operational SAML/OIDC/SCIM lifecycle claimed | E04/E10 |
| S22 | P371–418: programs, prerequisites, deadlines, managers and cohort assignments | Partial | Enforced prerequisite graphs survive revision cloning and gate enrollment, checkout and private learning access; circular and unpublished dependencies are rejected. Organization program assignment/licensing/deadlines and manager permissions remain | E04/E06 |
| S23 | P371–418: org reporting, retention, export, branding, billing | Partial | Organization-filtered reports and formula-safe CSV exports are implemented and tested. Typed platform settings persist; tenant branding/billing, complete retention and broader report/export parity remain | E04/E13 |
| S24 | P420–467: facilitator cohort health, learner 360, plans, assignment authoring | Partial | Facilitator controllers/pages; selected child scope checks repaired. Full-plan execution with realistic learner data unverified | E08/E09 |
| S25 | P420–467: nudges, rubric grading, meetings and internship oversight | Partial | Durable notification dispatch is implemented; production worker activation and provider meetings remain. Rubric/gradebook correctness still needs broader fixtures | E08/E11 |
| S26 | P469–475: sponsor assigned-learner access and reviews | Partial | Sponsor submission/scorecard authorization now enforces assigned learner and tenant, blocks self-review, preserves submission versions and rejects stale decisions. Tests and browser rubric review pass; complete endpoint matrix remains | E01/E08 |
| S27 | P477–493: Draft→Review→Approve→Publish→Version→Archive | Partial | Unified governance, immutable deep curriculum snapshots, separate review, recall and draft-edition cloning are implemented. Full diff/archive UX and publication concurrency remain | E02 |
| S28 | P477–493: question tagging, flags, blueprints, item-quality analytics | Partial | Question definitions, publication guards, exact blueprint selection and fractional domain statistics were repaired. Calibrated item-quality analytics and alerting remain | E05/E09 |
| S29 | P495–522: audit/versioning, SCORM/xAPI, proctoring/conferencing | Partial | Transactional redacted domain auditing, private scanned uploads and constrained SCORM are implemented. Production scanner and isolated PDF/PPTX parser probes pass; application rollout, full audit coverage, xAPI/LRS and proctoring remain | E02/E10/E13 |
| S30 | P495–522: privacy export/delete, retention, incident response | Missing / unverified | No complete tested subject-data export/delete/retention workflow or operational drill identified | E13 |
| S31 | P524–545: minimum screens, MVP/V1 and pricing tiers | Partial | Broad page coverage; corrected shell/navigation. Missing business capabilities must not be marked present based on screens alone | All gates |
| S32 | P553–576: cold start <3 s, TTI <500 ms, mobile <100 MB, profiling/crash/offline conflicts | Unverified | Build succeeds; no representative device performance/crash/size evidence. Bundle size is not TTI | E07/E14 |
| S33 | P578–589: acquisition/campaigns and operational communication | Partial | Campaign drafts, audiences, durable dispatch, consent, quiet hours, deduplication, retries and ambiguous outcomes are implemented. Live queue activation, provider sequences and attribution remain | E11 |

## Detailed `docs/04` acceptance reconciliation

| Section | Result | Missing proof or implementation |
|---|---|---|
| 1 Onboarding | Partial | Diagnostic-to-plan personalization and return-user transitions |
| 2 Home dashboard | Partial | Correct prioritization, daily-plan completion, meaningful recommendations on sparse data |
| 3 Courses and content | Partial, repaired authoring | Prerequisite graph, indexed discovery, large-media compatibility and full authoring acceptance; private media and revision workflows now implemented |
| 4 Assessment engine | Partial, integrity improved | Formal accommodations, every-type browser journey and cross-device/concurrency coverage; grading, exact difficulty quotas and pause now implemented |
| 5 Spaced repetition | Partial | SM-2 boundary tests, failure-generated review items, interleaving and graduation |
| 6 Error patterns | Partial | Minimum-sample rules and actionable remedies demonstrated against fixtures |
| 7 Analytics | Partial | Accurate, reconciled totals and report exports across time zones and roles |
| 8 Gamification | Partial | Broader award/mission criteria, abuse controls and time-zone boundaries; repeat-event ledger and tenant ranks now implemented |
| 9 Facilitator tools | Partial, scope fixes | Realistic cohort journey, bulk tools, notifications and revision editing |
| 10 Internship | Partial, reviewed completion implemented | Private evidence uploads, full meeting/portfolio acceptance, historical reconciliation and representative concurrency; submission/review/credential journey now verified |
| 11 Simulations | Partial | Complete branch authoring, attempt state and scoring/debrief tests |
| 12 Certificates | Partial, identity protected | Historical reconciliation, broader course rules and representative concurrency; course and reviewed-internship completion now tested with unique credential identity and private PDF/QR |
| 13 Commerce | Partial, payment checks improved | Checkout recovery/coupon locking and full Paystack refund reconciliation implemented; real test subscription confirmation/cancellation and processed refund verified. Inbound webhook/renewal cycle, recurring-refund policy, tax/invoices remain |
| 14 Notifications | Partial | Production queued/push acceptance, provider/device failures and sequence parity; durable dispatch/digest/preferences/consent implemented |
| 15 Meetings | Partial | Provider OAuth/webhooks/recordings/attendance integration |
| 16 Community | Partial | Membership on every detail/mutation/search and moderation |
| 17 Pathways | Partial | Enforced prerequisites, conditional sequencing and completion rules |
| 18 Admin | Partial | Complete audit/bulk-write coverage, tenant controls, retention and deployment; persistent settings and private/scanned files implemented |
| 19 SME | Partial | Complete content diff/archive UX and calibrated item analytics; unified review/revisions implemented |
| 20 Accessibility/offline | Partial | Assistive-technology testing, accommodations and disconnected/native-device acceptance; encrypted readings and replay/revocation-safe sync implemented |

## MasterStudy PDF: the 28 “already implemented” claims

Pages 1–3 contain this group. Status below is the current assessment, not the PDF's label.

| ID | Feature | Current status | Evidence / qualification |
|---|---|---|---|
| M01 | Course builder | Partial, repaired core flow | Admin/facilitator course setup, shared rich-text lesson studio, validation, private media and revisions are implemented and browser-checked; large curricula and full provider acceptance remain |
| M02 | Multi-format lessons | Partial | Text, hosted/native video, audio, PDF, PPTX and mixed authoring/player paths exist; protected document rasterization/watermarks tested. Full accessibility and real media/provider acceptance remain |
| M03 | Nine quiz types | Partial | Nine strategy contracts, fractional grading, ordering/matching and numeric tolerance are implemented; snapshot, deadline and blueprint tests pass. All-type browser/accommodation coverage remains |
| M04 | Assignments/rubrics | Partial | Facilitator assignment and grading flows; rubric/scoring/resubmission proof incomplete |
| M05 | Certificates | Partial | Automatic course and reviewed-internship completion, database-unique credentials, private PDF/QR and minimal verification are tested; historical reconciliation and representative concurrency remain |
| M06 | Progress | Partial | One monotonic completion service, published-curriculum denominator, required quiz checks and replay-safe SCORM/offline completion are tested; broader configurable criteria remain |
| M07 | Ratings/reviews | Present, limited verification | `CourseRatingController`, `CourseRating.vue`, course page; moderation and abuse cases not exhaustively tested |
| M08 | Categories/filtering | Present, limited verification | Category/course controllers and catalog; no Meilisearch autocomplete proof |
| M09 | Difficulty levels | Present, repaired contract | Three canonical levels now agree across authoring UI/validation/schema; tests reject `expert` |
| M10 | Duration | Present, repaired contract | Course and lesson durations persist for both roles; authoring tests |
| M11 | Prerequisites | Partial | Displayable array exists; dependency/enrollment enforcement is not a graph |
| M12 | Bundles | Partial | Bundle model/storefront/order fulfillment exist; entitlement edges need full tests |
| M13 | Coupons | Partial | Payment/order discount calculation exists; usage concurrency and cancellation restoration need work |
| M14 | Three payment gateways | Partial, hardened | Signature/reference/amount/currency checks; no full sandbox suite for all providers/refunds |
| M15 | Forums/discussions | Partial | Detail/search/mutation guards and tenant scoping are implemented and tested on targeted fixtures; legacy memberships and moderation remain |
| M16 | Direct messages | Partial | Tenant-aware direct messaging and safe attachment schemes are implemented; real-time delivery, abuse controls and complete tenant policy coverage remain |
| M17 | Notes/bookmarks | Present, limited verification | Notes/bookmarks use shared learning entitlements; offline readings have independent authenticated leases. Full resource and export matrix remains |
| M18 | XP/levels | Partial | Unique reward-event ledger and repeated-learning processing guards are tested; broader anti-abuse and time-zone cases remain |
| M19 | Badges | Present, limited verification | Badge models/seeder/page; criteria/idempotency need tests |
| M20 | Analytics | Partial | Learner/admin/facilitator report code; calculation calibration not proven |
| M21 | Events/meetings | Partial | Scheduling pages/controllers; actual conferencing integration incomplete |
| M22 | Cohorts/groups | Partial | Cohort/group controllers; selected ownership fixes, not exhaustive tenant coverage |
| M23 | Files/resources | Partial | Private assets, quarantine scanning, bounded package extraction and protected document delivery are implemented; host scanner/parser probes pass. Legacy public files and deployment remain |
| M24 | Free preview | Present, targeted tests | Shared access service supports signed-in previews while enforcing hidden/drip state |
| M25 | Versions/approval | Partial | Unified review lifecycle, deep immutable snapshots, recall and draft revisions are implemented; complete diff/archive UX and concurrency remain |
| M26 | Notifications | Partial | Real encrypted delivery outbox, SMTP/push adapters, digest and campaign processing are implemented. SMTP inbox test passed; production queue/device acceptance remains |
| M27 | Learner dashboard | Present, limited verification | Dashboard/page/controllers load; recommendation and large-data correctness unverified |
| M28 | Announcements | Present, limited verification | Facilitator announcement CRUD and pages; actual delivery/visibility coverage incomplete |

## MasterStudy PDF: the 31 “missing” claims

| ID | PDF page | Feature | Current status | Evidence / remaining implementation |
|---|---|---|---|---|
| M29 | 3 | AI complete-course generator | Partial | AI lesson/quiz/content tools; no transactional full-course generation/review/import pipeline |
| M30 | 3 | Recurring subscriptions | Partial | Immutable checkout/receipt/event ledgers, renewal, cancellation and retry logic pass targeted tests. Actual app provider cycle, pending-checkout recovery and live settlement remain |
| M31 | 3 | Memberships | Partial | Plan feature scaffolding; features/learning entitlements not comprehensively enforced |
| M32 | 3 | Taxes | Missing | No complete jurisdictional tax calculation/invoice/refund model identified |
| M33 | 4 | Trials | Partial | Trial dates/model behavior; repeat-trial prevention and lifecycle tests needed |
| M34 | 4 | Instructor earnings/commissions | Missing | No commission ledger, payout approval or settlement identified |
| M35 | 4 | Purchase history | Present, limited verification | Orders index/detail; invoice/refund lifecycle remains incomplete |
| M36 | 4 | Drip lessons | Partial, targeted tests | Immediate/date/enrollment-relative release; editor and shared guard now validated; time-zone edge tests needed |
| M37 | 4 | Enrollment expiry/pause | Partial | Enrollment statuses exist; consistent scheduled expiry and pause semantics incomplete |
| M38 | 4 | Dark/spotlight course player | Missing | No complete player mode/settings behavior identified |
| M39 | 4 | Course trailer | Partial | `preview_video` exists; complete authoring/player journey unverified |
| M40 | 4 | Upcoming course interest | Missing | No waitlist/interest/launch notification workflow identified |
| M41 | 4 | SCORM | Partial, constrained implementation | Bounded private package extraction, scan gate, opaque-origin player and constrained commit bridge are tested. Legacy migration, production wiring and compatibility remain; multi-SCO sequencing unsupported |
| M42 | 4 | Spotify/SoundCloud | Missing | Generic audio URL is not verified provider integration |
| M43 | 5 | Built-in live streaming | Missing | Generic video/provider components are not an in-platform live webinar broadcast service |
| M44 | 5 | Zoom | Partial | Meeting links/provider fields; no complete OAuth/create/webhook/recording integration proof |
| M45 | 5 | Google Meet | Partial | Link scheduling is not provider lifecycle integration |
| M46 | 5 | Google Classroom | Missing | No roster/coursework synchronization identified |
| M47 | 5 | Gradebook | Partial | `Facilitator/GradebookController` aggregates assignments, assessments and attendance; accuracy/export tests needed |
| M48 | 5 | Image matching | Missing | No complete authoring, player and grading contract identified |
| M49 | 5 | Image choice | Missing | Images in generic content are not a complete image-choice question implementation |
| M50 | 5 | Keywords answer type | Missing | No dedicated validated keyword grading strategy identified |
| M51 | 5 | Embeddable quizzes | Missing | No scoped embed token, cross-origin contract and result linkage identified |
| M52 | 5 | Co-instructors | Missing | Course author relationship is not collaborative author roles/permissions |
| M53 | 6 | Instructor applications | Missing | No application/review/onboarding workflow identified |
| M54 | 6 | Manual enrollment | Present, limited verification | Platform manual enrollment plus organization invitation/CSV seat enforcement and audited scoped membership are implemented. Paid-course organization assignment/licensing remains |
| M55 | 6 | Progress reset | Partial | Admin reset action exists; comprehensive assessment/certificate/history consistency unverified |
| M56 | 6 | Lesson Q&A | Partial | Course forums exist; lesson-scoped questions and accepted-answer/moderation workflow not complete |
| M57 | 6 | Email templates | Partial | Durable template payloads and campaign composer with delivery status exist; managed reusable template editing/versioning remains incomplete |
| M58 | 6 | Wishlist | Partial | General bookmarks are not a proven course wishlist-to-purchase workflow |
| M59 | 6 | Visual certificate builder | Missing | Fixed download template; no visual designer/versioned issuance templates |

The PDF's suggested closing implementation question on page 7 is not counted as an additional capability. Several “missing” features have since gained code, but this does not establish production-quality parity.

## Evidence limits

Automated tests and browser fixtures use synthetic data and block unexpected external calls. The subsequent migration/runtime rehearsal used a protected production database copy on the authorized host, never downloaded its records and left the live database unchanged. One authorized SMTP inbox delivery, one simulated Paystack test checkout, a same-host restore, SQL conversion, isolated HTTP/queue checks and server scanner/parser probes passed. Push devices, full recurring-provider lifecycle, SCORM conformance, native devices, assistive-technology conformance, representative load and off-host recovery remain unverified. Organization SSO and independent Contabo backup-service verification are user-deferred. These distinctions are recorded in the current verification summary.

## Additional administrator CRM requirement

The user-requested contact form and typed CSV import are implemented with company, job title, source, WhatsApp and structured addresses. Default and per-row Lead/Prospect/Customer/Partner types are supported alongside legacy Student/Alumni. Encrypted actor-bound previews expire in 15 minutes, imports are atomic and replay-safe, and duplicate/archived emails are rejected. Six feature tests plus populated desktop/mobile browser evidence are recorded in [CRM and learning boundaries](qa/CRM-AND-LEARNING-BOUNDARIES-2026-10-05.md). No messages or learner accounts are created by this import.

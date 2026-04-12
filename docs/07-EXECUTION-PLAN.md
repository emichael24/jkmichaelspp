# JK Michaels Professional Mastery Platform — Execution Plan

## Document Control

| Field | Value |
|---|---|
| Version | 1.0 |
| Date | February 26, 2026 |
| Methodology | Agile (2-week sprints) |
| Duration | 20 weeks (~5 months) |
| Team Size | See Section 3 |

---

## 1. Sprint Calendar Overview

| Sprint | Weeks | Dates (Est.) | Phase | Focus |
|---|---|---|---|---|
| **S0** | 0 | Pre-sprint | Setup | Project setup, tooling, environment |
| **S1** | 1-2 | Mar 2 – Mar 13 | Foundation | Auth, RBAC, users, organizations |
| **S2** | 3-4 | Mar 16 – Mar 27 | Courses | Course/module/lesson CRUD, content management |
| **S3** | 5-6 | Mar 30 – Apr 10 | Courses | Lesson viewer (multi-format), progress tracking |
| **S4** | 7-8 | Apr 13 – Apr 24 | Assessment | Question bank, quizzes, review flow |
| **S5** | 9-10 | Apr 27 – May 8 | Assessment | Mock exams, scoring, reports, spaced repetition |
| **S6** | 11-12 | May 11 – May 22 | Analytics + Gamification | Mastery maps, error patterns, XP, streaks, badges |
| **S7** | 13-14 | May 25 – Jun 5 | Facilitator | Cohort management, health dashboard, plan builder |
| **S8** | 15-16 | Jun 8 – Jun 19 | Facilitator + Internship | Grading, live sessions, internship task board |
| **S9** | 17-18 | Jun 22 – Jul 3 | Internship | Submissions, sponsor portal, portfolio, meetings |
| **S10** | 19-20 | Jul 6 – Jul 17 | SME + Simulations | Governance workflow, question management, simulations |
| **S11** | 21-22 | Jul 20 – Jul 31 | Commerce | Storefront, payments, certificates, notifications |
| **S12** | 23-24 | Aug 3 – Aug 14 | Community + Events + Polish | Forums, messaging, events, career pathways |
| **S13** | 25-26 | Aug 17 – Aug 28 | PWA + Mobile + Launch Prep | PWA optimization, Capacitor builds, UAT, launch |

---

## 2. Detailed Sprint Breakdown

### Sprint 0 — Project Setup (3-5 days)

**Deliverables:**
- [ ] Create Laravel 11 project
- [ ] Configure Vue 3 + Inertia.js + Tailwind CSS + Vite
- [ ] MySQL database created (local + staging)
- [ ] Redis configured (local)
- [ ] GitHub repository created with branching strategy
- [ ] CI/CD pipeline (GitHub Actions) — basic test + lint
- [ ] Laravel Sanctum installed + configured
- [ ] Spatie Permission installed
- [ ] S3 storage configured (DigitalOcean Spaces or AWS)
- [ ] Staging environment deployed (Laravel Forge / DigitalOcean)
- [ ] Base layouts created: AppLayout, AuthLayout, AdminLayout
- [ ] Design system components: Button, Card, Modal, Input, Table, Alert
- [ ] Tailwind theme configured (colors, fonts, spacing)
- [ ] Sentry error tracking installed
- [ ] Development environment README documented

**Exit Criteria:** Team can run the app locally, push code, CI runs, staging deploys.

---

### Sprint 1 — Auth & Users (Weeks 1-2)

**Backend:**
- [ ] User migration + model (all fields from schema)
- [ ] Organization migration + model
- [ ] Department migration + model
- [ ] Registration endpoint + validation
- [ ] Login endpoint + token generation
- [ ] Password reset flow
- [ ] Email verification flow
- [ ] Profile CRUD (GET, PUT, avatar upload)
- [ ] Roles + permissions seeder (7 roles, 11+ permissions)
- [ ] Auth middleware + role middleware
- [ ] Policy classes for user management
- [ ] Admin: list users, create user, assign roles, deactivate
- [ ] Admin: organization CRUD
- [ ] Admin: department CRUD
- [ ] Admin: CSV user import

**Frontend:**
- [ ] Login page
- [ ] Registration page
- [ ] Forgot password page
- [ ] Reset password page
- [ ] Auth store (Pinia)
- [ ] Auth middleware (route guards)
- [ ] Admin: user list page (with search, filter, pagination)
- [ ] Admin: user detail/edit page
- [ ] Admin: CSV import page
- [ ] Navigation: sidebar (desktop) + bottom nav (mobile)
- [ ] Profile edit page
- [ ] Preferences page (notifications + accessibility)

**Tests:**
- [ ] Auth API tests (register, login, logout, reset)
- [ ] RBAC middleware tests
- [ ] User CRUD API tests

---

### Sprint 2 — Course Management (Weeks 3-4)

**Backend:**
- [ ] Category migration + model + seeder (8 categories)
- [ ] Course migration + model
- [ ] Module migration + model
- [ ] Lesson migration + model
- [ ] Resource migration + model
- [ ] Course enrollment migration + model
- [ ] Bookmark + Note migrations + models
- [ ] Admin: course CRUD API
- [ ] Admin: module CRUD within course
- [ ] Admin: lesson CRUD within module
- [ ] Admin: resource CRUD
- [ ] Admin: media upload (video/audio/PDF to S3)
- [ ] Admin: publish/unpublish course
- [ ] Learner: list courses (with filters, search, sort)
- [ ] Learner: course detail
- [ ] Learner: enroll in free course
- [ ] Learner: bookmark + notes CRUD
- [ ] Meilisearch indexing for courses

**Frontend:**
- [ ] Admin: course list page
- [ ] Admin: course create/edit form (multi-step)
- [ ] Admin: module manager (sortable list)
- [ ] Admin: lesson create/edit (rich text editor, file uploads)
- [ ] Admin: resource upload manager
- [ ] Admin: category management page
- [ ] Learner: course catalog page (grid/list, filters, search)
- [ ] Learner: course detail page (overview, curriculum, resources)
- [ ] CourseCard component
- [ ] ModuleAccordion component

**Tests:**
- [ ] Course CRUD API tests
- [ ] Enrollment API tests
- [ ] Search integration test

---

### Sprint 3 — Lesson Viewer & Progress (Weeks 5-6)

**Backend:**
- [ ] Lesson progress migration + model
- [ ] Lesson progress tracking API (update, complete)
- [ ] Course progress calculation (aggregate from lessons)
- [ ] Download URL generation (signed S3 URLs)
- [ ] Video streaming setup (adaptive bitrate preparation)
- [ ] Learner: get lesson content API
- [ ] Learner: update lesson progress
- [ ] Learner: mark lesson complete
- [ ] Learner: my enrollments list + progress

**Frontend:**
- [ ] Lesson page with tab bar: Read | Watch | Listen | PDF | Practice
- [ ] ReadContent component (rich text rendering)
- [ ] VideoPlayer component (chapters, captions, resume, speed)
- [ ] AudioPlayer component (controls, speed, background play)
- [ ] PdfViewer component (in-app rendering)
- [ ] Lesson navigation (prev/next, progress bar)
- [ ] Notes + highlights UI within lesson
- [ ] Bookmark toggle on lessons
- [ ] Learner dashboard: ongoing courses section
- [ ] Course progress bar on catalog cards
- [ ] Download button + download manager page (UI skeleton)

**Tests:**
- [ ] Lesson progress tracking tests
- [ ] Content access authorization tests
- [ ] Video resume position tests

---

### Sprint 4 — Quiz Engine (Weeks 7-8)

**Backend:**
- [ ] Questions migration + model
- [ ] Quizzes migration + model
- [ ] Quiz-questions pivot migration
- [ ] Quiz attempts migration + model
- [ ] Question responses migration + model
- [ ] Admin: question CRUD API (with all fields)
- [ ] Admin: quiz CRUD API
- [ ] Learner: start quiz attempt
- [ ] Learner: submit answer (with confidence + time tracking)
- [ ] Learner: complete quiz + scoring logic
- [ ] Learner: quiz review (explanations per question)
- [ ] Question randomization logic
- [ ] Domain/subtopic scoring calculations
- [ ] Auto-add wrong answers to spaced repetition queue
- [ ] Admin: question bank list with filters

**Frontend:**
- [ ] Admin: question create/edit form (all types supported)
- [ ] Admin: quiz builder (select questions, configure settings)
- [ ] Admin: question bank browser (filters by domain, difficulty, type)
- [ ] QuizPlayer page (full-screen quiz experience)
- [ ] QuestionCard component (renders all question types)
- [ ] OptionsList component (radio/checkbox depending on type)
- [ ] QuizTimer component
- [ ] QuizNavGrid component (answered/unanswered/flagged)
- [ ] ConfidenceRater component
- [ ] QuizReview page (post-quiz review with explanations)
- [ ] ExplanationCard component
- [ ] ScoreSummary component
- [ ] DomainBreakdown component (chart)

**Tests:**
- [ ] Quiz scoring logic tests (multiple scenarios)
- [ ] Question type rendering tests
- [ ] Timer accuracy tests
- [ ] Confidence tracking tests

---

### Sprint 5 — Mock Exams & Spaced Repetition (Weeks 9-10)

**Backend:**
- [ ] Mock exams migration + model
- [ ] Mock-specific attempt handling (pause/resume)
- [ ] Full post-mock analytics calculation:
  - Domain breakdown
  - Time-per-question analysis
  - Confidence analysis
  - Weakness clustering
  - Fix priorities
  - 7-day plan generation
- [ ] Flashcard migration + model
- [ ] Spaced repetition items migration + model
- [ ] SM-2 algorithm implementation
- [ ] Auto-flashcard generation from lesson key terms
- [ ] Fix Card generation from wrong answers
- [ ] Spaced repetition: get due items API
- [ ] Spaced repetition: submit review + recalculate interval
- [ ] Practice by domain API (custom quiz generation)

**Frontend:**
- [ ] MockExamPlayer page (exam simulation UI)
- [ ] Pause/resume UI for mocks
- [ ] MockExamReport page (comprehensive post-mock report)
  - Score summary
  - Domain breakdown chart
  - Time analysis chart
  - Weakness clusters visualization
  - Fix priorities list
  - 7-day plan display
- [ ] Practice page (domain/difficulty/type selectors)
- [ ] SpacedRepetition review page
- [ ] FlashcardFlip component
- [ ] QualityRater component (0-5 scale)
- [ ] DeckProgress component

**Tests:**
- [ ] SM-2 algorithm unit tests
- [ ] Mock exam scoring tests
- [ ] Pause/resume state tests
- [ ] 7-day plan generation tests

---

### Sprint 6 — Analytics & Gamification (Weeks 11-12)

**Backend:**
- [ ] User mastery migration + model
- [ ] Mastery calculation service (runs after each quiz/mock)
- [ ] Error patterns migration + model
- [ ] Error pattern detection service
- [ ] Readiness scores migration + model
- [ ] Readiness score calculator
- [ ] Daily activity logs migration + model
- [ ] Activity logging (lessons, quizzes, mocks, flashcards)
- [ ] Analytics dashboard API (aggregated home data)
- [ ] Weekly report generation (scheduled job)
- [ ] User gamification migration + model
- [ ] Badges migration + model + seeder
- [ ] Missions migration + model + seeder
- [ ] XP level config seeder
- [ ] XP award service (triggered on actions)
- [ ] Badge award service (checks criteria after XP changes)
- [ ] Streak management service (check daily, handle protection)
- [ ] Mission progress tracking
- [ ] Leaderboard calculation
- [ ] Gamification stats API

**Frontend:**
- [ ] Learner home screen (complete implementation):
  - Today's plan tiles
  - Readiness meter (circular gauge)
  - Streak counter + protection
  - Focus areas cards
  - Continue card
  - Coach insight
- [ ] Analytics dashboard page
- [ ] MasteryMap (radar chart) component + page
- [ ] Error patterns display
- [ ] Readiness trend chart
- [ ] Activity chart (daily/weekly)
- [ ] Weekly report page
- [ ] Badges page (grid with earned/unearned)
- [ ] Missions page (active + progress bars)
- [ ] Leaderboard page
- [ ] XP bar + level indicator in header
- [ ] Streak counter in header
- [ ] Level-up celebration modal
- [ ] Badge earned celebration animation

**Tests:**
- [ ] Mastery calculation tests
- [ ] Error pattern detection tests
- [ ] XP award tests
- [ ] Badge criteria tests
- [ ] Streak logic tests (protection, rest day)

---

### Sprint 7 — Facilitator Console Part 1 (Weeks 13-14)

**Backend:**
- [ ] Cohorts migration + model
- [ ] Cohort members migration + model
- [ ] Cohort weekly plans migration + model
- [ ] Cohort plan items migration + model
- [ ] Announcements migration + model
- [ ] Interventions migration + model
- [ ] Facilitator: cohort CRUD API
- [ ] Facilitator: cohort member management
- [ ] Facilitator: cohort health calculation service
- [ ] Facilitator: mastery heatmap data API
- [ ] Facilitator: engagement heatmap data API
- [ ] Facilitator: at-risk learner detection service
- [ ] Facilitator: Learner 360 profile API
- [ ] Facilitator: weekly plan CRUD API (with items)
- [ ] Facilitator: auto-plan suggestion based on cohort weaknesses
- [ ] Facilitator: announcements CRUD
- [ ] Facilitator: nudge sending (push + email)
- [ ] Facilitator: intervention logging

**Frontend:**
- [ ] FacilitatorLayout (sidebar navigation)
- [ ] Facilitator dashboard page
- [ ] Cohort list page
- [ ] Cohort health dashboard:
  - Health score gauge
  - Mastery heatmap (learners × domains)
  - Engagement heatmap (learners × days)
  - Mock score distribution histogram
  - At-risk learner cards with reasons
- [ ] Learner 360 profile page
- [ ] Weekly plan builder (drag-and-drop UI)
- [ ] Announcements page (create + list)
- [ ] Nudge sending UI (templates + custom)
- [ ] Intervention log page

**Tests:**
- [ ] Cohort health calculation tests
- [ ] At-risk detection tests
- [ ] Plan builder API tests

---

### Sprint 8 — Grading, Live Sessions & Internship Start (Weeks 15-16)

**Backend:**
- [ ] Assignments migration + model
- [ ] Assignment submissions migration + model
- [ ] Facilitator: assignment CRUD API
- [ ] Facilitator: submission grading API (rubric scores, feedback)
- [ ] Facilitator: attendance tracking
- [ ] Meeting migration + model
- [ ] Meeting attendees migration + model
- [ ] Facilitator: meeting CRUD + RSVP
- [ ] Facilitator: meeting notes + action items
- [ ] Internship tracks migration + model
- [ ] Internship enrollments migration + model
- [ ] Internship milestones migration + model
- [ ] Internship tasks migration + model
- [ ] Learner: task board API (list, update status)
- [ ] Admin: internship track CRUD
- [ ] Admin: internship enrollment management

**Frontend:**
- [ ] Facilitator: assignment create page
- [ ] Facilitator: grading page (submission viewer + rubric scorer)
- [ ] Facilitator: live sessions page (schedule, link, attendance)
- [ ] Meeting card component
- [ ] Attendance list component
- [ ] Internship task board page (kanban):
  - KanbanBoard component (drag-and-drop columns)
  - TaskCard component
  - Column filters (daily/weekly/monthly)
- [ ] Internship dashboard page (learner view)
- [ ] Admin: internship track management page
- [ ] Learner: my cohorts page

**Tests:**
- [ ] Assignment submission workflow tests
- [ ] Grading API tests
- [ ] Task board state transition tests
- [ ] Meeting CRUD tests

---

### Sprint 9 — Internship Submissions, Sponsor & Portfolio (Weeks 17-18)

**Backend:**
- [ ] Internship submissions migration + model
- [ ] Internship evidence migration + model
- [ ] Sponsor reviews migration + model
- [ ] Sponsor scorecards migration + model
- [ ] Learner: task submission API (files + evidence + notes)
- [ ] Learner: resubmission API
- [ ] Sponsor: review queue API
- [ ] Sponsor: submit review (rubric, feedback, decision)
- [ ] Sponsor: scorecard submission API
- [ ] Portfolio items migration + model
- [ ] Learner: portfolio CRUD API
- [ ] Learner: portfolio PDF export
- [ ] Certificates migration + model
- [ ] Certificate generation service (PDF + QR code)
- [ ] Internship completion detection + certificate issuance
- [ ] Meeting: action items → task creation
- [ ] Meetings hub: calendar + scheduling
- [ ] Facilitator: internship oversight APIs

**Frontend:**
- [ ] SponsorLayout (sidebar navigation)
- [ ] Sponsor: review queue page
- [ ] Sponsor: submission review page (file viewer, evidence viewer)
- [ ] Sponsor: rubric scorer component
- [ ] Sponsor: feedback form (text + voice note)
- [ ] Sponsor: scorecard submission page
- [ ] Sponsor: intern progress page
- [ ] Task detail page (with submission form)
- [ ] SubmissionForm component (files + evidence + notes)
- [ ] EvidenceUploader component
- [ ] Submission history page (learner)
- [ ] Portfolio builder page
- [ ] Portfolio card component
- [ ] Portfolio PDF export button
- [ ] Meetings hub page (calendar view)
- [ ] Meeting scheduling form
- [ ] Facilitator: internship oversight page

**Tests:**
- [ ] Submission + evidence upload tests
- [ ] Sponsor review workflow tests
- [ ] Scorecard calculation tests
- [ ] Certificate generation tests
- [ ] Portfolio export tests

---

### Sprint 10 — SME Governance & Simulations (Weeks 19-20)

**Backend:**
- [ ] Content versions migration + model
- [ ] Content approvals migration + model
- [ ] Question flags migration + model
- [ ] Content workflow service (draft → review → approve → publish)
- [ ] Version snapshot on publish
- [ ] Question flag CRUD + resolution
- [ ] Item quality analytics calculations (difficulty, discrimination, distractor)
- [ ] Blueprint coverage report
- [ ] Simulations migration + model
- [ ] Simulation nodes migration + model
- [ ] Simulation choices migration + model
- [ ] Simulation attempts migration + model
- [ ] Simulation start API
- [ ] Simulation choice API (returns next node + running score)
- [ ] Simulation complete API (scorecard + best practice path)
- [ ] SME: content review + approve/reject API
- [ ] SME: question bank management with analytics

**Frontend:**
- [ ] SMELayout (sidebar navigation)
- [ ] SME: content library page
- [ ] SME: content review page (diff view)
- [ ] SME: approve/reject workflow UI
- [ ] SME: question bank management page (filters, edit, analytics)
- [ ] SME: flag queue page
- [ ] SME: item analytics page (charts)
- [ ] SME: versioning page (history + revert)
- [ ] Simulation listing page
- [ ] Simulation player page:
  - StoryNode component
  - DecisionNode component (choice cards)
  - ConsequenceNode component
  - Scorecard display
  - PathComparison component (your path vs best practice)
- [ ] Simulation save/resume

**Tests:**
- [ ] Content workflow state machine tests
- [ ] Version snapshot tests
- [ ] Simulation branching logic tests
- [ ] Scorecard calculation tests
- [ ] Item quality analytics tests

---

### Sprint 11 — Commerce & Certificates (Weeks 21-22)

**Backend:**
- [ ] Products migration + model
- [ ] Bundles migration + model
- [ ] Discount codes migration + model
- [ ] Orders migration + model
- [ ] Order items migration + model
- [ ] Payments migration + model
- [ ] Paystack integration (checkout URL, webhook verification)
- [ ] Flutterwave integration (checkout URL, webhook verification)
- [ ] Stripe integration (checkout, webhook)
- [ ] Order creation + discount validation
- [ ] Auto-enrollment on payment success
- [ ] Receipt/invoice PDF generation
- [ ] Storefront API (product listing, filters, search)
- [ ] Course completion detection + certificate issuance
- [ ] Certificate verification API (public)
- [ ] Notification system:
  - Notifications migration (Laravel standard)
  - Notification preferences migration + model
  - Push notification service (VAPID / Capacitor)
  - Email notification service
  - Learning reminders (scheduled)
  - Internship reminders (scheduled)
  - Streak rescue (scheduled: 8 PM check)
  - Quiet hours enforcement

**Frontend:**
- [ ] Storefront page (product catalog)
- [ ] Product detail page
- [ ] Checkout page (gateway selection, discount code input)
- [ ] Payment confirmation page
- [ ] Order history page
- [ ] Order detail page
- [ ] Certificates page (my certificates)
- [ ] Certificate detail page (with download + share)
- [ ] Certificate verification page (public, QR-scanned)
- [ ] Notification bell + badge count
- [ ] Notification drawer/page
- [ ] Notification preferences page

**Tests:**
- [ ] Payment webhook verification tests (all 3 gateways)
- [ ] Auto-enrollment tests
- [ ] Discount code validation tests
- [ ] Certificate generation + verification tests
- [ ] Notification delivery tests

---

### Sprint 12 — Community, Events & Polish (Weeks 23-24)

**Backend:**
- [ ] Forums migration + model
- [ ] Forum threads migration + model
- [ ] Forum replies migration + model
- [ ] Direct messages migration + model
- [ ] Study groups migration + model
- [ ] Events migration + model
- [ ] Event registrations migration + model
- [ ] Career pathways migration + model
- [ ] Course ratings migration + model
- [ ] Audit logs migration + model
- [ ] Forum CRUD APIs (threads, replies, moderation)
- [ ] Direct messaging API (with WebSocket via Reverb)
- [ ] Study group CRUD + membership
- [ ] Events CRUD + registration
- [ ] Pathway CRUD + recommendations
- [ ] Course rating API
- [ ] Audit logging service (auto-log on model events)
- [ ] Admin: dashboard aggregate calculations
- [ ] Admin: reports (executive PDF, compliance, department)
- [ ] Admin: settings management
- [ ] Admin: billing/seat management
- [ ] Onboarding flow API (complete)
- [ ] Home screen dashboard API (complete aggregation)

**Frontend:**
- [ ] Forums page (list, create thread)
- [ ] Thread page (with replies, moderation tools)
- [ ] Direct messages page (conversation list + chat UI)
- [ ] Study groups page (list, create, join)
- [ ] Events calendar page
- [ ] Event detail page (with register CTA)
- [ ] Career pathways page (visual roadmap)
- [ ] Pathway detail page
- [ ] Onboarding flow pages (all 4 steps + quick win)
- [ ] Admin: dashboard page (all widgets)
- [ ] Admin: reports pages (executive, compliance, department, internship)
- [ ] Admin: settings page
- [ ] Admin: billing page
- [ ] Admin: audit logs page
- [ ] Course rating component (on course detail page)
- [ ] Overall UI polish pass:
  - Loading states (skeleton screens)
  - Empty states
  - Error states
  - Form validation messages
  - Toast notifications
  - Responsive layout fixes

**Tests:**
- [ ] Forum moderation tests
- [ ] Real-time messaging tests
- [ ] Event registration tests
- [ ] Onboarding flow integration test
- [ ] Admin report generation tests

---

### Sprint 13 — PWA, Mobile & Launch (Weeks 25-26)

**Backend:**
- [ ] Performance optimization:
  - Query optimization (N+1 detection, eager loading)
  - Database indexing review
  - API response caching strategy
  - Queue optimization
- [ ] Security audit:
  - Rate limiting on all endpoints
  - CSRF verification
  - Authorization policy review
  - Input validation review
  - File upload security
- [ ] Data seeder for demo/staging (realistic test data)

**Frontend:**
- [ ] PWA configuration:
  - Service worker registration
  - Manifest configuration
  - Offline fallback page
  - Cache strategy implementation
  - Push notification prompts
  - "Add to Home Screen" handling
- [ ] Offline mode:
  - Download manager page (functional)
  - IndexedDB for offline progress
  - Background sync for queued actions
  - Offline indicator banner
- [ ] Capacitor setup:
  - Android project generated + configured
  - iOS project generated + configured
  - Splash screen + app icon
  - Push notification plugin
  - Camera plugin (evidence capture)
  - File system plugin (downloads)
  - Network detection plugin
- [ ] Performance optimization:
  - Code splitting audit
  - Bundle size analysis
  - Image optimization pass
  - Lighthouse score > 90 (Performance + Accessibility)
- [ ] Cross-browser testing: Chrome, Safari, Firefox, Edge
- [ ] Mobile testing: Android (mid-range device), iOS (iPhone 12+)
- [ ] Accessibility audit:
  - Keyboard navigation test
  - Screen reader test
  - Contrast ratio verification
  - Touch target size verification
- [ ] UAT with stakeholders (JK Michaels team)
- [ ] Bug fix sprint (from UAT feedback)
- [ ] Production deployment
- [ ] Play Store submission (Android)
- [ ] App Store submission (iOS)
- [ ] Launch monitoring setup

**Exit Criteria:**
- [ ] All critical user flows work end-to-end
- [ ] Lighthouse: Performance > 85, Accessibility > 90, Best Practices > 90
- [ ] No P0/P1 bugs
- [ ] Payment flow tested with real money (small amount) on all 3 gateways
- [ ] Push notifications work on PWA, Android, iOS
- [ ] Staging sign-off from JK Michaels

---

## 3. Team Composition (Recommended)

| Role | Count | Responsibility |
|---|---|---|
| **Full-Stack Lead Developer** | 1 | Architecture, backend core, code review, deployment |
| **Backend Developer** | 1-2 | APIs, services, integrations, queue jobs |
| **Frontend Developer** | 1-2 | Vue.js pages, components, mobile responsiveness |
| **UI/UX Designer** | 1 | Design system, wireframes, prototypes, UX review |
| **QA / Tester** | 1 | Manual testing, test case writing, UAT coordination |
| **Project Manager** | 1 | Sprint planning, stakeholder comms, timeline tracking |
| **DevOps** (part-time) | 0.5 | Server setup, CI/CD, monitoring, deployment |

**Minimum viable team:** 1 lead + 1 backend + 1 frontend + 1 designer = 4 people
**Ideal team:** 6-7 people

---

## 4. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Scope creep from stakeholders | High | High | Feature freeze per sprint. Use feature flags. Defer to V2. |
| Payment integration complexity | Medium | High | Start integration early (Sprint 3-4 for POC). Use sandbox extensively. |
| Performance on low-end Android | Medium | Medium | Test on budget device from Sprint 6+. Performance budget enforcement. |
| Content not ready for launch | High | Medium | Seed with sample content. Platform works independently of content volume. |
| Team capacity shortage | Medium | High | Prioritize core features. Can trim simulations + community to post-launch. |
| Offline sync conflicts | Medium | Medium | Last-write-wins for MVP. Document edge cases. |
| App store rejection | Low | Medium | Follow platform guidelines. Prepare privacy policy + terms of service early. |

---

## 5. Definition of Done (per feature)

- [ ] Code reviewed and merged
- [ ] API tests passing (where applicable)
- [ ] Frontend renders correctly on mobile + desktop
- [ ] No console errors
- [ ] Loading states implemented
- [ ] Empty states implemented
- [ ] Error handling implemented
- [ ] Responsive (tested at 375px, 768px, 1280px)
- [ ] Accessibility: keyboard navigable, proper ARIA
- [ ] Deployed to staging and verified

---

## 6. Success Metrics (Post-Launch)

| Metric | Target (Month 1) | Target (Month 3) |
|---|---|---|
| Registered users | 500 | 2,000 |
| DAU/MAU ratio | 15% | 25% |
| Course completion rate | 30% | 45% |
| Avg session duration | 8 min | 12 min |
| Mock exam attempts/user | 2 | 5 |
| Payment conversion (free → paid) | 5% | 10% |
| App crash rate | < 1% | < 0.5% |
| User satisfaction (in-app survey) | 3.5/5 | 4.0/5 |
| Lighthouse Performance score | > 85 | > 90 |

---

## 7. Post-Launch Roadmap (V2 Features)

| Priority | Feature | Timeline |
|---|---|---|
| 1 | AI Coach (scoped to course content, guardrailed) | Month 2-3 |
| 2 | Advanced simulation library (10+ scenarios per track) | Month 3-4 |
| 3 | Deeper ROI reporting (pre/post delta dashboards) | Month 3 |
| 4 | Credly/LinkedIn badge sharing | Month 4 |
| 5 | Full offline sync (bidirectional) | Month 4-5 |
| 6 | White-label/branding options for enterprise | Month 5-6 |
| 7 | Adaptive cohort plans (AI-suggested per cohort) | Month 6 |
| 8 | Predictive readiness models | Month 6-7 |
| 9 | Employer-facing portfolio links | Month 7 |
| 10 | React Native migration evaluation (if Capacitor limits hit) | Month 8 |

---

## 8. Document Cross-References

| When working on... | Refer to... |
|---|---|
| Database migrations | `02-DATABASE-SCHEMA.md` |
| API endpoint implementation | `03-API-SPECIFICATION.md` |
| Feature behavior & acceptance criteria | `04-FEATURE-SPECIFICATIONS.md` |
| Vue components & pages | `05-FRONTEND-ARCHITECTURE.md` |
| Server setup & deployment | `06-DEPLOYMENT-DEVOPS.md` |
| Sprint tasks & progress | This document (`07-EXECUTION-PLAN.md`) |

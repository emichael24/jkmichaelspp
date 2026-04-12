# JK Michaels Professional Mastery Platform — Project Overview

## Document Control

| Field | Value |
|---|---|
| Project Name | JK Michaels Professional Mastery Platform |
| Version | 1.0 |
| Date | February 26, 2026 |
| Status | Baseline |

---

## 1. Product Vision

**One-line definition:**
A personalized, AI-enhanced learning platform that blends multi-format courses, adaptive quizzes, mock exams, immersive simulations, structured internship projects, gamification, and real-time analytics — while giving admins, facilitators, sponsors, and SMEs full visibility into learner progress, quality, and outcomes.

**Key outcomes:**
- Pass rate improvement for exam candidates
- Skill uplift for managers/leaders
- Retention & habit formation (daily learning)
- Measurable ROI (pre/post deltas)
- Portfolio-ready deliverables (internship)
- Sponsor-rated performance scorecards

---

## 2. Target Users & Roles

| Role | Description | Primary Actions |
|---|---|---|
| **Learner** | Busy professional, exam candidate, manager, corporate learner, internship candidate | Study, take quizzes/exams, submit work, view progress |
| **Facilitator** | Trainer/coach | Manage cohorts, grade, intervene, run live sessions |
| **Org Admin** | HR/L&D manager | Manage users, assign programs, view reports, billing |
| **Platform Admin** | JK Michaels team | Full system access, configuration, content management |
| **SME/Reviewer** | Subject matter expert | Review content, manage question banks, approve publications |
| **Sponsor/Reviewer** | Internship supervisor | Review submissions, score rubrics, provide feedback |
| **Line Manager** | Optional | View team progress snapshots |

---

## 3. Technology Stack

| Layer | Technology | Purpose |
|---|---|---|
| **Backend Framework** | Laravel 11+ (PHP 8.3) | API, business logic, auth, queues, file handling |
| **Frontend Framework** | Vue.js 3 + Inertia.js | Reactive SPA, component-based UI |
| **Build Tool** | Vite | Fast bundling, HMR, PWA plugin |
| **Database** | MySQL 8.0 | Primary relational store |
| **Cache / Queue** | Redis + Laravel Horizon | Caching, background job processing |
| **File Storage** | S3-compatible (AWS S3 / DigitalOcean Spaces) | Media, submissions, certificates |
| **Search** | Meilisearch | Course catalog, question bank, resource search |
| **Real-time** | Laravel Reverb | WebSocket for notifications, live updates |
| **Auth** | Laravel Sanctum | Token-based API auth |
| **RBAC** | Spatie Laravel Permission | Roles & permissions management |
| **Payments** | Paystack + Flutterwave + Stripe | Nigerian + international payments |
| **PDF Generation** | DomPDF / Browsershot | Certificates, reports, portfolio export |
| **PWA** | Vite PWA Plugin + Service Workers | Installable, offline-capable |
| **Mobile** | Capacitor | Compile to Android/iOS from PWA |
| **Monitoring** | Laravel Telescope + Firebase Crashlytics | Debug, performance, crash reports |
| **CI/CD** | GitHub Actions | Automated testing, deployment |
| **Hosting** | DigitalOcean / AWS (Laravel Forge) | Server provisioning, SSL, deployments |

---

## 4. Architecture Pattern

**Modular Monolith** — Single Laravel application organized into domain modules. Not microservices. Keeps deployment simple while maintaining clean separation.

```
┌──────────────────────────────────────────────────────┐
│                     Clients                           │
│  PWA (Browser)  │  Android (Capacitor)  │  iOS        │
└───────┬─────────┴───────────┬───────────┴────────────┘
        │         HTTPS / JSON API                │
┌───────▼─────────────────────▼────────────────────────┐
│                Laravel Application                    │
│                                                       │
│  ┌─────────────────────────────────────────────────┐ │
│  │              Domain Modules                      │ │
│  │                                                   │ │
│  │  Auth & RBAC    │   Courses      │  Assessments  │ │
│  │  Users/Orgs     │   Lessons      │  Quizzes      │ │
│  │  Profiles       │   Resources    │  MockExams    │ │
│  │                 │   Media        │  QuestionBank │ │
│  │─────────────────┼────────────────┼───────────────│ │
│  │  Internship     │  Gamification  │  Analytics    │ │
│  │  Tasks          │  XP/Badges     │  Mastery      │ │
│  │  Submissions    │  Streaks       │  Heatmaps     │ │
│  │  Sponsor Review │  Missions      │  ErrorPatterns│ │
│  │─────────────────┼────────────────┼───────────────│ │
│  │  Facilitator    │  Commerce      │  Governance   │ │
│  │  Cohorts        │  Storefront    │  ContentFlow  │ │
│  │  Grading        │  Payments      │  Versioning   │ │
│  │  LiveSessions   │  Enrollment    │  QBManagement │ │
│  │─────────────────┼────────────────┼───────────────│ │
│  │  Notifications  │  Meetings      │  Simulations  │ │
│  │  Push/Email     │  Calendar      │  Branching    │ │
│  │  Reminders      │  Integrations  │  Scorecards   │ │
│  │                 │  VideoConf     │               │ │
│  │─────────────────┼────────────────┼───────────────│ │
│  │  Certificates   │  Community     │  Admin        │ │
│  │  Portfolio      │  Forums        │  OrgConsole   │ │
│  │  QR Verify      │  Messaging     │  Reports      │ │
│  └─────────────────┴────────────────┴───────────────┘ │
│                                                       │
│  Infrastructure: Redis │ Horizon │ Reverb │ S3 │ Mail │
└───────────┬───────────────────────────────────────────┘
            │
┌───────────▼───────────────────────────────────────────┐
│  MySQL 8.0  │  Redis  │  S3 Storage  │  Meilisearch   │
└───────────────────────────────────────────────────────┘
```

---

## 5. Multi-Tenancy Strategy

**Single database with `organization_id` scoping.**

- All org-specific data filtered by `organization_id`
- Platform-level content (JK Michaels courses) shared across orgs
- Org admins see only their users/data
- Platform admins see everything

---

## 6. Security & Data Privacy

- HTTPS everywhere (TLS 1.3)
- Encryption at rest (database + file storage)
- Laravel Sanctum token auth (no session cookies for API)
- RBAC enforced at middleware + policy level
- Input validation on all endpoints
- Rate limiting on auth + API endpoints
- CSRF protection on web routes
- SQL injection prevention (Eloquent ORM)
- XSS prevention (Vue.js auto-escaping + Laravel sanitization)
- File upload validation (type, size, malware scan)
- Audit logs for sensitive actions
- GDPR-like controls: data export, deletion requests
- Configurable data retention policies
- Admin access logging

---

## 7. Performance Budget

| Metric | Target |
|---|---|
| Cold start | < 3 seconds |
| Time to interactive | < 500ms |
| App size (mobile) | < 100 MB |
| API response (p95) | < 300ms |
| Image format | WebP (with fallback) |
| Video streaming | Adaptive bitrate |

**Techniques:**
- Lazy loading for non-essential modules
- Intelligent caching (Redis, 5-min TTL for UI data)
- Skeleton UIs + progressive loading
- Image compression (WebP, downsampling)
- Async network calls
- Background processing via queue (Horizon)
- Offline-first: text → interactive → media priority
- Background sync when online
- Profiling on low-end Android devices

---

## 8. Key Excluded Items (Out of Scope)

These are explicitly **NOT** in scope:

| Item | Reason |
|---|---|
| SSO/SCIM | Enterprise complexity; not needed for initial market |
| xAPI/SCORM | LMS interoperability not required initially |
| Campaign Engine | Marketing automation deferred; simple notifications sufficient |
| Proctoring | Adds complexity and vendor dependency |
| Advanced AI role-play tutor | V2 feature |
| Predictive readiness models | V2 feature |
| Project marketplace | V2 feature |
| Employer-facing portfolio links | V2 feature |

---

## 9. Document Index

| Document | File | Contents |
|---|---|---|
| Project Overview | `01-PROJECT-OVERVIEW.md` | This document |
| Database Schema | `02-DATABASE-SCHEMA.md` | All tables, relationships, indexes |
| API Specification | `03-API-SPECIFICATION.md` | All endpoints, request/response formats |
| Feature Specifications | `04-FEATURE-SPECIFICATIONS.md` | Detailed specs per feature module |
| Frontend Architecture | `05-FRONTEND-ARCHITECTURE.md` | Components, pages, state, routing |
| Deployment & DevOps | `06-DEPLOYMENT-DEVOPS.md` | Hosting, CI/CD, environments, monitoring |
| Execution Plan | `07-EXECUTION-PLAN.md` | Sprint plan, milestones, deliverables |

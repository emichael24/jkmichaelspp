# JK Michaels Platform — Comprehensive Gap Analysis Report

**Generated:** March 7, 2026  
**Scope:** All 7 documentation files vs. all implemented Vue pages + controllers  
**Total Vue Pages Audited:** 107 files across 30 directories  

---

## TABLE OF CONTENTS

1. [Complete Vue Page Inventory](#1-complete-vue-page-inventory)
2. [Per-Role Gap Analysis](#2-per-role-gap-analysis)
   - [Learner](#role-learner)
   - [Facilitator](#role-facilitator)
   - [SME Reviewer](#role-sme-reviewer)
   - [Sponsor](#role-sponsor)
   - [Admin](#role-admin)
3. [Missing Pages (Doc-Specified but Unbuilt)](#3-missing-pages)
4. [Top 20 Most Impactful Improvements](#4-top-20-most-impactful-improvements)

---

## 1. Complete Vue Page Inventory

### Learner Pages
| Page | Lines | Status | Interactive? |
|------|-------|--------|-------------|
| Dashboard.vue | 344 | ✅ Fully built | Yes — today's plan, streaks, XP, courses, coach insight, missions |
| Courses/Index.vue | 178 | ✅ Built | Yes — search, grid/list toggle, category filters |
| Courses/Show.vue | 284 | ✅ Built | Yes — tabs (overview, curriculum, reviews), enroll button, progress |
| Courses/MyCourses.vue | 186 | ✅ Built | Yes — enrolled courses with progress |
| Lessons/Show.vue | 302 | ✅ Built | Yes — multi-tab (read/watch/listen/PDF/practice), sidebar nav, notes |
| Quizzes/Show.vue | 163 | ✅ Built | Yes — quiz info, start button |
| Quizzes/Player.vue | 307 | ✅ Built | Yes — question cards, timer, nav grid, confidence rating, flagging |
| Quizzes/Review.vue | 157 | ✅ Built | Yes — post-quiz review with explanations |
| MockExams/Index.vue | 132 | ✅ Built | Yes — mock exam listing |
| MockExams/Show.vue | 152 | ✅ Built | Yes — mock exam detail |
| MockExams/Player.vue | 240 | ✅ Built | Yes — exam simulation player |
| MockExams/Report.vue | 289 | ✅ Built | Yes — domain analysis, time analysis, fix priorities tabs |
| Practice/Dashboard.vue | 114 | ✅ Built | Yes — practice by domain selector |
| Practice/Flashcards.vue | 194 | ✅ Built | Yes — flashcard manager, create cards |
| Practice/Review.vue | 131 | ✅ Built | Yes — spaced repetition review session with quality rating |
| Analytics/Dashboard.vue | 288 | ✅ Built | Yes — mastery map, trends, error patterns, readiness |
| Analytics/WeeklyReport.vue | 105 | ⚠️ Basic | Partially — displays report but no PDF export, limited data |
| Gamification/Badges.vue | 84 | ⚠️ Basic | Shows badge grid but minimal interactivity |
| Gamification/Leaderboard.vue | 98 | ⚠️ Basic | Shows leaderboard table |
| Gamification/Missions.vue | 163 | ✅ Built | Yes — daily/weekly missions with progress bars |
| Internship/Dashboard.vue | 159 | ✅ Built | Yes — progress, stats, milestones, upcoming tasks |
| Internship/TaskBoard.vue | 179 | ⚠️ Partial | Has kanban columns but NO drag-and-drop |
| Internship/Submissions.vue | 81 | ⚠️ Basic | Simple submission list |
| Internship/SubmissionDetail.vue | 135 | ⚠️ Basic | View + submit form but no evidence uploader component |
| Portfolio/Index.vue | 159 | ✅ Built | Yes — portfolio items management |
| Portfolio/Export.vue | 62 | ⚠️ Stub | Basic export UI |
| Portfolio/Public.vue | 42 | ⚠️ Stub | Minimal public view |
| Simulations/Index.vue | 129 | ✅ Built | Yes — simulation listing |
| Simulations/Show.vue | 120 | ✅ Built | Yes — simulation detail |
| Simulations/Player.vue | 195 | ✅ Built | Yes — story/decision/consequence flow |
| Simulations/Report.vue | 119 | ⚠️ Basic | Shows scorecard but no best-practice path comparison |
| Forums/Index.vue | 110 | ⚠️ Basic | Forum listing |
| Forums/Show.vue | 80 | ⚠️ Basic | Forum detail |
| Forums/Thread.vue | 123 | ⚠️ Basic | Thread with replies |
| Messages/Index.vue | 90 | ⚠️ Basic | Message list, no real-time |
| Messages/Conversation.vue | 91 | ⚠️ Basic | Conversation view, no real-time |
| StudyGroups/Index.vue | 81 | ⚠️ Basic | Study group list |
| StudyGroups/Show.vue | 66 | ⚠️ Stub | Minimal group detail |
| Events/Index.vue | 70 | ⚠️ Basic | Event list |
| Events/Show.vue | 88 | ⚠️ Basic | Event detail |
| Certificates/Index.vue | 54 | ⚠️ Basic | Certificate list |
| Certificates/Show.vue | 59 | ⚠️ Basic | Certificate detail |
| Certificates/Verify.vue | 51 | ⚠️ Basic | Public verification |
| Pathways/Index.vue | 51 | ⚠️ Stub | Minimal pathway listing |
| Pathways/Show.vue | 78 | ⚠️ Stub | Minimal pathway detail |
| Bookmarks/Index.vue | 116 | ✅ Built | Yes — bookmark management |
| Notifications/Index.vue | 123 | ⚠️ Basic | Notification list |
| Notifications/Preferences.vue | 126 | ✅ Built | Yes — notification preference toggles |
| Profile/Edit.vue | 148 | ✅ Built | Yes — profile form |
| Profile/Show.vue | 90 | ⚠️ Basic | Profile view |
| Onboarding/Index.vue | 125 | ⚠️ Partial | 4-step wizard but simplified from docs spec |
| Orders/Index.vue | 66 | ⚠️ Basic | Order history |
| Orders/Show.vue | 121 | ⚠️ Basic | Order detail |
| Downloads/Index.vue | 119 | ⚠️ Stub | Download manager UI but no functional offline storage |
| Storefront/Index.vue | 120 | ⚠️ Basic | Product listing |
| Storefront/Show.vue | 170 | ⚠️ Basic | Product detail with checkout |
| MyCohorts.vue | 61 | ⚠️ Basic | Simple cohort list for learners |

### Facilitator Pages
| Page | Lines | Status |
|------|-------|--------|
| Dashboard.vue | 217 | ✅ Built — cohort cards, stats, recent interventions |
| Cohorts/Index.vue | 120 | ✅ Built |
| Cohorts/Show.vue | 354 | ✅ Built — health score, mastery heatmap, engagement heatmap, at-risk learners |
| Cohorts/Create.vue | 80 | ⚠️ Basic — simple form |
| Cohorts/Edit.vue | 74 | ⚠️ Basic — simple form |
| Cohorts/Learner360.vue | 196 | ✅ Built — progress, mastery, mock history |
| Assignments/Index.vue | 88 | ⚠️ Basic |
| Assignments/Create.vue | 95 | ⚠️ Basic |
| Assignments/Edit.vue | 98 | ⚠️ Basic |
| Assignments/Grading.vue | 172 | ✅ Built — rubric scoring, feedback |
| Plans/Builder.vue | 277 | ✅ Built — weekly plan builder |
| Announcements/Index.vue | 153 | ✅ Built |
| Interventions/Index.vue | 210 | ✅ Built — intervention log, create |
| InternshipOversight/Health.vue | 106 | ⚠️ Basic |
| InternshipOversight/Portfolios.vue | 79 | ⚠️ Basic |
| Meetings/Index.vue | 240 | ✅ Built — meeting list, scheduling |
| Meetings/MeetingCard.vue | 118 | ✅ Built — component |

### SME Reviewer Pages
| Page | Lines | Status |
|------|-------|--------|
| AnalyticsDashboard.vue | 101 | ✅ Built — difficulty/discrimination distributions |
| ContentLibrary.vue | 117 | ✅ Built — content list with workflow status |
| ContentReview.vue | 195 | ✅ Built — review UI with approve/reject/request changes |
| PendingQueue.vue | 72 | ⚠️ Basic |
| QuestionBank.vue | 113 | ⚠️ Basic — list only, no inline editing |
| ItemAnalytics.vue | 99 | ⚠️ Basic — question analytics view |
| FlagQueue.vue | 149 | ✅ Built — flag list with resolve action |
| BlueprintCoverage.vue | 80 | ⚠️ Basic |
| Versions.vue | 73 | ⚠️ Basic — version list, no diff view |

### Sponsor Pages
| Page | Lines | Status |
|------|-------|--------|
| ReviewQueue.vue | 78 | ✅ Built — submission list with pagination |
| ReviewSubmission.vue | 140 | ✅ Built — file viewer, review form, risk flags |
| InternProgress.vue | 139 | ✅ Built — task list, milestones, scorecards |
| Scorecard.vue | 118 | ✅ Built — full scorecard form with criteria |

### Admin Pages
| Page | Lines | Status |
|------|-------|--------|
| Dashboard.vue | 161 | ⚠️ Partial — stat cards + tables but charts are placeholders |
| Users/Index.vue | 203 | ✅ Built |
| Users/Create.vue | 71 | ⚠️ Basic |
| Users/Edit.vue | 75 | ⚠️ Basic |
| Users/Import.vue | 72 | ⚠️ Basic |
| Courses/Index.vue | 229 | ✅ Built |
| Courses/Create.vue | 220 | ✅ Built |
| Courses/Edit.vue | 367 | ✅ Built |
| Courses/Lessons/Create.vue | 145 | ✅ Built |
| Courses/Lessons/Edit.vue | 150 | ✅ Built |
| Categories/Index.vue | 155 | ✅ Built |
| Questions/Index.vue | 226 | ✅ Built |
| Questions/Create.vue | 11 | ❌ Stub (wraps QuestionForm) |
| Questions/Edit.vue | 11 | ❌ Stub (wraps QuestionForm) |
| Questions/QuestionForm.vue | 294 | ✅ Built |
| Quizzes/Index.vue | 175 | ✅ Built |
| Quizzes/Create.vue | 143 | ✅ Built |
| Quizzes/Edit.vue | 261 | ✅ Built |
| MockExams/Index.vue | 142 | ✅ Built |
| MockExams/Create.vue | 115 | ✅ Built |
| MockExams/Edit.vue | 121 | ✅ Built |
| Simulations/Index.vue | 143 | ✅ Built |
| Simulations/Create.vue | 104 | ✅ Built |
| Simulations/Edit.vue | 383 | ✅ Built |
| Simulations/Show.vue | 121 | ✅ Built |
| Internships/Index.vue | 71 | ⚠️ Basic |
| Internships/Create.vue | 78 | ⚠️ Basic |
| Internships/Show.vue | 254 | ✅ Built |
| Events/Index.vue | 66 | ⚠️ Basic |
| Events/Create.vue | 121 | ✅ Built |
| Events/Edit.vue | 9 | ❌ Stub |
| Events/Registrations.vue | 51 | ⚠️ Basic |
| Reports/Index.vue | 36 | ❌ Stub — links only |
| Reports/Executive.vue | 44 | ❌ Stub — placeholder |
| Reports/Compliance.vue | 61 | ⚠️ Basic |
| Reports/Department.vue | 51 | ⚠️ Stub |
| Reports/Internship.vue | 51 | ⚠️ Stub |
| Settings/Index.vue | 75 | ⚠️ Basic |
| AuditLogs/Index.vue | 97 | ⚠️ Basic |
| AuditLogs/Show.vue | 39 | ❌ Stub |
| Billing/Index.vue | 82 | ⚠️ Basic |

### Auth Pages
| Page | Lines | Status |
|------|-------|--------|
| Login.vue | 106 | ✅ Built |
| Register.vue | 129 | ✅ Built |
| ForgotPassword.vue | 58 | ✅ Built |
| ResetPassword.vue | 81 | ✅ Built |
| VerifyEmail.vue | 53 | ✅ Built |

---

## 2. Per-Role Gap Analysis

---

### ROLE: LEARNER

#### Dashboard (Dashboard.vue — 344 lines)
- **Doc says:** Today's Plan (3 adaptive tiles), Readiness Meter (circular gauge with pass likelihood + exam countdown), Streak + Next Reward (flame icon, XP bar, next badge ETA, streak rescue button), Focus Areas (top 3 weak domains with mastery % + trend arrows), Continue Where You Left Off (resume card), Coach Insight (personalized one-sentence insight). Adaptive plan logic based on exam proximity, readiness level, and error patterns.
- **Current:** ✅ Today's Plan tiles (adaptive, max 3). ✅ Readiness meter (circular gauge with label). ✅ Streak counter + XP progress bar. ✅ Continue Learning card with progress. ✅ Coach Insight from error patterns. ✅ Daily missions. ✅ Recent badges. ✅ Spaced review due count.
- **Gap:**
  - ❌ **No Focus Areas section** — top 3 weak domains with mastery % and trend arrows are missing
  - ❌ **No exam countdown** — readiness meter doesn't show "14 days left" even though `exam_date` is stored
  - ❌ **No pass likelihood text** — "unlikely / possible / likely / very likely" not displayed
  - ❌ **No streak rescue button** — no protection token usage UI
  - ❌ **No next badge ETA** — streak section shows count but not next badge or predicted date
  - ⚠️ Plan adaptivity is basic (flashcards → lesson → generic quiz) — doesn't weight based on exam proximity or readiness < 50% as spec requires

#### Onboarding (Onboarding/Index.vue — 125 lines)
- **Doc says:** 4 steps: Welcome → Goal Selection (3 cards) → Profile Setup (exam date, daily minutes slider, preferred format, experience level, accessibility prefs) → Diagnostic (optional 10-question adaptive assessment) → Quick Win (mandatory micro-lesson + 5-question check + celebration animation + XP award).
- **Current:** Generic 4-step wizard: Goal selection → Profile info → Browse first course → Success.
- **Gap:**
  - ❌ **No diagnostic quick assessment** — step 3 skipped entirely
  - ❌ **No Quick Win challenge** — no micro-lesson or knowledge check
  - ❌ **Goal options don't match spec** — uses general goals (PM, Data Analysis, etc.) instead of "Pass my exam / Build management skills / Start an internship"
  - ❌ **No daily study time slider** — critical for plan generation
  - ❌ **No learning preference picker** (Read / Watch / Listen)
  - ❌ **No accessibility preferences** step
  - ❌ **No celebration animation** or XP award on completion
  - ❌ **No personalized daily plan generation** output

#### Course Catalog (Courses/Index.vue — 178 lines)
- **Doc says:** Grid/list toggle, filters (category, level, type, price, duration), search with autocomplete (Meilisearch), sort (popularity, rating, newest, price), course cards showing thumbnail, title, level badge, rating, price, duration, enrollment count.
- **Current:** ✅ Grid/list toggle. ✅ Search. ✅ Category filter pills. ⚠️ Basic filter form.
- **Gap:**
  - ❌ **No sort options** (by popularity, rating, newest, price)
  - ❌ **No level filter** (beginner/intermediate/advanced)
  - ❌ **No type filter** (self-paced, live, certified, bootcamp)
  - ❌ **No price filter** (free/paid range)
  - ❌ **No duration filter**
  - ❌ **No search autocomplete** (Meilisearch integration incomplete)

#### Course Detail (Courses/Show.vue — 284 lines)
- **Doc says:** Hero (thumbnail/preview video, rating, enrollment count), Tabs (Overview | Curriculum | Resources | Reviews), course ratings display (star distribution + user reviews), CTA variations, price + discount display.
- **Current:** ✅ Hero section. ✅ Overview (description, outcomes, prerequisites, instructor). ✅ Curriculum accordion. ✅ Enroll/continue button. ✅ Price + discount display.
- **Gap:**
  - ❌ **No Reviews tab** — course ratings and user reviews not displayed
  - ❌ **No Resources tab** — downloadable templates/checklists not listed
  - ❌ **No preview video** player
  - ❌ **No star distribution chart** for ratings
  - ❌ **No "Write a Review" form**

#### Lesson Page (Lessons/Show.vue — 302 lines)
- **Doc says:** Tab bar (Read | Watch | Listen | PDF | Practice), skimmable summary, key terms table, video player with chapters/captions/speed, audio player, PDF viewer, practice quiz (1-5 questions, 70% pass required), highlight-to-save notes, bookmark, "Add to flashcards" for key terms, progress bar, prev/next navigation.
- **Current:** ✅ Multi-tab layout (read/watch/listen/pdf/practice). ✅ Video player with resume. ✅ Sidebar curriculum nav. ✅ Bookmarks.
- **Gap:**
  - ❌ **No in-video quiz prompts** (every 5-8 min for videos > 10 min)
  - ❌ **No video chapter markers/timestamps**
  - ❌ **No playback speed controls** (0.75x–2x)
  - ❌ **No transcript panel**
  - ❌ **No "Add to flashcards" button** for key terms
  - ❌ **No highlight-to-save** notes feature
  - ⚠️ Practice tab exists but doesn't enforce 70% pass for lesson completion
  - ⚠️ Key terms display exists but is basic

#### Quiz Player (Quizzes/Player.vue — 307 lines)
- **Doc says:** One question/screen, randomized options, prev/next/flag, question grid, timer, confidence rating (Low/Med/High), post-quiz: score summary, domain breakdown chart, per-question review with explanations for all options, time analysis, "Fix this" button (→ spaced repetition), "Go to lesson" link.
- **Current:** ✅ Full quiz player with timer, nav grid, confidence rating, flagging. ✅ Question card rendering. ✅ Progress bar.
- **Gap:**
  - ⚠️ **Post-quiz review** exists (Review.vue, 157 lines) but needs verification of: explanations for ALL wrong options (not just correct), "Fix this" → add to SR, "Go to lesson" link, time-per-question analysis
  - ❌ **No time-per-question analysis** in review

#### Mock Exam (MockExams/ — 4 pages)
- **Doc says:** Full-length/mini/domain-focused mocks. Simulation mode (no explanations during). Pause/resume. Post-mock report includes: domain breakdown, time analysis, confidence analysis, weakness clusters, top 5 fix priorities, auto-generated 7-day improvement plan.
- **Current:** ✅ Mock listing, detail, player (240 lines). ✅ Report (289 lines) with domain analysis, time analysis tabs.
- **Gap:**
  - ❌ **No confidence analysis tab** (questions where confidence mismatched result)
  - ❌ **No weakness clusters** visualization (groups of related weak subtopics)
  - ❌ **No 7-day improvement plan** auto-generation
  - ❌ **No pause/resume capability** in mock player
  - ⚠️ Fix priorities may be present but need deeper review

#### Spaced Repetition (Practice/Review.vue — 131 lines)
- **Doc says:** Flashcard flip UI, quality rating (0-5), progress through deck, interleaving across topics, items from failed quizzes / lesson key terms / user-created. SM-2 algorithm calculates intervals.
- **Current:** ✅ Flashcard flip UI. ✅ Quality rating. ✅ Session progress.
- **Gap:**
  - ⚠️ SM-2 algorithm is implemented in backend but **interleaving across topics** may not be verified
  - ⚠️ "Fix Cards" from failed quiz auto-add needs verification

#### Simulations (4 pages)
- **Doc says:** Branching scenarios with story/decision/consequence nodes, running score, replay, scorecard (Judgment/Ethics/Stakeholder/Risk), best practice path comparison (side by side).
- **Current:** ✅ Player with story/decision/consequence flow (195 lines). ⚠️ Report (119 lines).
- **Gap:**
  - ❌ **No best practice path comparison** (your path vs. optimal path side-by-side)
  - ❌ **No replay button** functionality
  - ⚠️ Scorecard dimensions may be incomplete

#### Community (Forums + Messages + Study Groups)
- **Doc says:** Course-specific forums, cohort forums, nested replies, accepted answers, moderator actions (pin/lock/delete), 1-to-1 messaging with real-time WebSocket, study groups (max 10 members).
- **Current:** Forums (3 pages, 80-123 lines each), Messages (2 pages, 90-91 lines each), StudyGroups (2 pages, 66-81 lines).
- **Gap:**
  - ❌ **No real-time messaging** (no WebSocket/Reverb integration)
  - ❌ **No "accepted answer"** feature in forums
  - ❌ **No moderator actions** (pin, lock, delete)
  - ❌ **No read receipts** in messaging
  - ⚠️ **Study groups** are minimal stubs

#### Certificates & Portfolio
- **Doc says:** QR-verifiable certificates, portfolio builder with skill tags, reordering, public/private toggle, PDF export.
- **Current:** Certificates (3 pages, 51-59 lines each). Portfolio (3 pages, 42-159 lines).
- **Gap:**
  - ❌ **No QR code rendering** on certificate view
  - ❌ **No PDF download** button on certificate
  - ❌ **No skill tagging** on portfolio items
  - ❌ **No reordering** portfolio items (drag)
  - ❌ **No public/private toggle** per portfolio item
  - ⚠️ PDF export page exists (62 lines) but may be non-functional

#### Events & Pathways
- **Doc says:** Platform-wide event calendar, RSVP, reminders, replay. Career pathways as visual roadmaps with progress.
- **Current:** Events (70 + 88 lines). Pathways (51 + 78 lines).
- **Gap:**
  - ❌ **No calendar view** for events (just a list)
  - ❌ **No RSVP functionality**
  - ❌ **No visual roadmap** for career pathways (just a list)
  - ❌ **No progress indicator** on pathways

#### Storefront & Commerce
- **Doc says:** Product catalog, cart, discount code, payment gateway choice (Paystack/Flutterwave/Stripe), confirmation, auto-enrollment.
- **Current:** Storefront (Index 120 + Show 170). Orders (66 + 121).
- **Gap:**
  - ❌ **No cart functionality**
  - ❌ **No discount code input** in checkout
  - ❌ **No gateway selection** UI (Paystack/Flutterwave/Stripe toggle)
  - ⚠️ Payment redirect exists in Show.vue but flow is basic

#### Downloads / Offline
- **Doc says:** Download button per lesson/module/course, download manager showing status + storage used, offline content access, auto-sync on reconnect.
- **Current:** Downloads/Index.vue (119 lines).
- **Gap:**
  - ❌ **Non-functional** — UI only, no actual IndexedDB/SW download logic
  - ❌ **No offline content access**
  - ❌ **No background sync**

---

### ROLE: FACILITATOR

#### Dashboard (Facilitator/Dashboard.vue — 217 lines)
- **Doc says:** Cohort overview with health scores, at-risk learner count, quick access to all cohort management functions, grading queue count, upcoming sessions.
- **Current:** ✅ Welcome banner. ✅ Quick stats (total learners, active cohorts, at-risk, avg health). ✅ Cohort cards with health bars. ✅ Recent interventions.
- **Gap:**
  - ❌ **No grading queue count** — facilitator doesn't see pending assignments to grade
  - ❌ **No upcoming live sessions** section
  - ❌ **No "quick action" buttons** (start live session, send nudge, create assignment)

#### Cohort Health Dashboard (Cohorts/Show.vue — 354 lines)
- **Doc says:** Health score gauge (composite), mastery heatmap (learners × domains), engagement heatmap (learners × days), mock score distribution histogram, at-risk learner cards with reasons + suggested interventions.
- **Current:** ✅ Health score gauge. ✅ Health breakdown (4 metrics). ✅ Mastery heatmap (learners × domains). ✅ Engagement heatmap. ✅ At-risk learner section.
- **Gap:**
  - ❌ **No mock score distribution histogram** — the chart showing latest mock scores with pass line is missing
  - ❌ **No suggested interventions** per at-risk learner (spec says: each shows name, reason, last active, suggested intervention)
  - ⚠️ Engagement heatmap may be present but needs verification of 4-week view

#### Learner 360 Profile (Cohorts/Learner360.vue — 196 lines)
- **Doc says:** Everything about one learner: progress vs plan, mastery map, mock history + timing patterns, error patterns, simulation decisions/scores, assignment grades, intervention log, attendance record.
- **Current:** ⚠️ Built at 196 lines — likely shows progress, mastery, mock history.
- **Gap:**
  - ❌ **No error patterns** section per learner
  - ❌ **No simulation decisions/scores** view
  - ❌ **No attendance record** display
  - ❌ **No intervention log** for this specific learner
  - ❌ **No progress vs. plan** comparison (actual vs expected timeline)

#### Weekly Plan Builder (Plans/Builder.vue — 277 lines)
- **Doc says:** Calendar/week view, drag items (lessons, quizzes, mocks, simulations, assignments), set mandatory/optional, set due dates, auto-suggest based on cohort weaknesses.
- **Current:** ✅ Built at 277 lines.
- **Gap:**
  - ❌ **No auto-suggest** ("Based on cohort weaknesses, add Risk Management drill")
  - ⚠️ Drag-and-drop may not be fully functional on mobile

#### Live Sessions
- **Doc says:** Schedule with calendar picker, add video platform link, auto-notify learners, attendance marking, post-session notes/replay upload/follow-up quiz assignment.
- **Current:** Meetings/Index.vue (240 lines) + MeetingCard.vue (118 lines).
- **Gap:**
  - ❌ **No attendance marking UI** (present/absent/excused)
  - ❌ **No post-session notes** recording
  - ❌ **No replay upload** capability
  - ❌ **No follow-up quiz assignment** from session

#### Grading (Assignments/Grading.vue — 172 lines)
- **Doc says:** Assignment inbox with submission queue, rubric scoring UI (slider/dropdown per criterion), feedback text + voice recording, return for revision with notes, grade history per learner.
- **Current:** ✅ Grading page with rubric scoring and feedback.
- **Gap:**
  - ❌ **No voice recording** for feedback
  - ❌ **No grade history** per learner (across assignments)
  - ⚠️ "Return for revision" may exist but needs confirmation

#### Nudging & Communication
- **Doc says:** Pre-built nudge templates, custom coaching messages (text + voice), escalation to org admin / line manager.
- **Current:** Interventions page exists (210 lines).
- **Gap:**
  - ❌ **No pre-built nudge templates** to select from
  - ❌ **No voice message** recording
  - ❌ **No escalation mechanism** to org admin

#### Internship Oversight
- **Doc says:** View internship cohort health (timeliness, quality scores, sponsor satisfaction), see sponsor feedback patterns, intervene on stuck interns, audit portfolio completeness, generate completion recommendations.
- **Current:** Health.vue (106 lines) + Portfolios.vue (79 lines).
- **Gap:**
  - ❌ **No sponsor satisfaction metrics** display
  - ❌ **No feedback pattern analysis**
  - ❌ **No completion recommendation generator**
  - ⚠️ Pages are basic overview stubs

---

### ROLE: SME REVIEWER

#### Dashboard
- **Doc says:** Dashboard with content workflow stats, pending reviews, question bank health, flag counts.
- **Current:** AnalyticsDashboard.vue (101 lines) — focused on question analytics (difficulty/discrimination distributions).
- **Gap:**
  - ❌ **No dedicated SME dashboard** — the AnalyticsDashboard is item analytics, not a homepage dashboard
  - ❌ **No pending review count** on dashboard
  - ❌ **No content workflow overview** (draft/review/approved/published counts)
  - ❌ **No recent activity** feed

#### Content Workflow (ContentLibrary + ContentReview + PendingQueue)
- **Doc says:** Full workflow: Draft → Review → Approved → Published → Version → Archive. Author submits, SME reviews (approve/reject/request changes), diff view for version comparison.
- **Current:** ContentLibrary.vue (117 lines) — list with status filter. ContentReview.vue (195 lines) — approve/reject/request changes. PendingQueue.vue (72 lines) — pending items.
- **Gap:**
  - ❌ **No diff view** — version comparison not rendered (diff data passed but not visualized)
  - ❌ **No "Archive" action** in workflow
  - ⚠️ Workflow state transitions exist in backend but UI may not show all states

#### Question Bank Management (QuestionBank.vue — 113 lines)
- **Doc says:** Full CRUD with rich filters, tagging (domain/subtopic/difficulty/type/Bloom's), blueprint mapping, exposure control, flagged item review.
- **Current:** Question listing with filters, pagination.
- **Gap:**
  - ❌ **No inline editing** of questions from this view
  - ❌ **No blueprint mapping** visualization (coverage vs exam blueprint)
  - ❌ **No exposure control warnings** (visual indicator for over-exposed questions)
  - ⚠️ QuestionBank shows stats but editing redirects to admin pages

#### Item Quality Analytics (ItemAnalytics.vue — 99 lines)
- **Doc says:** Difficulty distribution chart, discrimination index, distractor effectiveness, time-to-answer stats, explanation quality rating.
- **Current:** Basic analytics view (99 lines).
- **Gap:**
  - ❌ **No distractor effectiveness** analysis (which options nobody picks)
  - ❌ **No explanation quality rating** from learner feedback
  - ⚠️ Difficulty and discrimination available at aggregate level in AnalyticsDashboard

#### Versioning (Versions.vue — 73 lines)
- **Doc says:** Version history, diff between versions, revert capability.
- **Current:** Version listing (73 lines).
- **Gap:**
  - ❌ **No diff view** between versions (side-by-side comparison)
  - ❌ **No revert** functionality
  - ⚠️ Just a list of version snapshots

---

### ROLE: SPONSOR

#### Dashboard
- **Doc says:** Dashboard with intern overview, pending review count, upcoming meetings.
- **Current:** ❌ **No Sponsor Dashboard page** — route goes directly to ReviewQueue
- **Gap:**
  - ❌ **No dedicated dashboard** with overview metrics (total interns, pending reviews, avg score, upcoming meetings)
  - ❌ **No "Meeting Hub"** page as specified in docs

#### Review Queue (ReviewQueue.vue — 78 lines)
- **Doc says:** List of pending submissions sorted by due date, badge showing count, filter by intern/task type/date.
- **Current:** ✅ Submission list with pagination.
- **Gap:**
  - ❌ **No filters** (by intern, task type, date range)
  - ❌ **No count badge** showing pending items

#### Submission Review (ReviewSubmission.vue — 140 lines)
- **Doc says:** View files in-app (PDF viewer, image viewer), download files, rubric scoring (criteria list, each 0-5 with optional comment), overall feedback, voice note recording, approve/request revision, risk flags.
- **Current:** ✅ Files display. ✅ Review form with decision, score, feedback, risk flags.
- **Gap:**
  - ❌ **No in-app PDF viewer** — just download links
  - ❌ **No rubric criteria scoring** (individual criteria, not just overall score) — form has `overall_score` but no per-criterion scores
  - ❌ **No voice note recording** for feedback
  - ⚠️ Risk flags exist but as checkboxes, not expandable with details

#### Scorecard (Scorecard.vue — 118 lines)
- **Doc says:** Weekly/monthly aggregate ratings per category (Quality, Timeliness, Communication, Initiative, Technical Skill), each 1-5, overall comments, improvement actions, "Ready for next milestone" toggle.
- **Current:** ✅ Full scorecard form with all categories, improvement actions, ready toggle.
- **Gap:** ✅ **Minimal gap** — this page is well-built

#### Intern Progress (InternProgress.vue — 139 lines)
- **Doc says:** Task list with status, milestones, scorecards, submission history.
- **Current:** ✅ Progress overview, milestones, task list, scorecards.
- **Gap:**
  - ❌ **No progress chart/trend** over time (score trend graph)
  - ❌ **No sponsor feedback score trend** as mentioned in learner's internship dashboard spec

#### Meeting Hub
- **Doc says:** Schedule meetings, calendar view, platform link, attendance, notes, action items → tasks.
- **Current:** ❌ **No MeetingHub page** for sponsors — missing entirely
- **Gap:**
  - ❌ **Entire page missing** — sponsors cannot schedule or view meetings

---

### ROLE: ADMIN

#### Dashboard (Admin/Dashboard.vue — 161 lines)
- **Doc says:** Active users (DAU/WAU), enrollment trends, revenue metrics, completion rates, at-risk user count, top performing courses.
- **Current:** ✅ Stat cards (users, learners, revenue, completion). ⚠️ Charts are placeholders ("Chart renders with real data"). ✅ Recent registrations table. ✅ Top courses.
- **Gap:**
  - ❌ **Charts not rendering** — enrollment trend and revenue charts are placeholder divs
  - ❌ **No DAU/WAU display** (data is computed in controller but not rendered in Vue page)
  - ❌ **No at-risk user count** displayed (also computed but not passed correctly — controller sends `metrics` but Vue expects `stats`)
  - ⚠️ **Props mismatch**: controller sends `metrics`, `topCourses`, `enrollmentTrend` but Vue expects `stats`, `recentUsers`, `topCourses`, `enrollmentTrend` — `recentUsers` is never sent from controller

#### Reports (4 pages, 36-61 lines each)
- **Doc says:** Executive summary (PDF export), compliance report (who completed what by when), cohort performance, department report, internship performance, CSV export, scheduled report delivery.
- **Current:** Reports/Index.vue (36 lines) — just navigation links. Others are minimal stubs.
- **Gap:**
  - ❌ **No PDF export** on any report
  - ❌ **No CSV export** on any report
  - ❌ **No actual data** in executive report (44 lines — likely a placeholder)
  - ❌ **No scheduled report delivery** setup
  - ❌ **No cohort performance report** (not listed)

#### User Management
- **Doc says:** User list with search/filter, add manually/CSV import, assign roles, assign departments, set exam dates/goals, deactivate/reactivate.
- **Current:** Users/Index.vue (203 lines) ✅, Create (71) ⚠️, Edit (75) ⚠️, Import (72) ⚠️.
- **Gap:**
  - ❌ **No role assignment** dropdown in user create/edit forms (needs verification)
  - ❌ **No department assignment** in user forms
  - ❌ **No exam date/goal setting** in user forms
  - ⚠️ Import is basic — may not handle 1000+ rows or show progress

#### Settings
- **Doc says:** Platform branding (logo, colors), notification policies, data retention rules, privacy settings, billing/seat management.
- **Current:** Settings/Index.vue (75 lines). Billing/Index.vue (82 lines).
- **Gap:**
  - ❌ **No branding customization** (logo, colors upload)
  - ❌ **No data retention rules** configuration
  - ❌ **No privacy settings** (GDPR controls)
  - ⚠️ Billing page exists but likely basic

#### Audit Logs
- **Doc says:** All sensitive actions logged, filter by user/action/entity/date, exportable.
- **Current:** AuditLogs/Index.vue (97 lines), Show.vue (39 lines — stub).
- **Gap:**
  - ❌ **No export** (CSV/PDF) for audit logs
  - ❌ **Show page is a stub** (39 lines)

---

## 3. Missing Pages (Doc-Specified but Unbuilt)

| Page (from docs) | Expected Location | Status |
|---|---|---|
| Home/Index.vue (separate learner home engine) | Pages/Home/Index.vue | ❌ Merged into Dashboard.vue (ok) |
| SpacedRepetition/Review.vue | Pages/SpacedRepetition/ | ❌ Replaced by Practice/Review.vue (ok) |
| Sponsor/Dashboard.vue | Pages/Sponsor/Dashboard.vue | ❌ **MISSING** — no sponsor dashboard |
| Sponsor/MeetingHub.vue | Pages/Sponsor/MeetingHub.vue | ❌ **MISSING** — no meeting hub for sponsors |
| SME/Dashboard.vue | Pages/Sme/Dashboard.vue | ❌ **MISSING** — no SME dashboard (using AnalyticsDashboard) |
| Admin/Organizations/ (Index, Show) | Pages/Admin/Organizations/ | ❌ **MISSING** — no organization management pages |
| Admin/Programs/Assign.vue | Pages/Admin/Programs/Assign.vue | ❌ **MISSING** — no program assignment page |
| Admin/Modules/Edit.vue | Pages/Admin/Modules/ | ❌ **MISSING** — module editing is inline |
| Offline/Downloads.vue (as spec'd) | Pages/Offline/Downloads.vue | ❌ Using Downloads/Index.vue (ok but non-functional) |
| Checkout.vue | Pages/Storefront/Checkout.vue | ❌ **MISSING** — checkout is inline in Show.vue |

---

## 4. Top 20 Most Impactful Improvements (Ranked)

### 1. 🔴 ADMIN DASHBOARD: Fix Props Mismatch + Add Real Charts
**Impact:** Admin sees broken/empty dashboard — the most-used admin page  
**Controller should pass:**
```php
return Inertia::render('Admin/Dashboard', [
    'stats' => [
        'totalUsers' => $totalUsers,
        'activeLearners' => $activeLearners,
        'monthlyRevenue' => $monthlyRevenue,
        'completionRate' => $completionRate,
        'usersChange' => $usersChangePercent,    // change vs last month
        'learnersChange' => $learnersChange,
        'revenueChange' => $revenueChange,
        'completionChange' => $completionChange,
        'dau' => $dau,
        'wau' => $wau,
        'atRiskCount' => $atRiskCount,
    ],
    'recentUsers' => $recentUsers,  // ← MISSING from controller!
    'topCourses' => $topCourses->map(fn($c) => [
        'id' => $c->id,
        'title' => $c->title,
        'enrollments' => $c->enrollments_count,
        'completionRate' => $c->completion_rate,
    ]),
    'enrollmentTrend' => $enrollmentTrend,  // format: [{date, count}]
]);
```
**Vue should render:** Real Chart.js line/bar charts instead of placeholder divs. Add `recentUsers` query with last 10 registrations.

---

### 2. 🔴 SPONSOR DASHBOARD: Create Missing Page
**Impact:** Sponsors have no landing page — they go directly to review queue with no overview  
**Controller should pass:**
```php
'pendingCount' => $pendingSubmissions,
'totalInterns' => $totalInterns,
'avgScore' => $averageScore,
'upcomingMeetings' => $upcomingMeetings,
'recentReviews' => $lastFiveReviews,
'internSummaries' => $internOverviews,  // [{name, track, progress, score}]
```
**Vue should render:** Stat cards (pending reviews, total interns, avg score) + intern progress list + upcoming meetings + recent reviews.

---

### 3. 🔴 SME DASHBOARD: Create Missing Page
**Impact:** SME reviewers have no overview page — they navigate blind  
**Controller should pass:**
```php
'stats' => [
    'pendingReviews' => $pendingCount,
    'approvedThisWeek' => $approvedCount,
    'flaggedQuestions' => $flaggedCount,
    'totalQuestions' => $totalQuestions,
    'draftContent' => $draftCount,
],
'recentApprovals' => $lastFiveApprovals,
'urgentFlags' => $topFlags,
'blueprintHealth' => $coverageScore,
```
**Vue should render:** Stat cards + pending reviews list + urgent flag queue + blueprint health score.

---

### 4. 🔴 ONBOARDING: Implement Full Spec Flow
**Impact:** First-time user experience is the #1 determinant of retention  
**Controller should pass (per step):**
- Step 1: Goal cards ("Pass my exam" / "Build management skills" / "Start an internship")
- Step 2: `exam_date` picker, `daily_minutes` slider, `learning_preference` icons, `experience_level`, accessibility toggles
- Step 3: Diagnostic quiz (10 adaptive questions) or skip
- Step 4: Quick Win lesson + 5-question check, XP award

**Vue should render:** Full multi-step wizard with proper validation, diagnostic quiz component, celebration animation on completion, and personalized plan generation output.

---

### 5. 🔴 LEARNER DASHBOARD: Add Focus Areas + Exam Countdown
**Impact:** The primary "what to do next" intelligence for learners  
**Controller should add:**
```php
'focusAreas' => $this->masteryService->getWeakDomains($user->id, 3),
// Returns: [{domain, mastery_pct, trend: 'up'|'down'|'stable'}]
'examCountdown' => $user->exam_date ? now()->diffInDays($user->exam_date) : null,
'passLikelihood' => $readiness?->pass_likelihood,
```
**Vue should render:** Focus Areas section with 3 cards showing domain name, mastery %, trend arrow (↑↓→), and a "Practice this" link. Readiness meter should show "X days left" and pass likelihood text.

---

### 6. 🟠 INTERNSHIP TASK BOARD: Add Drag-and-Drop
**Impact:** Core internship workflow — tasks can't be moved between columns  
**Controller:** Already passes tasks with status. No change needed.  
**Vue should render:** Use `@vueuse/core` `useDraggable` or vue-draggable-plus to enable card dragging between Kanban columns. On drop, call `POST /internship/tasks/{id}/status` with new status. Must work on both desktop and mobile (touch events).

---

### 7. 🟠 ADMIN REPORTS: Implement Real Report Pages
**Impact:** Admins cannot generate compliance reports — key enterprise feature  
**Controller should pass:** For Executive: platform-wide KPIs (user growth, engagement, revenue, completion rates, top courses). For Compliance: user × course × completion status × date matrix. For Department: per-department engagement and completion metrics.  
**Vue should render:** Data tables with filters, server-side export buttons (PDF/CSV that trigger Laravel queue jobs), print-friendly layouts.

---

### 8. 🟠 COURSE DETAIL: Add Reviews & Resources Tabs
**Impact:** Social proof (reviews) drives enrollment conversion; resources are key learning aids  
**Controller should add:**
```php
'ratings' => CourseRating::where('course_id', $course->id)
    ->with('user:id,first_name,last_name')
    ->latest()->paginate(10),
'ratingDistribution' => CourseRating::ratingDistribution($course->id),
'resources' => $course->resources,
```
**Vue should render:** Reviews tab with star distribution chart, individual reviews, "Write a Review" form. Resources tab with file icons (PDF/DOCX/XLSX), download links, and download tracking.

---

### 9. 🟠 MOCK EXAM REPORT: Add 7-Day Plan + Confidence Analysis
**Impact:** Post-mock analysis is the #1 learning driver for exam candidates  
**Controller should add:**
```php
'confidenceAnalysis' => $this->getConfidenceAnalysis($attempt),
// [{question_stem, your_confidence, was_correct, type: 'mismatch'|'aligned'}]
'weaknessClusters' => $this->getWeaknessClusters($attempt),
// [{cluster_name, subtopics: [...], improvement_priority: 1-5}]
'sevenDayPlan' => $this->generateSevenDayPlan($attempt),
// [{day: 1, activities: [{type, title, duration, reason}]}]
```
**Vue should render:** Additional tabs for Confidence Analysis (table of mismatches), Weakness Clusters (grouped cards), and 7-Day Plan (day-by-day activity schedule with reasons).

---

### 10. 🟠 FACILITATOR LEARNER 360: Complete All Sections
**Impact:** Facilitators need comprehensive view to make intervention decisions  
**Controller should add:**
```php
'errorPatterns' => $errorPatternService->getPatterns($learnerId),
'simulationHistory' => SimulationAttempt::forUser($learnerId)->get(),
'attendanceRecord' => Meeting::attendanceFor($learnerId),
'interventionLog' => Intervention::where('learner_id', $learnerId)->get(),
'progressVsPlan' => $this->calculateProgressVsPlan($learnerId, $cohortId),
```
**Vue should render:** Add Error Patterns section (card per pattern), Simulation history (scores + decisions), Attendance table (date, status), Intervention log (timeline), Progress vs. Plan chart (expected vs. actual line).

---

### 11. 🟠 SPONSOR MEETING HUB: Create Missing Page
**Impact:** Sponsors cannot schedule or manage meetings with interns  
**Controller should pass:**
```php
'meetings' => Meeting::where('organizer_id', $sponsorId)->with('attendees')->get(),
'internEnrollments' => InternshipEnrollment::where('sponsor_id', $sponsorId)->with('user')->get(),
```
**Vue should render:** Meeting list with calendar view, scheduling form (title, type, date, platform link, agenda), RSVP status per attendee.

---

### 12. 🟡 COURSE CATALOG: Add Sort + Advanced Filters
**Impact:** Users can't find courses by price, level, or popularity  
**Controller should add:** Sort parameter support (popularity, rating, newest, price). Filter params for level, type, price range, duration.  
**Vue should render:** Sort dropdown + filter sidebar/drawer with level checkboxes, type pills, price range slider, and duration selector.

---

### 13. 🟡 LESSON PAGE: Add Video Features
**Impact:** Video is the primary learning format — missing essential features  
**Controller should pass:** `video_chapters` JSON, `video_transcript` text.  
**Vue should render:** Chapter markers (clickable timestamps in video progress bar), transcript panel (collapsible, highlights current section), playback speed selector (0.75x, 1x, 1.25x, 1.5x, 2x), "Add to flashcards" button on key terms.

---

### 14. 🟡 SME VERSIONING: Add Diff View
**Impact:** SMEs can't compare content versions — critical for quality governance  
**Controller should pass:** Previous version content alongside current version.  
**Vue should render:** Side-by-side diff view using a diff library (diff2html or custom), highlighting additions (green) and removals (red).

---

### 15. 🟡 COMMUNITY: Add Real-Time Messaging
**Impact:** Direct messaging without real-time is effectively email — not useful  
**Controller:** WebSocket channel setup via Laravel Reverb.  
**Vue should render:** Real-time message delivery using Echo/Reverb, typing indicators, read receipts, notification sound.

---

### 16. 🟡 ADMIN ORG MANAGEMENT: Create Missing Pages
**Impact:** Multi-tenant features are blocked without org management  
**Controller should pass:** Org list with seat counts, user allocation, subscription plan. Org detail with users, departments, compliance.  
**Vue should render:** Admin/Organizations/Index.vue (org table with search) + Show.vue (org users, departments, seat management, billing).

---

### 17. 🟡 GAMIFICATION: Enhance Badges + Streak Rescue
**Impact:** Gamification drives daily retention — streak rescue prevents disengagement  
**Controller should add:** `protectionTokens`, `nextBadge` (name + criteria + ETA), `restDayAvailable`.  
**Vue should render:** Dashboard streak section with rescue button, badge page with earned/unearned states and progress indicators, rest day mode toggle.

---

### 18. 🟡 SIMULATION REPORT: Add Path Comparison
**Impact:** Learning from simulations requires seeing what the best practice was  
**Controller should pass:** `bestPracticePath` (array of optimal decisions) alongside `userPath`.  
**Vue should render:** Side-by-side path comparison showing each decision point: your choice vs. optimal choice, with explanation of why optimal was better and links to relevant lessons.

---

### 19. 🟡 CAREER PATHWAYS: Build Visual Roadmap
**Impact:** Career pathways drive long-term engagement and course sequencing  
**Controller should pass:** `pathway` with ordered courses, completion status per course, skills mapped, recommended next.  
**Vue should render:** Visual vertical timeline/roadmap with course nodes (completed: green, current: blue, future: gray), skill tags per node, overall progress indicator.

---

### 20. 🟡 EVENTS CALENDAR: Add Calendar View + RSVP
**Impact:** Event participation drives community engagement  
**Controller should pass:** Events with dates, types, RSVP status.  
**Vue should render:** Monthly/weekly calendar view (using a calendar component or custom grid), event cards with RSVP buttons (Accept/Decline/Tentative), filter by event type.

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| Total Vue pages | 107 |
| Fully built (✅) | ~42 (39%) |
| Basic/partial (⚠️) | ~48 (45%) |
| Stubs/missing (❌) | ~17 (16%) |
| Missing entire pages (doc-specced) | 6 |
| Total lines of Vue code | ~15,000+ |
| Critical gaps (blocking functionality) | 5 |
| Important gaps (degraded experience) | 15+ |

### Priority Matrix

| Priority | Category | Count |
|----------|----------|-------|
| 🔴 Critical | Dashboards missing/broken, onboarding, data mismatches | 5 |
| 🟠 High | Reports, drag-drop, 7-day plans, missing pages | 6 |
| 🟡 Medium | Filters, video features, real-time, path comparison | 9 |
| 🟢 Low | Offline, calendar views, voice recording | 5+ |

### Estimated Effort Per Improvement

| # | Improvement | Estimated Hours |
|---|-------------|----------------|
| 1 | Admin Dashboard fix | 4-6h |
| 2 | Sponsor Dashboard | 8-12h |
| 3 | SME Dashboard | 6-8h |
| 4 | Onboarding full flow | 16-24h |
| 5 | Learner Dashboard focus areas | 4-6h |
| 6 | Task Board drag-and-drop | 8-12h |
| 7 | Admin Reports | 16-24h |
| 8 | Course Reviews/Resources tabs | 8-12h |
| 9 | Mock Report 7-day plan | 8-12h |
| 10 | Learner 360 complete | 12-16h |
| 11 | Sponsor Meeting Hub | 8-12h |
| 12 | Course Catalog filters | 4-6h |
| 13 | Lesson video features | 8-12h |
| 14 | SME Versioning diff | 6-8h |
| 15 | Real-time messaging | 12-16h |
| 16 | Admin Org Management | 12-16h |
| 17 | Gamification enhancements | 6-8h |
| 18 | Simulation path comparison | 6-8h |
| 19 | Career Pathways roadmap | 8-12h |
| 20 | Events Calendar | 6-8h |
| | **TOTAL** | **~165-230h** |

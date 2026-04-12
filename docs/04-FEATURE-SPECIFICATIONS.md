# JK Michaels Professional Mastery Platform — Feature Specifications

## Document Control

| Field | Value |
|---|---|
| Version | 1.0 |
| Date | February 26, 2026 |

---

## Table of Contents

1. [Onboarding & Personalization](#1-onboarding--personalization)
2. [Home Screen & Daily Engine](#2-home-screen--daily-engine)
3. [Courses & Multi-Format Learning](#3-courses--multi-format-learning)
4. [Assessment Engine](#4-assessment-engine)
5. [Spaced Repetition & Memory System](#5-spaced-repetition--memory-system)
6. [Error Pattern Detection](#6-error-pattern-detection)
7. [Analytics & Mastery Dashboard](#7-analytics--mastery-dashboard)
8. [Gamification System](#8-gamification-system)
9. [Cohort & Facilitator System](#9-cohort--facilitator-system)
10. [Internship & Project Lab](#10-internship--project-lab)
11. [Simulations](#11-simulations)
12. [Certificates & Portfolio](#12-certificates--portfolio)
13. [Commerce & Payments](#13-commerce--payments)
14. [Notifications & Reminders](#14-notifications--reminders)
15. [Meetings & Events](#15-meetings--events)
16. [Community](#16-community)
17. [Career Pathways](#17-career-pathways)
18. [Admin Console](#18-admin-console)
19. [SME Governance Console](#19-sme-governance-console)
20. [Accessibility & Offline](#20-accessibility--offline)

---

## 1. Onboarding & Personalization

### Goal
Lock in retention within the first 2 minutes. Personalize immediately and create quick momentum.

### User Flow
```
Welcome Screen → Goal Selection → Profile Setup → Diagnostic Opt-in → Quick Win → Home
```

### Requirements

**1.1 Welcome Screen**
- JK Michaels branded splash
- "Get Started" CTA
- Social proof: "Join 10,000+ professionals"

**1.2 Goal Selection (Step 1 of 4)**
- Three cards: "Pass my exam" / "Build management skills" / "Start an internship"
- Single select, large touch targets
- Skip option (defaults to "skill")

**1.3 Profile Setup (Step 2 of 4)**
- Exam date picker (optional)
- Daily study time slider: 10 / 15 / 20 / 30 / 45 / 60 min
- Preferred format: Read / Watch / Listen (icon cards)
- Experience level: Beginner / Intermediate / Advanced
- Accessibility preferences (expandable): dyslexia font toggle, high contrast, color blind mode, extended time

**1.4 Diagnostic Quick Assessment (Step 3 of 4, Optional)**
- "Take a 5-minute assessment to personalize your plan?"
- If yes: 10 adaptive questions across key domains
- If no: use experience_level as baseline

**1.5 Quick Win Challenge (Step 4 of 4, Mandatory)**
- Auto-selected micro-lesson (3 min read)
- Followed by 5-question knowledge check
- Celebration animation on completion
- "You're on your way!" + recommended next step
- XP awarded: 25 XP for completing onboarding

**1.6 Outputs Generated**
- Personalized daily plan (3 activities)
- Starting mastery map (if diagnostic taken)
- Track recommendation + career pathway suggestion
- Notification channel confirmation

### Acceptance Criteria
- [ ] Onboarding can be completed in under 2 minutes (excluding diagnostic)
- [ ] Diagnostic, if taken, completes in under 5 minutes
- [ ] User receives first XP and sees streak start
- [ ] Daily plan is generated and visible on home screen
- [ ] All preferences saved to user profile
- [ ] Skip paths work without breaking the flow

---

## 2. Home Screen & Daily Engine

### Goal
Drive daily return behavior. Show the user exactly what to do in their available time.

### Layout (Mobile-first, max 3 scroll sections visible)

**2.1 Today's Plan (Top)**
- Max 3 activity tiles based on `daily_minutes` and current mastery
- Each tile shows: icon, title, estimated time, type label
- Tile types: Quiz, Lesson, Simulation, Flashcard Review, Mock Exam
- Tap → goes directly to the activity
- Completion checkmark animation on each tile

**2.2 Readiness Meter**
- Circular gauge showing % readiness
- Confidence band label (Low / Medium / High)
- Exam countdown if `exam_date` is set
- "Pass likelihood" text (optional: unlikely / possible / likely / very likely)

**2.3 Streak + Next Reward**
- Flame icon + "X day streak"
- XP progress bar to next level
- Next badge with ETA
- Streak rescue button (if missed yesterday, has protection tokens)

**2.4 Focus Areas**
- Top 3 weak domains/subtopics
- Each shows mastery % + trend arrow
- "Fastest improvement" recommendation with reasoning

**2.5 Continue Where You Left Off**
- Resume card: last lesson / mock / simulation / internship task
- Progress indicator
- Tap to resume

**2.6 Coach Insight**
- One-sentence personalized insight
- Example: "Your biggest leak is time-per-question on scenario items."
- Generated from error patterns + recent performance
- Expandable for action recommendation

### Adaptive Plan Logic
```
IF exam_date < 14 days:
  weight mocks + weak domain drills heavily
ELIF readiness < 50%:
  weight lessons + flashcard reviews
ELIF error_patterns detected:
  include targeted fix activities
ELSE:
  balanced mix of lesson + quiz + simulation
```

### Acceptance Criteria
- [ ] Home loads in < 500ms
- [ ] Plan adapts based on mastery data
- [ ] Streak displays correctly including protection status
- [ ] Continue card shows most recent unfinished activity
- [ ] Coach insight updates based on last 7 days data

---

## 3. Courses & Multi-Format Learning

### Goal
Support all learning preferences with high-quality, multi-format content.

### 3.1 Course Catalog
- Grid/list toggle
- Filters: category, level, type, price (free/paid), duration
- Search bar with autocomplete (Meilisearch)
- Sort: popularity, rating, newest, price
- Course cards: thumbnail, title, level badge, rating, price, duration, enrollment count

### 3.2 Course Detail Page
- Hero section: thumbnail/preview video, title, subtitle, rating, enrollment count
- Tabs: Overview | Curriculum | Resources | Reviews
- Overview: description, learning outcomes, prerequisites, instructor
- Curriculum: expandable module list → lesson titles + duration + completion status
- Resources: downloadable templates, checklists, etc.
- Reviews: star distribution + user reviews
- CTA: "Enroll" or "Continue Learning" button
- Price + discount display

### 3.3 Lesson Page
Each lesson presents content in multiple formats via a tab bar:

**Tab: Read**
- Skimmable summary (expandable for full text)
- Key terms table
- Examples with visual formatting
- Estimated read time

**Tab: Watch**
- Video player (adaptive bitrate)
- Chapter markers/timestamps
- In-video quiz prompts (every 5-8 min for videos > 10 min)
- Captions/subtitles toggle
- Playback speed: 0.75x, 1x, 1.25x, 1.5x, 2x
- Transcript panel (collapsible)
- Resume from last position

**Tab: Listen**
- Audio player with playback controls
- Background playable (mobile)
- Speed controls
- Offline downloadable

**Tab: PDF/Slides**
- In-app viewer
- Download button
- Print-friendly option

**Tab: Practice**
- 1-5 question knowledge check (mandatory for completion)
- Immediate feedback per question
- Must pass (70%) to mark lesson complete

**Additional features on all lesson pages:**
- Highlight text → save to personal notes
- Bookmark button
- "Add to flashcards" for key terms
- Progress bar top of page
- Next/Previous lesson navigation

### 3.4 Resource Library
- Per-course downloadable resources
- Categories: Templates, Checklists, Cheat Sheets, Formula Sheets, Case Studies, Playbooks
- File type icons (PDF, DOCX, XLSX)
- Download tracking (audit)
- Search within resources

### 3.5 Offline Access
- Download button per lesson / module / course
- Download manager screen showing download status + storage used
- Prioritized download: text → practice questions → audio → video
- Auto-sync progress when back online
- Conflict resolution: latest timestamp wins for progress data

### Acceptance Criteria
- [ ] All 5 tabs render correctly per lesson
- [ ] Video resumes from last position
- [ ] In-video quizzes appear at configured intervals
- [ ] Practice quiz enforces 70% pass rate for completion
- [ ] Notes and bookmarks persist across sessions
- [ ] Offline downloaded content is accessible without network
- [ ] Progress syncs correctly when coming back online

---

## 4. Assessment Engine

### Goal
Provide certification-grade practice with deep analytics per attempt.

### 4.1 Quiz Types
| Type | Context | Time | Questions |
|---|---|---|---|
| Lesson Check | After each lesson | Untimed | 1-5 |
| Module Quiz | End of module | Optional timer | 10-20 |
| Domain Practice | Chosen by learner | Timed/Untimed | 10-30 |
| Adaptive Quiz | AI-selected from weak areas | Timed | 10-20 |
| Custom Quiz | Learner picks domain/difficulty/type | Timed/Untimed | 5-50 |

### 4.2 Question Presentation
- One question per screen
- Question stem + optional media (image/diagram)
- Answer options (randomized order)
- Navigation: Previous / Next / Flag for review
- Question grid showing answered/unanswered/flagged
- Timer display (if timed)
- Confidence rating after answering: Low / Medium / High

### 4.3 Answer Review Flow
After completing a quiz/mock:
1. Score summary: correct/total, percentage, pass/fail
2. Domain breakdown chart
3. Per-question review:
   - Your answer highlighted
   - Correct answer highlighted
   - Explanation: why correct is correct
   - Explanation: why each incorrect option is wrong
   - Exam tip
   - "Fix this" button → adds to spaced repetition queue
   - "Go to lesson" link
4. Time analysis: fastest/slowest questions, avg time

### 4.4 Mock Exam System
**Types:**
- Full-length (mirrors real exam: 180 questions / 230 min for PMP, etc.)
- Mini-mock (50 questions / 60 min)
- Domain-focused (30 questions from one domain)

**Features:**
- Exam simulation mode: timer, no explanation during, navigation rules
- Pause/resume capability (configurable)
- Realistic pressure: no "are you sure" prompts in sim mode

**Post-mock report includes:**
- Overall score + pass/fail confidence band
- Domain-by-domain breakdown with chart
- Time analysis: time-per-question distribution, topics that took longest
- Confidence analysis: questions where confidence mismatched result
- Weakness clusters: groups of related weak subtopics
- Top 5 fix priorities
- Auto-generated 7-day improvement plan

### 4.5 Practice by Domain
- Learner selects: domain → subtopic → difficulty → question type → count → timed/untimed
- Questions pulled from bank, excluding recently seen (exposure control)
- Results feed into mastery model

### Acceptance Criteria
- [ ] All question types render correctly
- [ ] Timer works accurately (accounts for pause/resume)
- [ ] Confidence rating is captured per question
- [ ] Post-quiz review shows all explanations
- [ ] Mock exam generates domain breakdown + fix priorities
- [ ] 7-day plan auto-generates based on mock results
- [ ] Wrong answers auto-added to spaced repetition (opt-out available)

---

## 5. Spaced Repetition & Memory System

### Goal
Automatically schedule reviews based on forgetting risk to boost long-term retention.

### Algorithm
SM-2 (SuperMemo 2) implementation:
```
After each review, user rates quality (0-5):
- 0: Complete blackout
- 1: Incorrect but recognized
- 2: Incorrect but easy to recall
- 3: Correct with difficulty
- 4: Correct with hesitation
- 5: Perfect recall

If quality >= 3:
  interval = previous_interval * ease_factor
  ease_factor = max(1.3, ease_factor + (0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02)))
Else:
  repetitions = 0
  interval = 1 day

Schedule next_review_at = now + interval days
```

### Sources of Review Items
1. **Failed quiz questions** → auto-added as Fix Cards
2. **Lesson key terms** → auto-generated flashcards
3. **User-created flashcards** → manual additions
4. **Weak domain questions** → system-selected

### Review Session UX
- "You have X items to review today" notification
- Flashcard flip UI: front → tap → back + quality rating
- Quick session: 5-10 min, ~20 cards
- Shows progress through deck
- Option to skip or snooze remaining cards

### Interleaving
- Mix topics within a session (not all from one domain)
- Helps transfer learning and reduces pattern matching

### Acceptance Criteria
- [ ] SM-2 algorithm correctly calculates intervals
- [ ] Failed quiz questions auto-create review items
- [ ] Daily review count shows on home screen
- [ ] Interleaving works across domains
- [ ] Items graduate after consistent correct responses

---

## 6. Error Pattern Detection

### Goal
Identify systematic mistakes and provide targeted fixes.

### Pattern Types

| Pattern | Detection Logic | Fix Suggestion |
|---|---|---|
| Concept Confusion | Same wrong domain consistently | "Review lesson X vs lesson Y" |
| Careless Mistakes | Fast + Wrong (< 30% expected time) | "Slow down, re-read the question" |
| Overthinking | Slow + Wrong (> 200% expected time) | "Use 30-sec elimination strategy" |
| Confidence Mismatch | High confidence + Wrong | "You may be confusing similar concepts" |
| Qualifier Misread | Wrong on MOST/LEAST/EXCEPT questions | "Highlight qualifiers before answering" |
| Elimination Failure | Wrong on scenario items with obvious eliminations | "Practice 2-pass elimination method" |

### Detection Frequency
- Run analysis after every quiz/mock attempt
- Aggregate over rolling 7-day and 30-day windows
- Minimum sample: 20 questions before pattern detection activates

### Display
- Error patterns section in Analytics dashboard
- Coach insight on home screen references active patterns
- Targeted practice suggestions link to specific drills

### Acceptance Criteria
- [ ] At least 4 pattern types detected accurately
- [ ] Suggestions link to relevant practice activities
- [ ] Patterns update after each quiz/mock
- [ ] Coach insight references detected patterns

---

## 7. Analytics & Mastery Dashboard

### Goal
Give learners and facilitators a clear picture of strengths, weaknesses, and progress.

### 7.1 Learner Dashboard

**Overall Stats:**
- Total courses enrolled / completed
- Hours studied (this week / this month / total)
- Questions answered (total + accuracy)
- Current readiness score

**Mastery Map (Visual)**
- Radar/spider chart for domains
- Each domain: accuracy % + color (red < 50%, yellow 50-75%, green > 75%)
- Drill into subtopics
- Trend arrows (improving / declining / stable)

**Performance Trends:**
- Line chart: readiness score over time (7d / 30d / 90d)
- Line chart: quiz scores over time
- Bar chart: time studied per day/week

**Mock Exam History:**
- Table: date, type, score, pass/fail, time taken
- Tap → detailed post-mock report

**Weekly Report (auto-generated):**
- Hours studied
- Questions answered + accuracy
- Improvement areas
- Achievements
- Next week's focus recommendation

### 7.2 Facilitator Views
(See Section 9 — Cohort views, Learner 360, Heatmaps)

### Acceptance Criteria
- [ ] Mastery map updates after every assessment
- [ ] Trend charts show meaningful data after 3+ data points
- [ ] Weekly report auto-generates and is accessible from dashboard
- [ ] Data is accurate and matches raw quiz/lesson data

---

## 8. Gamification System

### Goal
Professional, meaningful, anti-burnout gamification that drives daily engagement without being childish.

### 8.1 XP System
| Action | XP |
|---|---|
| Complete lesson | 10 |
| Complete quiz (pass) | 15 |
| Complete quiz (90%+) | 25 |
| Complete mock exam | 50 |
| Complete simulation | 30 |
| Daily login | 5 |
| 7-day streak | 50 bonus |
| Complete mission | varies |
| Submit internship task | 20 |
| Review flashcards (daily) | 10 |

### 8.2 Levels
- Level 1-50 progression
- Each level requires progressively more XP
- Title per level: Novice → Apprentice → Practitioner → Professional → Expert → Master → Guru
- Level-up celebration animation

### 8.3 Badges
Categories:
- **Learning:** First Lesson, 10 Lessons, 50 Lessons
- **Mastery:** Domain Master (90%+ in a domain), Full Spectrum (all domains > 70%)
- **Streak:** 7-day, 30-day, 100-day streak
- **Social:** First Forum Post, Helpful Answer, Study Group Leader
- **Internship:** First Submission, Milestone Complete, Portfolio Builder
- **Completion:** Course Complete, Mock Exam Ace (90%+), Intern of the Month

### 8.4 Missions (Daily/Weekly)
**Daily examples:**
- Complete 1 lesson
- Answer 20 questions
- Review flashcards
- Log into the app

**Weekly examples:**
- Complete 1 module
- Take 1 mock exam
- Achieve 80%+ on a practice quiz
- Help someone in the forum

### 8.5 Anti-Burnout
- **Streak Protection Tokens:** Earn 1 per 7-day streak (max 3 stored). Use to preserve streak on a missed day.
- **Rest Day Mode:** 1 per week. Intentional rest without streak penalty. "Recovery is part of mastery."
- **Consistency Celebration:** "You've studied 5 of the last 7 days" > better than demanding 7/7

### 8.6 Leaderboard (Optional, Cohort-scoped)
- Opt-in per cohort
- Shows: rank, name, XP this period, streak
- Weekly reset option
- No shaming: only top 10 shown + "Your position: #X"

### Acceptance Criteria
- [ ] XP awarded correctly for all actions
- [ ] Level progression calculates correctly
- [ ] Badges award automatically when criteria met
- [ ] Daily/weekly missions rotate and track progress
- [ ] Streak protection tokens work correctly
- [ ] Rest day mode prevents streak loss

---

## 9. Cohort & Facilitator System

### Goal
Enable facilitators to manage cohorts, monitor progress, intervene on at-risk learners, grade work, and run live sessions.

### 9.1 Cohort Management
- Create cohort: name, course, dates, max learners, settings
- Add/remove learners
- Assign co-facilitator
- Set weekly plans (drag-and-drop builder)
- Post announcements

### 9.2 Cohort Health Dashboard
**Health Score (composite):**
- Engagement rate (% active in last 7 days)
- Average readiness score
- On-time completion rate
- Intervention count

**Mastery Heatmap:**
- Grid: learners (rows) × domains (columns)
- Cell color: red/yellow/green by mastery %
- Sortable by any column

**Engagement Heatmap:**
- Grid: learners × days of week
- Cell: active (green) / inactive (gray)
- Last 4 weeks

**Mock Score Distribution:**
- Histogram of latest mock scores
- Pass line marked
- Trend overlay (last 3 mocks)

**At-Risk Learners:**
- Auto-flagged based on rules:
  - No activity in 3+ days
  - Readiness < 40%
  - Declining trend (3 consecutive lower scores)
  - Behind schedule (< 50% of expected progress by date)
- Each shows: name, reason, last active, suggested intervention

### 9.3 Learner 360 Profile
Everything about one learner:
- Progress vs plan
- Mastery map
- Mock history + timing patterns
- Error patterns
- Simulation decisions and scores
- Assignment grades
- Intervention log
- Attendance record

### 9.4 Weekly Plan Builder
- Calendar/week view
- Drag items: lessons, quizzes, mocks, simulations, assignments
- Set mandatory/optional
- Set due dates
- Auto-suggest: "Based on cohort weaknesses, add Risk Management drill"

### 9.5 Communication & Nudges
- Cohort announcements (pinnable)
- Pre-built nudge templates:
  - "We miss you! Come back and continue your streak."
  - "Your mock exam is tomorrow. Review your weak areas tonight."
  - "Great progress this week! Keep it up."
- Custom coaching messages (text + voice)
- Escalation to org admin / line manager

### 9.6 Grading & Feedback
- Assignment inbox with submission queue
- Rubric scoring UI: slider/dropdown per criterion
- Feedback text box + voice recording option
- Return for revision with specific notes
- Grade history per learner

### 9.7 Live Sessions
- Schedule with calendar picker
- Add video platform link (Zoom/Teams/Meet manual entry)
- Auto-notify enrolled learners
- Attendance marking: present/absent/excused
- Post-session: add notes, upload replay, assign follow-up quiz

### 9.8 Intervention Playbooks
Pre-configured intervention workflows:

| Trigger | Playbook |
|---|---|
| No activity 3+ days | Send nudge → Day 5: facilitator call → Day 7: escalate |
| Readiness < 40% | Assign booster plan: 3 weak domain lessons + 1 practice quiz/day |
| Overthinking pattern | Assign timed drills (45 sec/question) |
| Burnout signals | Suggest rest day + reduced plan for 1 week |

### 9.9 Facilitator Internship Oversight
- View internship cohort health: timeliness, quality scores, sponsor satisfaction
- See sponsor feedback patterns
- Intervene on stuck interns
- Audit portfolio completeness
- Generate internship completion recommendations

### Acceptance Criteria
- [ ] Cohort health score calculates from real data
- [ ] Mastery heatmap renders with real learner data
- [ ] At-risk detection flags correctly based on rules
- [ ] Weekly plan builder saves and pushes items to learner plans
- [ ] Grading updates submission status and notifies learner
- [ ] Nudge templates send via push + email
- [ ] Live session attendance tracked

---

## 10. Internship & Project Lab

### Goal
Structured, paid internship with daily/weekly/monthly tasks, evidence-based submissions, sponsor reviews, and portfolio outcomes.

### 10.1 Internship Tracks
Pre-configured tracks:
- Business Analysis Internship (12 weeks)
- Project Management Internship (12 weeks)
- Finance Internship (8 weeks)
- Product Management Internship (10 weeks)

Each track includes:
- Orientation module (tools, templates, expectations)
- 4-8 milestones with deliverables
- Daily/weekly/monthly task templates
- Rubric criteria for sponsor scoring
- Required tools list

### 10.2 Internship Dashboard (Learner)
- Current milestone + progress bar
- Tasks due today / this week
- Submission status summary (pie chart)
- Next meeting date + join link
- Sponsor feedback score trend
- Portfolio item count

### 10.3 Task Board
**Columns (Kanban):**
Backlog → This Week → Today → In Progress → In Review → Needs Revision → Done

**Task Card shows:**
- Title + type badge (daily/weekly/monthly)
- Due date
- Priority indicator
- Assigned to (for team tasks)
- Status indicator

**Drag-and-drop** to move between columns.

**Task Detail View:**
- Description + instructions
- Deliverable requirements
- Attached templates
- Submission area
- Evidence upload area
- Sponsor feedback (if reviewed)
- Version history (resubmissions)

### 10.4 Submission System
- Upload files: PDF, DOCX, XLSX, PPTX (max 25MB each, max 5 files)
- Upload evidence: screenshots, photos (max 10MB each, max 10 items)
- Optional: screen recordings (max 100MB)
- Text notes/reflection field
- Time log (optional, minutes)
- Version tracking for resubmissions
- Submission confirmation with timestamp

### 10.5 Sponsor Review Portal
**Review Queue:**
- List of pending submissions sorted by due date
- Badge showing count of items awaiting review
- Filter by intern / task type / date

**Review UI:**
- View all uploaded files in-app (PDF viewer, image viewer)
- Download files
- Rubric scoring: criteria list, each scored 0-5 with optional comment
- Overall feedback text field
- Voice note recording (max 3 min)
- Decision buttons: Approve ✓ / Request Revision ↻
- Risk flags: checkboxes for Late, Quality Concern, Team Issue

**Scorecard Submission:**
- Weekly/monthly aggregate ratings
- Categories: Quality, Timeliness, Communication, Initiative, Technical Skill
- Each scored 1-5
- Overall comments
- Improvement actions (text list)
- "Ready for next milestone" toggle

### 10.6 Meetings Hub
- Schedule meeting: title, type, platform, date/time, duration, agenda
- Calendar view (week/month)
- Sync with Google Calendar / Outlook (via calendar URL export)
- Platform integration: paste Zoom/Teams/Meet link
- Attendance capture by organizer
- Post-meeting: notes, action items
- Action items auto-create tasks on task board

### 10.7 Internship Completion
Upon completion of all milestones:
- Internship completion certificate (QR-verifiable)
- Portfolio export to PDF
- Sponsor ratings summary
- Skills demonstrated mapping
- "Next steps" career recommendation

### Acceptance Criteria
- [ ] Task board drag-and-drop works on mobile
- [ ] File uploads work for all supported types within size limits
- [ ] Evidence uploads display correctly in sponsor view
- [ ] Rubric scoring calculates and saves correctly
- [ ] Resubmission preserves version history
- [ ] Meeting action items create tasks on board
- [ ] Completion certificate generates with QR code

---

## 11. Simulations

### Goal
Build workplace judgment through branching scenarios with consequences and replay.

### 11.1 Simulation Types
| Type | Description |
|---|---|
| Branching Scenario | Story → decisions → consequences → outcome |
| Role-Play | Conversations as manager/team member/client |
| Case Study Lab | Analyze documents/emails/charts → make decisions |
| Decision Room | Scope/cost/time/risk trade-offs with constraints |

### 11.2 Simulation Flow
```
Context Narrative
     ↓
Story Node (situation description)
     ↓
Decision Node (2-4 choices)
     ↓
Consequence Node (what happened as a result)
     ↓
[Repeat 3-8 decision points]
     ↓
End Node → Scorecard → Best Practice Path Reveal
```

### 11.3 Simulation UI
- Full-screen immersive mode
- Story text with optional media (images, documents, charts)
- Decision cards with clear options
- Consequence reveal with animation
- Running score indicators (subtle)
- Save/resume capability
- Replay button on completion

### 11.4 Scorecard Dimensions
- **Judgment:** Quality of decisions made
- **Ethics:** Ethical considerations
- **Stakeholder Handling:** Communication + relationship management
- **Risk Thinking:** How well risks were managed

### 11.5 Best Practice Path
After completion:
- Show your path vs optimal path (side by side)
- Highlight where you diverged
- Explain why the best practice choice was better
- Link to relevant lesson content

### Acceptance Criteria
- [ ] Branching logic follows correct paths
- [ ] All simulation types render correctly
- [ ] Scores calculate from choice impacts
- [ ] Best practice path displays after completion
- [ ] Save/resume works mid-simulation

---

## 12. Certificates & Portfolio

### 12.1 Digital Certificates
**Trigger:** Course completion (all modules + passing grade) or Internship completion

**Certificate includes:**
- JK Michaels branding + logo
- Title: "Certificate of Completion"
- Recipient name
- Course/internship title
- Date issued
- Certificate number (JKM-2026-XXXXX)
- QR code → links to verification page
- Optional: score achieved

**Verification page (public URL):**
- Certificate holder name
- Course/internship completed
- Date issued
- Valid/Revoked status
- JK Michaels branding

### 12.2 Portfolio Builder (Internship)
- Auto-populated from approved internship submissions
- Learner can:
  - Add title + description per item
  - Tag skills demonstrated
  - Mark items public/private
  - Reorder items
- Export portfolio as formatted PDF
- Public portfolio URL (if any items marked public)

### Acceptance Criteria
- [ ] Certificate PDF generates with correct data
- [ ] QR code links to working verification page
- [ ] Portfolio export creates clean PDF
- [ ] Public portfolio URL works correctly

---

## 13. Commerce & Payments

### 13.1 Storefront
- Course catalog with pricing
- Filter: category, type, level, price range, duration
- Search
- Course cards: thumbnail, title, price, rating, enrollment count
- Bundle display with savings

### 13.2 Checkout Flow
```
Course/Bundle Selection → Cart (optional) → Discount Code → Payment Gateway → Confirmation → Auto-Enrollment
```

### 13.3 Payment Gateways

**Paystack (Primary - Nigeria):**
- Redirect to Paystack checkout
- Webhook verification on success
- Card, bank transfer, USSD support

**Flutterwave (Secondary - Nigeria + Africa):**
- Redirect to Flutterwave checkout
- Webhook verification
- Card, bank transfer, mobile money

**Stripe (International):**
- Embedded checkout
- Webhook verification
- Card payments

### 13.4 Discount Codes
- Percentage or fixed amount
- Max uses limit
- Product-specific or global
- Date range validity
- Validation before payment

### 13.5 Order Management
- Order history with status
- Receipt/invoice generation
- Refund handling (admin-initiated)

### Acceptance Criteria
- [ ] Payment flow works end-to-end for all 3 gateways
- [ ] Webhook verification is secure (signature validation)
- [ ] Auto-enrollment triggers on payment success
- [ ] Discount codes validate correctly
- [ ] Order receipts are accessible
- [ ] Failed payments do not create enrollment

---

## 14. Notifications & Reminders

### 14.1 Notification Types

**Learning:**
- Daily study reminder (at user's preferred time)
- "Don't forget your quiz today!"
- "2 lessons left to complete this module"
- "Mock exam scheduled tomorrow"
- Streak rescue (if missed yesterday + has tokens)
- Weekly progress summary
- New content available in enrolled course

**Internship:**
- Task due today/tomorrow
- Submission deadline warning (24h, 1h)
- Meeting reminder (24h, 1h, 15min)
- Sponsor feedback received
- Revision requested

**Admin/System:**
- Enrollment confirmation + payment receipt
- Certificate issued
- Cohort announcements
- System maintenance notices

### 14.2 Channels
- **In-app:** notification bell icon + badge count + notification drawer
- **Push:** device push notifications (via PWA service worker + Capacitor)
- **Email:** transactional emails (welcome, receipt, reminders, weekly digest)

### 14.3 User Controls
- Per-channel toggle (push on/off, email on/off)
- Quiet hours (start/end time)
- Frequency: real-time, daily digest, weekly digest
- Category toggles (learning, internship, system)

### 14.4 Smart Delivery Rules
- Respect quiet hours (queue and deliver after)
- Don't send push + email for same event (prefer push, fallback to email)
- Batch notifications to avoid spam (max 3 push/day for non-urgent)
- Streak rescue: send only once, at 8 PM if no activity by then

### Acceptance Criteria
- [ ] Push notifications work on PWA + Capacitor builds
- [ ] Email templates render correctly
- [ ] Quiet hours respected
- [ ] Frequency settings work (digest bundles multiple notifications)
- [ ] User can toggle all categories independently

---

## 15. Meetings & Events

### 15.1 Meetings (Cohort + Internship)
- Facilitators/sponsors schedule meetings
- Learners see upcoming meetings in their dashboard
- Calendar view (week/month)
- RSVP: Accept / Decline / Tentative
- Meeting link displayed 5 min before start
- Attendance recorded by organizer
- Post-meeting notes + action items
- Optional replay upload

### 15.2 Events & Webinars
- Platform-wide event calendar
- Event types: webinar, bootcamp, industry session, workshop
- Registration with confirmation
- Reminder notifications (24h, 1h)
- Attendance tracking
- Replay available after event
- Free and paid events

### Acceptance Criteria
- [ ] Meeting RSVP works correctly
- [ ] Calendar displays all upcoming meetings + events
- [ ] Reminders send at configured intervals
- [ ] Attendance can be recorded post-event

---

## 16. Community

### 16.1 Discussion Forums
- Course-specific forums (auto-created per course)
- General forums
- Cohort forums (private to cohort)
- Thread: title, body, rich text, author, timestamp, reply count
- Replies: nested (1 level), with "accepted answer" option
- Moderator actions: pin, lock, delete
- Learner actions: post, reply, bookmark thread

### 16.2 Direct Messaging
- 1-to-1 messaging between any users
- Message: text + optional file attachment
- Read receipts
- Facilitator → learner coaching messages

### 16.3 Study Groups
- Learner-created groups (course-scoped)
- Join/leave
- Group forum
- Max 10 members per group

### Acceptance Criteria
- [ ] Forums load with pagination
- [ ] Nested replies display correctly
- [ ] Moderator actions work
- [ ] Direct messages deliver in real-time (WebSocket)
- [ ] Study groups enforce max member limit

---

## 17. Career Pathways

### 17.1 Pathway Display
- Visual roadmap: courses in sequence
- Milestone markers
- "Your progress" indicator
- Skills mapped to target role
- Recommended next course based on completion

### 17.2 Pathways
- Project Manager (PMP/PRINCE2 → Agile → MS Project → Leadership)
- Business Analyst (ECBA → CCBA → CBAP → Data Analysis)
- Finance Analyst (Finance Foundations → Cost Control → Advanced Finance)
- Product Manager (Fundamentals → Agile → Strategy → Analytics)

### Acceptance Criteria
- [ ] Pathways render with progress tracking
- [ ] Recommendations update as courses complete

---

## 18. Admin Console

### 18.1 Platform Dashboard
- Active users, DAU/WAU
- Enrollment trends
- Revenue metrics
- Completion rates
- At-risk user count
- Top performing courses

### 18.2 User Management
- User list with search/filter
- Add user manually / CSV import
- Assign roles
- Assign to departments
- Set exam dates/goals
- Deactivate/reactivate
- View user activity

### 18.3 Program Assignment
- Assign courses / learning paths to users or groups
- Set deadlines
- Set prerequisites
- Manage internship enrollments (paid entitlements)
- Bulk assignment by department

### 18.4 Reporting
- Executive summary (PDF export)
- Compliance report (who completed what by when)
- Cohort performance report
- Department report
- Internship performance report
- CSV export for all reports
- Scheduled report delivery (weekly email to admin)

### 18.5 Settings
- Platform branding (logo, colors)
- Notification policies
- Data retention rules
- Privacy settings
- Billing / seat management

### 18.6 Audit Logs
- All sensitive actions logged
- Filter by user, action type, entity, date range
- Exportable

### Acceptance Criteria
- [ ] Dashboard aggregates display accurately
- [ ] CSV import handles large files (1000+ rows)
- [ ] Reports generate as PDF and CSV
- [ ] Audit logs capture all CRUD operations on sensitive entities

---

## 19. SME Governance Console

### 19.1 Content Workflow
```
Draft → Review → Approved → Published → Version → Archive
```
- Author creates/edits content → status: Draft
- Author submits for review → status: Review
- SME reviews: approve or request changes
- Approved content can be published
- Published content is versioned (diff view available)
- Archived content is hidden from learners

### 19.2 Question Bank Management
- Full CRUD with rich filters
- Tagging: domain, subtopic, difficulty, type, Bloom's level
- Blueprint mapping (ensure coverage of exam domains)
- Exposure control: flag over-exposed questions
- Flagged item review queue (from learner reports)

### 19.3 Item Quality Analytics
- Difficulty distribution chart (should match exam blueprint)
- Discrimination index (top 27% vs bottom 27% comparison)
- Distractor effectiveness (options that no one picks are bad distractors)
- Time-to-answer statistics per question
- Explanation quality rating (from learner feedback)

### 19.4 Change Alerts
- When exam blueprint updates, system identifies affected questions/lessons
- Impact list generated
- Migration tools to bulk-update tags

### Acceptance Criteria
- [ ] Content workflow enforces state transitions
- [ ] Only SME role can approve content
- [ ] Question analytics calculate from real attempt data
- [ ] Blueprint coverage report shows gaps

---

## 20. Accessibility & Offline

### 20.1 WCAG 2.2 AA Compliance
- Semantic HTML throughout
- ARIA labels on interactive elements
- Keyboard navigation for all features
- Focus indicators visible
- Color not sole conveyor of information
- Text resizable to 200% without break
- Touch targets: minimum 44×44px

### 20.2 Accessibility Options
- Dyslexia-friendly font toggle (OpenDyslexic)
- High contrast theme
- Color-blind modes (protanopia, deuteranopia, tritanopia)
- Extended time for timed quizzes/exams (1.5x or 2x)
- Adjustable text spacing

### 20.3 Offline Strategy
**Phase 1: Download & Access**
- Mark courses/modules for download
- Download manager showing status + storage
- Priority: text content → questions → audio → video
- Downloaded content accessible without internet

**Phase 2: Offline Progress Sync**
- Quiz answers stored locally (IndexedDB via Service Worker)
- Lesson progress stored locally
- Background sync when connectivity returns
- Conflict resolution: latest timestamp wins
- Visual indicator: "Offline mode" banner

### Acceptance Criteria
- [ ] Lighthouse accessibility score > 90
- [ ] All interactive elements keyboard accessible
- [ ] Dyslexia font, high contrast, color-blind modes work
- [ ] Extended time applies to timed assessments
- [ ] Downloaded content accessible offline
- [ ] Progress syncs correctly when back online

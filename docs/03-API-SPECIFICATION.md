# JK Michaels Professional Mastery Platform — API Specification

## Document Control

| Field | Value |
|---|---|
| Version | 1.0 |
| Date | February 26, 2026 |
| Base URL | `https://api.jkmichaels.com/api/v1` |
| Auth | Bearer Token (Laravel Sanctum) |
| Format | JSON |

---

## Conventions

- **Versioning:** `/api/v1/...`
- **Auth header:** `Authorization: Bearer {token}`
- **Pagination:** `?page=1&per_page=20` → response includes `meta: {current_page, last_page, total}`
- **Filters:** `?filter[field]=value`
- **Sorting:** `?sort=-created_at` (prefix `-` for descending)
- **Includes:** `?include=modules,lessons` (eager load relations)
- **Search:** `?search=keyword`
- **Success response:** `{data: {...}, message: "..."}`
- **Error response:** `{message: "...", errors: {field: ["..."]}}`
- **HTTP status codes:** 200 OK, 201 Created, 204 No Content, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 422 Validation Error, 429 Rate Limited, 500 Server Error

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Onboarding](#2-onboarding)
3. [Users & Profiles](#3-users--profiles)
4. [Organizations](#4-organizations)
5. [Courses & Content](#5-courses--content)
6. [Assessments](#6-assessments)
7. [Spaced Repetition](#7-spaced-repetition)
8. [Analytics & Mastery](#8-analytics--mastery)
9. [Gamification](#9-gamification)
10. [Cohorts & Facilitator](#10-cohorts--facilitator)
11. [Internship & Project Lab](#11-internship--project-lab)
12. [Simulations](#12-simulations)
13. [Certificates & Portfolio](#13-certificates--portfolio)
14. [Commerce & Payments](#14-commerce--payments)
15. [Notifications](#15-notifications)
16. [Meetings & Events](#16-meetings--events)
17. [Community](#17-community)
18. [Governance & Content Workflow](#18-governance--content-workflow)
19. [Admin](#19-admin)
20. [Career Pathways](#20-career-pathways)

---

## 1. Authentication

### `POST /auth/register`
Register a new user.

**Body:**
```json
{
  "first_name": "string|required",
  "last_name": "string|required",
  "email": "email|required|unique",
  "password": "string|min:8|confirmed",
  "phone": "string|nullable"
}
```

**Response (201):**
```json
{
  "data": {
    "user": { "id", "uuid", "first_name", "last_name", "email" },
    "token": "plaintext_token"
  }
}
```

### `POST /auth/login`
**Body:** `{ "email", "password" }`
**Response (200):** `{ data: { user, token } }`

### `POST /auth/logout`
**Auth required.** Revokes current token.
**Response (204)**

### `POST /auth/forgot-password`
**Body:** `{ "email" }`
**Response (200):** `{ message: "Reset link sent" }`

### `POST /auth/reset-password`
**Body:** `{ "email", "token", "password", "password_confirmation" }`
**Response (200):** `{ message: "Password reset" }`

### `POST /auth/email/verify/{id}/{hash}`
Email verification.
**Response (200):** `{ message: "Email verified" }`

### `POST /auth/email/resend`
**Auth required.**
**Response (200):** `{ message: "Verification email sent" }`

---

## 2. Onboarding

### `POST /onboarding`
Save onboarding preferences and generate personalized plan.

**Auth required.**

**Body:**
```json
{
  "goal": "certification|skill|internship",
  "exam_date": "date|nullable",
  "daily_minutes": "integer|5-120",
  "learning_preference": "read|watch|listen",
  "experience_level": "beginner|intermediate|advanced",
  "accessibility_prefs": {
    "dyslexia_font": "boolean",
    "high_contrast": "boolean",
    "color_blind_mode": "string|nullable",
    "extended_time": "boolean"
  },
  "notification_prefs": {
    "push": "boolean",
    "email": "boolean",
    "quiet_start": "time|nullable",
    "quiet_end": "time|nullable"
  },
  "take_diagnostic": "boolean"
}
```

**Response (200):**
```json
{
  "data": {
    "daily_plan": [{ "type": "quiz|lesson|simulation", "title", "duration_minutes", "resource_id" }],
    "suggested_track": { "course_id", "title", "career_pathway" },
    "baseline_score": 0,
    "mastery_map": { "domain": "pct" },
    "quick_win": { "lesson_id", "quiz_id" }
  }
}
```

### `GET /onboarding/diagnostic`
Get diagnostic quiz (5 min quick assessment).
**Response (200):** Returns a quiz object.

### `POST /onboarding/diagnostic`
Submit diagnostic answers.
**Body:** `{ responses: [{ question_id, selected_answer_ids }] }`
**Response (200):** Returns baseline score + mastery map.

---

## 3. Users & Profiles

### `GET /me`
Get current user profile.
**Response:** Full user object with roles, permissions, gamification stats.

### `PUT /me`
Update profile fields.
**Body:** Any updatable user fields.

### `PUT /me/avatar`
Upload avatar.
**Body:** `multipart/form-data` with `avatar` file.

### `PUT /me/password`
Change password.
**Body:** `{ current_password, password, password_confirmation }`

### `PUT /me/preferences`
Update notification + accessibility preferences.

### `DELETE /me`
Request account deletion (GDPR).

### `GET /me/export`
Export all user data (GDPR).

---

## 4. Organizations

### `GET /organizations/{id}` — Org Admin
### `PUT /organizations/{id}` — Org Admin
### `GET /organizations/{id}/users` — Org Admin
### `POST /organizations/{id}/users` — Add user
### `POST /organizations/{id}/users/import` — CSV import
### `DELETE /organizations/{id}/users/{user_id}` — Remove user
### `GET /organizations/{id}/departments` — List departments
### `POST /organizations/{id}/departments` — Create department
### `PUT /organizations/{id}/departments/{dept_id}` — Update
### `GET /organizations/{id}/reports/overview` — Org dashboard data
### `GET /organizations/{id}/reports/compliance` — Compliance report
### `GET /organizations/{id}/reports/engagement` — Engagement metrics

---

## 5. Courses & Content

### `GET /courses`
List courses with filters.

**Query params:**
- `?filter[category_id]=1`
- `?filter[level]=beginner`
- `?filter[type]=self_paced`
- `?filter[is_free]=true`
- `?search=project+management`
- `?sort=-created_at`
- `?include=modules,category`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "uuid": "...",
      "title": "PMP Certification Prep",
      "slug": "pmp-certification-prep",
      "subtitle": "...",
      "thumbnail": "url",
      "level": "intermediate",
      "type": "certified",
      "duration_hours": 35.0,
      "price": 25000.00,
      "currency": "NGN",
      "avg_rating": 4.5,
      "total_enrollments": 350,
      "category": { "id", "name" },
      "module_count": 12,
      "lesson_count": 48
    }
  ],
  "meta": { "current_page", "last_page", "total" }
}
```

### `GET /courses/{slug}`
Course detail with modules and lesson list.

### `POST /courses/{id}/enroll`
Enroll current user (free courses or after payment).

### `GET /courses/{id}/modules`
List modules for a course.

### `GET /courses/{id}/modules/{module_id}/lessons`
List lessons in a module.

### `GET /lessons/{id}`
Full lesson content (text, video URL, audio URL, PDF URL, etc.).

### `POST /lessons/{id}/progress`
Update lesson progress.
**Body:** `{ progress_pct, video_position_sec, time_spent_seconds }`

### `POST /lessons/{id}/complete`
Mark lesson complete.

### `GET /courses/{id}/resources`
List downloadable resources for a course.

### `GET /resources/{id}/download`
Generate signed download URL.

### Bookmarks & Notes

### `GET /bookmarks`
### `POST /bookmarks`
**Body:** `{ bookmarkable_type, bookmarkable_id, note }`
### `DELETE /bookmarks/{id}`
### `GET /lessons/{id}/notes`
### `POST /lessons/{id}/notes`
**Body:** `{ content, highlight_text, position }`
### `PUT /notes/{id}`
### `DELETE /notes/{id}`

### Admin Course Management (platform_admin, sme_reviewer)

### `POST /admin/courses` — Create course
### `PUT /admin/courses/{id}` — Update course
### `DELETE /admin/courses/{id}` — Soft delete
### `POST /admin/courses/{id}/modules` — Add module
### `PUT /admin/modules/{id}` — Update module
### `POST /admin/modules/{id}/lessons` — Add lesson
### `PUT /admin/lessons/{id}` — Update lesson
### `POST /admin/lessons/{id}/media` — Upload video/audio/PDF
### `PUT /admin/courses/{id}/publish` — Publish course
### `GET /admin/categories` — List categories
### `POST /admin/categories` — Create category

---

## 6. Assessments

### Quizzes

### `GET /quizzes/{id}`
Get quiz with questions (randomized if configured).

**Response:**
```json
{
  "data": {
    "id": 1,
    "title": "Module 1 Knowledge Check",
    "question_count": 10,
    "time_limit_minutes": 15,
    "questions": [
      {
        "id": 1,
        "uuid": "...",
        "type": "multiple_choice",
        "stem": "Which of the following...",
        "stem_media": null,
        "options": [
          { "id": "a", "text": "Option A" },
          { "id": "b", "text": "Option B" }
        ]
      }
    ]
  }
}
```

### `POST /quizzes/{id}/start`
Start a quiz attempt.
**Response:** Returns `quiz_attempt_id`.

### `POST /quiz-attempts/{id}/answer`
Submit answer for a question.
**Body:**
```json
{
  "question_id": 1,
  "selected_answer_ids": ["b"],
  "confidence_level": "high",
  "time_spent_seconds": 45
}
```

### `POST /quiz-attempts/{id}/complete`
Complete the quiz attempt. Returns score + review.
**Response:**
```json
{
  "data": {
    "score_pct": 80.0,
    "correct_count": 8,
    "total_count": 10,
    "time_spent_seconds": 540,
    "domain_scores": { "Planning": { "correct": 3, "total": 4, "pct": 75.0 } },
    "review": [
      {
        "question_id": 1,
        "is_correct": false,
        "correct_answer_ids": ["c"],
        "your_answer_ids": ["b"],
        "explanation_correct": "...",
        "explanation_incorrect": { "b": "..." },
        "exam_tip": "...",
        "linked_lesson_id": 15
      }
    ]
  }
}
```

### `GET /quiz-attempts/{id}`
Get attempt details (review mode).

### Mock Exams

### `GET /mock-exams`
List available mock exams. `?filter[course_id]=1&filter[type]=full_length`

### `GET /mock-exams/{id}`
Mock exam details.

### `POST /mock-exams/{id}/start`
Start mock exam attempt.

### `POST /mock-attempts/{id}/answer`
Same as quiz answer.

### `POST /mock-attempts/{id}/pause`
Pause (if allowed).

### `POST /mock-attempts/{id}/resume`
Resume paused attempt.

### `POST /mock-attempts/{id}/complete`
Complete and score. Returns full breakdown including:
- domain scores
- time-per-question patterns
- confidence analysis
- weakness clusters
- top fix priorities
- personalized 7-day plan

### Practice by Domain

### `GET /practice`
Get adaptive practice questions.
**Query:** `?course_id=1&domain=Planning&difficulty=medium&type=scenario&count=10&timed=true`
**Response:** Question set.

### Question Bank Admin

### `GET /admin/questions` — List with filters
### `POST /admin/questions` — Create question
### `PUT /admin/questions/{id}` — Update
### `DELETE /admin/questions/{id}` — Soft delete
### `POST /admin/questions/{id}/flag` — Flag for review
### `GET /admin/questions/analytics` — Item quality stats

---

## 7. Spaced Repetition

### `GET /spaced-repetition/due`
Get items due for review today.
**Query:** `?course_id=1&limit=20`
**Response:** Array of flashcards/questions due for review.

### `POST /spaced-repetition/{id}/review`
Submit review result.
**Body:** `{ quality: 0-5 }` (SM-2 algorithm input: 0=forgot, 5=perfect)
**Response:** Updated interval and next review date.

### `GET /flashcards`
List flashcards. `?course_id=1&domain=Planning`

### `POST /flashcards`
Create personal flashcard.
**Body:** `{ course_id, front, back, tags }`

---

## 8. Analytics & Mastery

### `GET /analytics/mastery`
User's mastery map.
**Query:** `?course_id=1`
**Response:**
```json
{
  "data": {
    "overall_readiness_pct": 72.5,
    "confidence_band": "medium",
    "pass_likelihood": "likely",
    "domains": [
      {
        "domain": "Planning",
        "accuracy_pct": 85.0,
        "confidence_score": 70.0,
        "speed_score": 65.0,
        "mastery_level": "proficient",
        "improvement_rate": 5.2,
        "subtopics": [...]
      }
    ],
    "weak_areas": ["Risk Management", "Procurement"],
    "fastest_improvement": "Risk Management"
  }
}
```

### `GET /analytics/error-patterns`
**Query:** `?course_id=1`
**Response:** Array of detected error patterns with suggested fixes.

### `GET /analytics/readiness`
Readiness score trend.
**Query:** `?course_id=1&period=30d`

### `GET /analytics/activity`
Daily activity logs.
**Query:** `?period=7d|30d`

### `GET /analytics/dashboard`
Learner home dashboard data (aggregated).
**Response:**
```json
{
  "data": {
    "todays_plan": [...],
    "readiness_meter": { "score_pct", "confidence_band", "exam_countdown_days" },
    "streak": { "current", "longest", "protection_tokens" },
    "next_reward": { "xp_needed", "next_badge", "next_level" },
    "focus_areas": [...],
    "continue": { "type", "id", "title", "progress_pct" },
    "coach_insight": "Your biggest leak is..."
  }
}
```

### `GET /analytics/weekly-report`
Weekly summary report.

---

## 9. Gamification

### `GET /gamification/stats`
Current user's XP, level, streak, badges.

### `GET /gamification/badges`
All available badges with earned status.

### `GET /gamification/missions`
Active missions with progress.

### `POST /gamification/streak/protect`
Use a streak protection token.

### `POST /gamification/rest-day`
Activate rest day mode.

### `GET /gamification/leaderboard`
**Query:** `?scope=cohort|course|global&period=week|month|all`

---

## 10. Cohorts & Facilitator

### Learner-facing

### `GET /cohorts`
My cohorts.

### `GET /cohorts/{id}`
Cohort detail (schedule, members, plan).

### `GET /cohorts/{id}/plan`
Weekly plan for current cohort.

### `GET /cohorts/{id}/announcements`
Cohort announcements.

### Facilitator Console

### `GET /facilitator/cohorts`
All cohorts I facilitate.

### `GET /facilitator/cohorts/{id}/health`
Cohort health dashboard.
**Response:**
```json
{
  "data": {
    "health_score": 78,
    "total_learners": 25,
    "active_today": 18,
    "at_risk_count": 3,
    "mastery_heatmap": [{ "user_id", "user_name", "domains": { "Planning": 85, "Risk": 45 } }],
    "engagement_heatmap": [{ "user_id", "days": { "Mon": true, "Tue": false } }],
    "mock_score_distribution": [{ "range": "60-70", "count": 5 }],
    "at_risk_learners": [{ "user_id", "name", "reasons": ["low_engagement", "low_readiness"] }]
  }
}
```

### `GET /facilitator/cohorts/{id}/learners/{user_id}`
Learner 360 profile.
**Response:** Full learner data — progress, mastery map, mock history, error patterns, simulation scores, assignment grades, intervention history.

### `POST /facilitator/cohorts/{id}/plan`
Create/update weekly plan.
**Body:** `{ week_number, items: [{ itemable_type, itemable_id, is_mandatory, due_date }] }`

### `POST /facilitator/cohorts/{id}/assignments`
Create assignment.

### `GET /facilitator/assignments/{id}/submissions`
View submissions for grading.

### `PUT /facilitator/submissions/{id}/grade`
Grade a submission.
**Body:** `{ score, rubric_scores, feedback, feedback_audio_url, status: "graded|returned" }`

### `POST /facilitator/cohorts/{id}/announcements`
Post announcement.

### `POST /facilitator/interventions`
Log an intervention.
**Body:** `{ learner_id, cohort_id, type, reason, action_taken }`

### `POST /facilitator/nudge`
Send nudge to learner.
**Body:** `{ learner_id, message, channel: "push|email" }`

### Facilitator Internship Oversight

### `GET /facilitator/internships/health`
Internship cohort health: timeliness, quality, sponsor satisfaction.

### `GET /facilitator/internships/portfolios`
Audit portfolio completeness across interns.

---

## 11. Internship & Project Lab

### Learner-facing

### `GET /internships`
Available internship tracks.

### `GET /internships/{id}`
Track detail.

### `GET /internship/dashboard`
My internship dashboard.
**Response:**
```json
{
  "data": {
    "enrollment": { "track", "status", "progress_pct", "start_date", "end_date" },
    "milestones": [{ "id", "title", "week", "status" }],
    "tasks_due": { "daily": [...], "weekly": [...], "monthly": [...] },
    "submission_stats": { "submitted": 12, "approved": 10, "in_review": 1, "revision": 1 },
    "meetings": [{ "next_meeting", "join_url" }],
    "sponsor_feedback_summary": { "avg_score", "latest_comment" },
    "portfolio_items_count": 5
  }
}
```

### `GET /internship/tasks`
Task board.
**Query:** `?status=today|this_week|backlog&type=daily|weekly|monthly`

### `PUT /internship/tasks/{id}`
Update task status (move on board).
**Body:** `{ status: "in_progress|in_review" }`

### `POST /internship/tasks/{id}/submit`
Submit task work.
**Body (multipart):**
```
content: "text notes"
files[]: file uploads (PDF/DOCX/XLSX/PPTX)
evidence[]: screenshot/photo uploads
time_log_minutes: 120
```

### `GET /internship/submissions`
My submissions list.

### `GET /internship/submissions/{id}`
Submission detail with sponsor feedback.

### `POST /internship/submissions/{id}/resubmit`
Resubmit after revision request.

### Sponsor Portal

### `GET /sponsor/review-queue`
Pending submissions to review.

### `GET /sponsor/submissions/{id}`
View submission with files + evidence.

### `POST /sponsor/submissions/{id}/review`
Review a submission.
**Body:**
```json
{
  "decision": "approved|revision_requested",
  "rubric_scores": { "criterion_name": { "score": 4, "comment": "Good work" } },
  "overall_score": 4.0,
  "feedback": "Overall feedback text",
  "feedback_audio_url": "url|null",
  "risk_flags": ["late"]
}
```

### `GET /sponsor/interns/{enrollment_id}/progress`
Intern progress snapshot.

### `POST /sponsor/scorecards`
Submit weekly/monthly scorecard.
**Body:**
```json
{
  "enrollment_id": 1,
  "period_type": "weekly|monthly",
  "period_number": 3,
  "scores": { "quality": 4, "timeliness": 3, "communication": 5 },
  "overall_score": 4.0,
  "comments": "...",
  "improvement_actions": ["Improve documentation"],
  "ready_for_next": true
}
```

---

## 12. Simulations

### `GET /simulations`
Available simulations. `?course_id=1&type=branching_scenario`

### `GET /simulations/{id}`
Simulation detail + start node.

### `POST /simulations/{id}/start`
Start simulation attempt. Returns first node.

### `POST /simulation-attempts/{id}/choose`
Make a choice.
**Body:** `{ node_id, choice_id }`
**Response:** Next node (story/decision/consequence/feedback/end) + running scores.

### `POST /simulation-attempts/{id}/complete`
Complete simulation. Returns scorecard.
**Response:**
```json
{
  "data": {
    "scores": { "judgment": 85, "ethics": 90, "stakeholder_handling": 70, "risk_thinking": 75 },
    "total_score_pct": 80.0,
    "best_practice_path": [node_ids],
    "your_path": [node_ids],
    "time_spent_seconds": 600
  }
}
```

### `GET /simulation-attempts/{id}`
Review completed attempt.

---

## 13. Certificates & Portfolio

### `GET /certificates`
My certificates.

### `GET /certificates/{uuid}`
Certificate detail (public endpoint for QR verification).

### `GET /certificates/{uuid}/verify`
Public verification endpoint. Returns certificate validity + holder info.

### `GET /certificates/{uuid}/download`
Download PDF.

### `GET /portfolio`
My portfolio items.

### `POST /portfolio`
Add portfolio item.
**Body:** `{ enrollment_id, title, description, type, file, skills_demonstrated, is_public }`

### `PUT /portfolio/{id}`
Update portfolio item.

### `GET /portfolio/export`
Export portfolio as PDF.

### `GET /portfolio/{user_uuid}` (Public)
Public portfolio view (if items marked public).

---

## 14. Commerce & Payments

### `GET /storefront`
Course catalog with pricing.
**Query:** `?filter[type]=certified&filter[level]=intermediate&filter[category_id]=1&search=pmp`

### `GET /storefront/{slug}`
Product detail page.

### `GET /bundles`
Available bundles.

### `POST /cart/validate-discount`
Validate discount code.
**Body:** `{ code, product_id }`

### `POST /orders`
Create order.
**Body:**
```json
{
  "items": [{ "product_id": 1, "quantity": 1 }],
  "discount_code": "PROMO2026",
  "payment_gateway": "paystack|flutterwave|stripe"
}
```

**Response (201):**
```json
{
  "data": {
    "order": { "id", "uuid", "total", "currency", "status" },
    "payment": {
      "gateway": "paystack",
      "authorization_url": "https://checkout.paystack.com/...",
      "reference": "ref_123"
    }
  }
}
```

### `POST /payments/webhook/paystack`
Paystack webhook (verify payment). Automatic enrollment on success.

### `POST /payments/webhook/flutterwave`
Flutterwave webhook.

### `POST /payments/webhook/stripe`
Stripe webhook.

### `GET /orders`
My order history.

### `GET /orders/{uuid}`
Order detail.

---

## 15. Notifications

### `GET /notifications`
List notifications.
**Query:** `?filter[category]=learning|internship|admin&filter[read]=false`

### `PUT /notifications/{id}/read`
Mark as read.

### `PUT /notifications/read-all`
Mark all as read.

### `GET /notifications/preferences`
Get notification preferences.

### `PUT /notifications/preferences`
Update preferences.
**Body:** `{ push_enabled, email_enabled, quiet_hours_start, quiet_hours_end, frequency }`

---

## 16. Meetings & Events

### `GET /meetings`
My upcoming meetings.

### `GET /meetings/{id}`
Meeting detail.

### `POST /meetings/{id}/rsvp`
**Body:** `{ status: "accepted|declined|tentative" }`

### `POST /meetings` (Facilitator/Sponsor)
Schedule meeting.
**Body:** `{ title, meetable_type, meetable_id, type, platform, scheduled_at, duration_minutes, agenda }`

### `PUT /meetings/{id}` (Facilitator/Sponsor)
Update meeting.

### `POST /meetings/{id}/notes` (Facilitator)
Add meeting notes + action items.

### `POST /meetings/{id}/attendance` (Facilitator)
Record attendance. **Body:** `{ attendees: [{ user_id, attended }] }`

### Events

### `GET /events`
Upcoming events/webinars.
**Query:** `?filter[type]=webinar|bootcamp`

### `GET /events/{id}`
Event detail.

### `POST /events/{id}/register`
Register for event.

### `GET /events/{id}/replay`
Get replay URL + transcript.

---

## 17. Community

### `GET /forums`
List forums. `?filter[course_id]=1`

### `GET /forums/{id}/threads`
List threads.

### `POST /forums/{id}/threads`
Create thread. **Body:** `{ title, body }`

### `GET /threads/{id}`
Thread detail with replies.

### `POST /threads/{id}/replies`
Post reply. **Body:** `{ body, parent_id }`

### `PUT /threads/{id}/pin` (Moderator)
### `PUT /threads/{id}/lock` (Moderator)

### `GET /messages`
Direct message conversations.

### `GET /messages/{user_id}`
Conversation with a user.

### `POST /messages/{user_id}`
Send message. **Body:** `{ body, attachment }`

### `GET /study-groups`
List study groups. `?filter[course_id]=1`

### `POST /study-groups`
Create study group. **Body:** `{ name, course_id, max_members, is_public }`

### `POST /study-groups/{id}/join`
### `POST /study-groups/{id}/leave`

---

## 18. Governance & Content Workflow

### `GET /admin/content/pending`
Content pending review.

### `POST /admin/content/{type}/{id}/submit`
Submit for review (draft → review).

### `POST /admin/content/{type}/{id}/approve`
Approve content (review → published). SME only.

### `POST /admin/content/{type}/{id}/reject`
Reject with notes.

### `GET /admin/content/{type}/{id}/versions`
Version history.

### `POST /admin/content/{type}/{id}/revert/{version}`
Revert to a previous version.

### `GET /admin/questions/flags`
Flagged questions queue.

### `PUT /admin/questions/flags/{id}`
Resolve flag. **Body:** `{ status, resolution_notes }`

### `GET /admin/questions/analytics`
Item quality analytics.
**Response:** difficulty distribution, discrimination index, distractor effectiveness, time-to-answer stats.

---

## 19. Admin

### `GET /admin/dashboard`
Platform overview.
**Response:**
```json
{
  "data": {
    "total_users": 5000,
    "active_learners": 3200,
    "dau": 800,
    "wau": 2100,
    "courses_count": 45,
    "completion_rate": 68.5,
    "avg_readiness": 72.0,
    "at_risk_count": 120,
    "revenue_this_month": 1500000,
    "top_courses": [...],
    "enrollment_trend": [...],
    "struggling_topics": [...]
  }
}
```

### User Management

### `GET /admin/users`
List all users with filters.

### `POST /admin/users`
Create user (manual).

### `PUT /admin/users/{id}`
Update user.

### `PUT /admin/users/{id}/roles`
Assign roles. **Body:** `{ roles: ["facilitator", "sme_reviewer"] }`

### `PUT /admin/users/{id}/deactivate`
Deactivate user.

### Program Assignment

### `POST /admin/users/{id}/assign`
Assign learning path / course / internship.
**Body:** `{ type: "course|internship_track", id, deadline }`

### `POST /admin/bulk-assign`
Bulk assign by department/group.
**Body:** `{ user_ids: [...], course_id, deadline }`

### Reports

### `GET /admin/reports/executive`
Executive summary PDF generation.

### `GET /admin/reports/compliance`
Compliance report.

### `GET /admin/reports/cohort/{id}`
Cohort performance report.

### `GET /admin/reports/internship`
Internship performance report (portfolio + rubric summary).

### `GET /admin/reports/department/{id}`
Department/team report.

### `GET /admin/reports/export`
CSV export. **Query:** `?report=users|enrollments|scores|activity`

### Settings

### `GET /admin/settings`
Platform settings.

### `PUT /admin/settings`
Update settings (notification policy, data retention, privacy, etc.).

### `GET /admin/audit-logs`
Audit log viewer.
**Query:** `?filter[user_id]=1&filter[action]=update&filter[auditable_type]=Course`

---

## 20. Career Pathways

### `GET /pathways`
List career pathways.

### `GET /pathways/{slug}`
Pathway detail with courses and milestones.

### `GET /pathways/recommend`
Recommend pathways based on user profile + progress.

---

## Rate Limiting

| Endpoint Group | Limit |
|---|---|
| Auth (login/register) | 10 requests/minute |
| General API | 60 requests/minute |
| File uploads | 10 requests/minute |
| Webhooks | 100 requests/minute |
| Admin endpoints | 120 requests/minute |

---

## Webhook Outbound (Future)

For enterprise integrations, the platform can emit webhooks:

| Event | Payload |
|---|---|
| `user.enrolled` | User + Course data |
| `user.completed_course` | User + Course + Score |
| `user.earned_certificate` | User + Certificate |
| `internship.milestone_completed` | User + Milestone |
| `payment.completed` | Order + Payment data |

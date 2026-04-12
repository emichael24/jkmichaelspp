# JK Michaels Professional Mastery Platform — Database Schema

## Document Control

| Field | Value |
|---|---|
| Version | 1.0 |
| Date | February 26, 2026 |
| Database | MySQL 8.0 |
| ORM | Eloquent (Laravel) |
| Naming | snake_case, plural table names |

---

## Table of Contents

1. [Auth & Users](#1-auth--users)
2. [Organizations](#2-organizations)
3. [Courses & Content](#3-courses--content)
4. [Assessments & Question Bank](#4-assessments--question-bank)
5. [Spaced Repetition & Memory](#5-spaced-repetition--memory)
6. [Analytics & Mastery](#6-analytics--mastery)
7. [Gamification](#7-gamification)
8. [Cohorts & Facilitator](#8-cohorts--facilitator)
9. [Internship & Project Lab](#9-internship--project-lab)
10. [Simulations](#10-simulations)
11. [Certificates & Portfolio](#11-certificates--portfolio)
12. [Commerce & Payments](#12-commerce--payments)
13. [Notifications](#13-notifications)
14. [Meetings & Events](#14-meetings--events)
15. [Community](#15-community)
16. [Governance & Content Workflow](#16-governance--content-workflow)
17. [Audit & System](#17-audit--system)

---

## 1. Auth & Users

### `users`
Primary user table for all roles.

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| uuid | CHAR(36) | UNIQUE, NOT NULL | Public identifier |
| organization_id | BIGINT UNSIGNED | FK → organizations.id, NULLABLE | NULL = platform-level user |
| first_name | VARCHAR(100) | NOT NULL | |
| last_name | VARCHAR(100) | NOT NULL | |
| email | VARCHAR(255) | UNIQUE, NOT NULL | |
| email_verified_at | TIMESTAMP | NULLABLE | |
| phone | VARCHAR(20) | NULLABLE | |
| password | VARCHAR(255) | NOT NULL | Hashed |
| avatar | VARCHAR(500) | NULLABLE | S3 path |
| timezone | VARCHAR(50) | DEFAULT 'Africa/Lagos' | |
| locale | VARCHAR(10) | DEFAULT 'en' | |
| goal | ENUM('certification','skill','internship') | NULLABLE | Set during onboarding |
| exam_date | DATE | NULLABLE | Target exam date |
| daily_minutes | TINYINT UNSIGNED | DEFAULT 15 | Available study time/day |
| learning_preference | ENUM('read','watch','listen') | DEFAULT 'read' | |
| experience_level | ENUM('beginner','intermediate','advanced') | DEFAULT 'beginner' | |
| accessibility_prefs | JSON | NULLABLE | {dyslexia_font, high_contrast, color_blind_mode, extended_time} |
| notification_prefs | JSON | NULLABLE | {push, email, quiet_start, quiet_end} |
| onboarding_completed | BOOLEAN | DEFAULT FALSE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| last_login_at | TIMESTAMP | NULLABLE | |
| remember_token | VARCHAR(100) | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | NULLABLE | Soft delete |

**Indexes:** `email`, `organization_id`, `uuid`, `is_active`

### `roles` (Spatie)
Managed by Spatie Laravel Permission.

| Role Name | Description |
|---|---|
| platform_admin | Full system access |
| org_admin | Organization-level admin |
| facilitator | Trainer/coach |
| sme_reviewer | Content reviewer |
| sponsor | Internship reviewer |
| line_manager | Team progress viewer |
| learner | Default role |

### `permissions` (Spatie)
Managed by Spatie Laravel Permission. Key permissions:

| Permission | Roles |
|---|---|
| manage_users | platform_admin, org_admin |
| manage_courses | platform_admin, sme_reviewer |
| manage_cohorts | platform_admin, org_admin, facilitator |
| grade_assignments | facilitator, sme_reviewer |
| review_submissions | sponsor |
| view_org_reports | org_admin, line_manager |
| manage_billing | platform_admin, org_admin |
| manage_content_workflow | platform_admin, sme_reviewer |
| manage_question_bank | platform_admin, sme_reviewer |
| view_learner_360 | facilitator, org_admin |
| manage_internships | platform_admin, org_admin |

### `personal_access_tokens` (Sanctum)
Standard Laravel Sanctum tokens table.

### `password_reset_tokens`
Standard Laravel password reset.

---

## 2. Organizations

### `organizations`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| uuid | CHAR(36) | UNIQUE, NOT NULL | |
| name | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE, NOT NULL | |
| logo | VARCHAR(500) | NULLABLE | S3 path |
| industry | VARCHAR(100) | NULLABLE | |
| size | ENUM('1-50','51-200','201-1000','1000+') | NULLABLE | |
| contact_email | VARCHAR(255) | NOT NULL | |
| contact_phone | VARCHAR(20) | NULLABLE | |
| address | TEXT | NULLABLE | |
| country | VARCHAR(3) | DEFAULT 'NGA' | ISO code |
| settings | JSON | NULLABLE | {branding, white_label, notification_policy, data_retention_days} |
| subscription_plan | ENUM('free','pro','enterprise') | DEFAULT 'free' | |
| max_seats | INT UNSIGNED | DEFAULT 50 | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | NULLABLE | |

### `departments`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| organization_id | BIGINT UNSIGNED | FK → organizations.id | |
| name | VARCHAR(255) | NOT NULL | |
| parent_id | BIGINT UNSIGNED | FK → departments.id, NULLABLE | Hierarchical |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `department_user` (Pivot)

| Column | Type |
|---|---|
| department_id | BIGINT UNSIGNED FK |
| user_id | BIGINT UNSIGNED FK |

---

## 3. Courses & Content

### `categories`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| name | VARCHAR(255) | NOT NULL | e.g., "Agile & Project Management" |
| slug | VARCHAR(255) | UNIQUE | |
| description | TEXT | NULLABLE | |
| icon | VARCHAR(255) | NULLABLE | |
| parent_id | BIGINT UNSIGNED | FK → categories.id, NULLABLE | Nested categories |
| sort_order | INT | DEFAULT 0 | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `courses`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| category_id | BIGINT UNSIGNED | FK → categories.id | |
| title | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE | |
| subtitle | VARCHAR(500) | NULLABLE | |
| description | TEXT | NULLABLE | |
| thumbnail | VARCHAR(500) | NULLABLE | S3 path |
| preview_video | VARCHAR(500) | NULLABLE | |
| level | ENUM('beginner','intermediate','advanced') | NOT NULL | |
| type | ENUM('self_paced','live','certified','bootcamp') | DEFAULT 'self_paced' | |
| duration_hours | DECIMAL(5,1) | NULLABLE | Estimated total |
| price | DECIMAL(10,2) | DEFAULT 0 | 0 = free |
| currency | VARCHAR(3) | DEFAULT 'NGN' | |
| discount_price | DECIMAL(10,2) | NULLABLE | |
| exam_blueprint | JSON | NULLABLE | {domains: [{name, weight_pct}]} |
| prerequisites | JSON | NULLABLE | [course_ids] |
| learning_outcomes | JSON | NULLABLE | [strings] |
| tags | JSON | NULLABLE | |
| is_published | BOOLEAN | DEFAULT FALSE | |
| published_at | TIMESTAMP | NULLABLE | |
| version | INT UNSIGNED | DEFAULT 1 | |
| author_id | BIGINT UNSIGNED | FK → users.id | |
| reviewer_id | BIGINT UNSIGNED | FK → users.id, NULLABLE | |
| total_enrollments | INT UNSIGNED | DEFAULT 0 | Denormalized counter |
| avg_rating | DECIMAL(3,2) | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | NULLABLE | |

**Indexes:** `category_id`, `slug`, `is_published`, `level`, `type`

### `modules`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| course_id | BIGINT UNSIGNED | FK → courses.id, CASCADE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| sort_order | INT | DEFAULT 0 | |
| duration_minutes | INT UNSIGNED | NULLABLE | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `lessons`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| module_id | BIGINT UNSIGNED | FK → modules.id, CASCADE | |
| title | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | | |
| content_type | ENUM('text','video','audio','pdf','slides','mixed') | DEFAULT 'mixed' | Primary type |
| text_content | LONGTEXT | NULLABLE | Rich text / HTML |
| summary | TEXT | NULLABLE | Skimmable summary |
| key_terms | JSON | NULLABLE | [{term, definition}] |
| video_url | VARCHAR(500) | NULLABLE | S3 / streaming URL |
| video_duration_seconds | INT UNSIGNED | NULLABLE | |
| video_transcript | LONGTEXT | NULLABLE | |
| video_chapters | JSON | NULLABLE | [{time_sec, title}] |
| audio_url | VARCHAR(500) | NULLABLE | |
| audio_duration_seconds | INT UNSIGNED | NULLABLE | |
| pdf_url | VARCHAR(500) | NULLABLE | |
| slides_url | VARCHAR(500) | NULLABLE | |
| sort_order | INT | DEFAULT 0 | |
| duration_minutes | INT UNSIGNED | DEFAULT 10 | Estimated |
| is_free_preview | BOOLEAN | DEFAULT FALSE | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| is_downloadable | BOOLEAN | DEFAULT TRUE | Offline download flag |
| version | INT UNSIGNED | DEFAULT 1 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `lesson_progress`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| lesson_id | BIGINT UNSIGNED | FK → lessons.id | |
| status | ENUM('not_started','in_progress','completed') | DEFAULT 'not_started' | |
| progress_pct | TINYINT UNSIGNED | DEFAULT 0 | 0-100 |
| video_position_sec | INT UNSIGNED | DEFAULT 0 | Resume point |
| time_spent_seconds | INT UNSIGNED | DEFAULT 0 | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Unique index:** `(user_id, lesson_id)`

### `course_enrollments`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| enrolled_at | TIMESTAMP | NOT NULL | |
| enrolled_by | ENUM('self','admin','system') | DEFAULT 'self' | |
| deadline | DATE | NULLABLE | |
| progress_pct | TINYINT UNSIGNED | DEFAULT 0 | Denormalized |
| status | ENUM('active','completed','expired','suspended') | DEFAULT 'active' | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Unique index:** `(user_id, course_id)`

### `bookmarks`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| bookmarkable_type | VARCHAR(255) | | Polymorphic (Lesson, Question, Resource) |
| bookmarkable_id | BIGINT UNSIGNED | | |
| note | TEXT | NULLABLE | User's note |
| created_at | TIMESTAMP | | |

### `user_notes`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| lesson_id | BIGINT UNSIGNED | FK → lessons.id | |
| content | TEXT | NOT NULL | |
| highlight_text | TEXT | NULLABLE | Selected text |
| position | JSON | NULLABLE | {paragraph, offset} |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `resources`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| title | VARCHAR(255) | NOT NULL | |
| type | ENUM('template','checklist','cheat_sheet','formula_sheet','case_study','playbook') | NOT NULL | |
| description | TEXT | NULLABLE | |
| file_url | VARCHAR(500) | NOT NULL | S3 path |
| file_type | VARCHAR(10) | | pdf, docx, xlsx, etc. |
| file_size_bytes | INT UNSIGNED | | |
| download_count | INT UNSIGNED | DEFAULT 0 | |
| sort_order | INT | DEFAULT 0 | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 4. Assessments & Question Bank

### `questions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| domain | VARCHAR(255) | NOT NULL | Exam domain |
| subtopic | VARCHAR(255) | NULLABLE | |
| type | ENUM('multiple_choice','multiple_select','true_false','scenario','situational_judgment','formula','fill_blank','ordering','matching') | NOT NULL | |
| difficulty | ENUM('easy','medium','hard','expert') | NOT NULL | |
| bloom_level | ENUM('remember','understand','apply','analyze','evaluate','create') | NULLABLE | Bloom's taxonomy |
| stem | TEXT | NOT NULL | Question text |
| stem_media | JSON | NULLABLE | {type, url} for images/diagrams |
| options | JSON | NOT NULL | [{id, text, is_correct, media_url}] |
| correct_answer_ids | JSON | NOT NULL | [option_ids] |
| explanation_correct | TEXT | NOT NULL | Why correct answer is right |
| explanation_incorrect | JSON | NULLABLE | {option_id: explanation} per wrong answer |
| exam_tip | TEXT | NULLABLE | |
| linked_lesson_id | BIGINT UNSIGNED | FK → lessons.id, NULLABLE | Link back to lesson |
| time_estimate_seconds | INT UNSIGNED | DEFAULT 60 | Expected time |
| exposure_count | INT UNSIGNED | DEFAULT 0 | Times shown |
| correct_rate | DECIMAL(5,4) | DEFAULT 0 | % answered correctly |
| avg_time_seconds | DECIMAL(8,2) | DEFAULT 0 | Avg time to answer |
| discrimination_index | DECIMAL(5,4) | NULLABLE | Item quality metric |
| status | ENUM('draft','review','approved','published','archived') | DEFAULT 'draft' | |
| reviewed_by | BIGINT UNSIGNED | FK → users.id, NULLABLE | |
| reviewed_at | TIMESTAMP | NULLABLE | |
| version | INT UNSIGNED | DEFAULT 1 | |
| tags | JSON | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | NULLABLE | |

**Indexes:** `course_id`, `domain`, `difficulty`, `type`, `status`, `bloom_level`

### `quizzes`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| title | VARCHAR(255) | NOT NULL | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| module_id | BIGINT UNSIGNED | FK → modules.id, NULLABLE | |
| lesson_id | BIGINT UNSIGNED | FK → lessons.id, NULLABLE | Inline quiz |
| type | ENUM('lesson_check','module_quiz','domain_practice','adaptive','custom') | NOT NULL | |
| question_count | INT UNSIGNED | NOT NULL | |
| time_limit_minutes | INT UNSIGNED | NULLABLE | NULL = untimed |
| passing_score_pct | TINYINT UNSIGNED | DEFAULT 70 | |
| is_randomized | BOOLEAN | DEFAULT TRUE | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| max_attempts | INT UNSIGNED | NULLABLE | NULL = unlimited |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `quiz_questions` (Pivot)

| Column | Type |
|---|---|
| quiz_id | BIGINT UNSIGNED FK |
| question_id | BIGINT UNSIGNED FK |
| sort_order | INT |

### `mock_exams`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| type | ENUM('full_length','mini_mock','domain_focused') | NOT NULL | |
| question_count | INT UNSIGNED | NOT NULL | |
| time_limit_minutes | INT UNSIGNED | NOT NULL | |
| passing_score_pct | TINYINT UNSIGNED | DEFAULT 65 | |
| domain_distribution | JSON | NOT NULL | {domain: question_count} |
| difficulty_distribution | JSON | NULLABLE | {easy: %, medium: %, hard: %} |
| is_published | BOOLEAN | DEFAULT TRUE | |
| is_exam_simulation | BOOLEAN | DEFAULT FALSE | Full exam UI pressure |
| allow_pause | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `quiz_attempts`
Shared by quizzes and mock exams.

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| quizzable_type | VARCHAR(255) | | Polymorphic: Quiz or MockExam |
| quizzable_id | BIGINT UNSIGNED | | |
| status | ENUM('in_progress','paused','completed','abandoned') | DEFAULT 'in_progress' | |
| score_pct | DECIMAL(5,2) | NULLABLE | |
| correct_count | INT UNSIGNED | DEFAULT 0 | |
| total_count | INT UNSIGNED | NOT NULL | |
| time_spent_seconds | INT UNSIGNED | DEFAULT 0 | |
| domain_scores | JSON | NULLABLE | {domain: {correct, total, pct}} |
| started_at | TIMESTAMP | NOT NULL | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Indexes:** `user_id`, `(quizzable_type, quizzable_id)`

### `question_responses`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| quiz_attempt_id | BIGINT UNSIGNED | FK → quiz_attempts.id, CASCADE | |
| question_id | BIGINT UNSIGNED | FK → questions.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| selected_answer_ids | JSON | NOT NULL | [option_ids] |
| is_correct | BOOLEAN | NOT NULL | |
| time_spent_seconds | INT UNSIGNED | DEFAULT 0 | |
| confidence_level | ENUM('low','medium','high') | NULLABLE | Self-rated |
| is_flagged | BOOLEAN | DEFAULT FALSE | Flagged for review |
| created_at | TIMESTAMP | | |

**Indexes:** `user_id`, `question_id`, `quiz_attempt_id`

---

## 5. Spaced Repetition & Memory

### `flashcards`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| course_id | BIGINT UNSIGNED | FK | |
| lesson_id | BIGINT UNSIGNED | FK, NULLABLE | |
| question_id | BIGINT UNSIGNED | FK, NULLABLE | Auto-generated from wrong answers |
| front | TEXT | NOT NULL | |
| back | TEXT | NOT NULL | |
| domain | VARCHAR(255) | NULLABLE | |
| tags | JSON | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `spaced_repetition_items`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| reviewable_type | VARCHAR(255) | | Polymorphic: Flashcard or Question |
| reviewable_id | BIGINT UNSIGNED | | |
| ease_factor | DECIMAL(4,2) | DEFAULT 2.50 | SM-2 algorithm |
| interval_days | INT UNSIGNED | DEFAULT 1 | Days until next review |
| repetitions | INT UNSIGNED | DEFAULT 0 | |
| next_review_at | TIMESTAMP | NOT NULL | |
| last_reviewed_at | TIMESTAMP | NULLABLE | |
| status | ENUM('new','learning','review','graduated') | DEFAULT 'new' | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Indexes:** `user_id`, `next_review_at`, `(reviewable_type, reviewable_id)`

---

## 6. Analytics & Mastery

### `user_mastery`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| course_id | BIGINT UNSIGNED | FK | |
| domain | VARCHAR(255) | NOT NULL | |
| subtopic | VARCHAR(255) | NULLABLE | |
| accuracy_pct | DECIMAL(5,2) | DEFAULT 0 | |
| confidence_score | DECIMAL(5,2) | DEFAULT 0 | |
| speed_score | DECIMAL(5,2) | DEFAULT 0 | vs expected time |
| consistency_score | DECIMAL(5,2) | DEFAULT 0 | |
| improvement_rate | DECIMAL(5,2) | DEFAULT 0 | Trend direction |
| mastery_level | ENUM('novice','developing','proficient','expert') | DEFAULT 'novice' | |
| total_attempts | INT UNSIGNED | DEFAULT 0 | |
| last_attempt_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Unique index:** `(user_id, course_id, domain, subtopic)`

### `error_patterns`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| course_id | BIGINT UNSIGNED | FK | |
| pattern_type | ENUM('concept_confusion','careless_fast','overthinking_slow','confidence_mismatch','qualifier_misread','elimination_failure') | NOT NULL | |
| domain | VARCHAR(255) | NULLABLE | |
| occurrence_count | INT UNSIGNED | DEFAULT 1 | |
| example_question_ids | JSON | NULLABLE | Recent examples |
| suggested_fix | TEXT | NULLABLE | AI or rule-generated |
| detected_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `readiness_scores`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| course_id | BIGINT UNSIGNED | FK | |
| score_pct | DECIMAL(5,2) | NOT NULL | Overall readiness |
| confidence_band | ENUM('low','medium','high') | NOT NULL | |
| pass_likelihood | ENUM('unlikely','possible','likely','very_likely') | NULLABLE | |
| domain_breakdown | JSON | NOT NULL | {domain: pct} |
| weak_areas | JSON | NULLABLE | [domains] top 3 |
| calculated_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |

### `daily_activity_logs`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| date | DATE | NOT NULL | |
| lessons_completed | INT UNSIGNED | DEFAULT 0 | |
| quizzes_completed | INT UNSIGNED | DEFAULT 0 | |
| questions_answered | INT UNSIGNED | DEFAULT 0 | |
| questions_correct | INT UNSIGNED | DEFAULT 0 | |
| time_spent_minutes | INT UNSIGNED | DEFAULT 0 | |
| xp_earned | INT UNSIGNED | DEFAULT 0 | |
| simulations_completed | INT UNSIGNED | DEFAULT 0 | |
| flashcards_reviewed | INT UNSIGNED | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Unique index:** `(user_id, date)`

---

## 7. Gamification

### `user_gamification`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK, UNIQUE | |
| total_xp | INT UNSIGNED | DEFAULT 0 | |
| level | INT UNSIGNED | DEFAULT 1 | |
| current_streak_days | INT UNSIGNED | DEFAULT 0 | |
| longest_streak_days | INT UNSIGNED | DEFAULT 0 | |
| streak_protection_tokens | TINYINT UNSIGNED | DEFAULT 1 | |
| last_activity_date | DATE | NULLABLE | |
| rest_day_used_this_week | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `badges`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| name | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE | |
| description | TEXT | | |
| icon | VARCHAR(500) | | S3 path |
| category | ENUM('learning','mastery','streak','social','internship','completion') | NOT NULL | |
| criteria | JSON | NOT NULL | {type: 'streak', value: 7} |
| xp_reward | INT UNSIGNED | DEFAULT 0 | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `user_badges`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| badge_id | BIGINT UNSIGNED | FK | |
| earned_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |

**Unique index:** `(user_id, badge_id)`

### `missions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | | |
| type | ENUM('daily','weekly','cohort','special') | NOT NULL | |
| criteria | JSON | NOT NULL | {action, count, domain...} |
| xp_reward | INT UNSIGNED | DEFAULT 0 | |
| badge_reward_id | BIGINT UNSIGNED | FK → badges.id, NULLABLE | |
| starts_at | TIMESTAMP | NULLABLE | |
| ends_at | TIMESTAMP | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `user_missions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| mission_id | BIGINT UNSIGNED | FK | |
| progress | INT UNSIGNED | DEFAULT 0 | |
| target | INT UNSIGNED | NOT NULL | |
| status | ENUM('active','completed','expired') | DEFAULT 'active' | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `xp_levels`
Configuration table for XP → Level mapping.

| Column | Type | Constraints | Description |
|---|---|---|---|
| level | INT UNSIGNED | PK | |
| xp_required | INT UNSIGNED | NOT NULL | Cumulative XP |
| title | VARCHAR(100) | | e.g., "Apprentice", "Expert" |

---

## 8. Cohorts & Facilitator

### `cohorts`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| organization_id | BIGINT UNSIGNED | FK, NULLABLE | |
| name | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| facilitator_id | BIGINT UNSIGNED | FK → users.id | |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NOT NULL | |
| max_learners | INT UNSIGNED | DEFAULT 50 | |
| status | ENUM('draft','active','completed','archived') | DEFAULT 'draft' | |
| settings | JSON | NULLABLE | {allow_self_enroll, leaderboard_visible} |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `cohort_members`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| cohort_id | BIGINT UNSIGNED | FK → cohorts.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| role | ENUM('learner','co_facilitator') | DEFAULT 'learner' | |
| joined_at | TIMESTAMP | NOT NULL | |
| status | ENUM('active','completed','dropped','removed') | DEFAULT 'active' | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Unique index:** `(cohort_id, user_id)`

### `cohort_weekly_plans`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| cohort_id | BIGINT UNSIGNED | FK | |
| week_number | INT UNSIGNED | NOT NULL | |
| title | VARCHAR(255) | | |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NOT NULL | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `cohort_plan_items`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| weekly_plan_id | BIGINT UNSIGNED | FK → cohort_weekly_plans.id | |
| itemable_type | VARCHAR(255) | | Polymorphic: Lesson, Quiz, MockExam, Assignment |
| itemable_id | BIGINT UNSIGNED | | |
| sort_order | INT | DEFAULT 0 | |
| is_mandatory | BOOLEAN | DEFAULT TRUE | |
| due_date | DATE | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `assignments`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| cohort_id | BIGINT UNSIGNED | FK, NULLABLE | |
| course_id | BIGINT UNSIGNED | FK | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NOT NULL | |
| instructions | TEXT | NULLABLE | |
| rubric | JSON | NULLABLE | [{criterion, description, max_score}] |
| max_score | INT UNSIGNED | DEFAULT 100 | |
| due_date | TIMESTAMP | NULLABLE | |
| allow_late | BOOLEAN | DEFAULT FALSE | |
| max_resubmissions | INT UNSIGNED | DEFAULT 1 | |
| created_by | BIGINT UNSIGNED | FK → users.id | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `assignment_submissions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| assignment_id | BIGINT UNSIGNED | FK → assignments.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| submission_number | INT UNSIGNED | DEFAULT 1 | 1st, 2nd, etc. |
| content | TEXT | NULLABLE | Text response |
| file_urls | JSON | NULLABLE | [S3 paths] |
| status | ENUM('submitted','in_review','graded','returned','resubmitted') | DEFAULT 'submitted' | |
| score | INT UNSIGNED | NULLABLE | |
| rubric_scores | JSON | NULLABLE | {criterion: score} |
| feedback | TEXT | NULLABLE | |
| feedback_audio_url | VARCHAR(500) | NULLABLE | Voice feedback |
| graded_by | BIGINT UNSIGNED | FK → users.id, NULLABLE | |
| graded_at | TIMESTAMP | NULLABLE | |
| submitted_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `interventions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| facilitator_id | BIGINT UNSIGNED | FK → users.id | |
| learner_id | BIGINT UNSIGNED | FK → users.id | |
| cohort_id | BIGINT UNSIGNED | FK → cohorts.id, NULLABLE | |
| type | ENUM('nudge','coaching','escalation','plan_adjustment','rest_day') | NOT NULL | |
| reason | TEXT | NOT NULL | |
| action_taken | TEXT | NOT NULL | |
| status | ENUM('pending','sent','acknowledged','resolved') | DEFAULT 'pending' | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `announcements`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| author_id | BIGINT UNSIGNED | FK → users.id | |
| cohort_id | BIGINT UNSIGNED | FK, NULLABLE | NULL = org/platform wide |
| organization_id | BIGINT UNSIGNED | FK, NULLABLE | |
| title | VARCHAR(255) | NOT NULL | |
| body | TEXT | NOT NULL | |
| is_pinned | BOOLEAN | DEFAULT FALSE | |
| published_at | TIMESTAMP | NULLABLE | |
| expires_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 9. Internship & Project Lab

### `internship_tracks`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| title | VARCHAR(255) | NOT NULL | e.g., "Business Analysis Internship" |
| slug | VARCHAR(255) | UNIQUE | |
| description | TEXT | | |
| duration_weeks | INT UNSIGNED | NOT NULL | |
| price | DECIMAL(10,2) | NOT NULL | |
| currency | VARCHAR(3) | DEFAULT 'NGN' | |
| orientation_course_id | BIGINT UNSIGNED | FK → courses.id, NULLABLE | |
| tools_required | JSON | NULLABLE | [tool names] |
| templates | JSON | NULLABLE | [{name, file_url}] |
| expectations | TEXT | NULLABLE | |
| skills_covered | JSON | NULLABLE | [skill tags] |
| max_participants | INT UNSIGNED | DEFAULT 20 | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `internship_enrollments`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| track_id | BIGINT UNSIGNED | FK → internship_tracks.id | |
| sponsor_id | BIGINT UNSIGNED | FK → users.id, NULLABLE | Assigned sponsor |
| team_lead_id | BIGINT UNSIGNED | FK → users.id, NULLABLE | |
| project_title | VARCHAR(255) | NULLABLE | Specific project assigned |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NOT NULL | |
| status | ENUM('enrolled','active','completed','withdrawn','failed') | DEFAULT 'enrolled' | |
| overall_score | DECIMAL(5,2) | NULLABLE | Final sponsor score |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `internship_milestones`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| track_id | BIGINT UNSIGNED | FK → internship_tracks.id | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | | |
| week_number | INT UNSIGNED | NOT NULL | |
| deliverables | JSON | NULLABLE | [expected outputs] |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `internship_tasks`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| enrollment_id | BIGINT UNSIGNED | FK → internship_enrollments.id | |
| milestone_id | BIGINT UNSIGNED | FK → internship_milestones.id, NULLABLE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| type | ENUM('daily','weekly','monthly') | NOT NULL | |
| status | ENUM('backlog','this_week','today','in_progress','in_review','approved','needs_revision','done') | DEFAULT 'backlog' | |
| priority | ENUM('low','medium','high','urgent') | DEFAULT 'medium' | |
| due_date | DATE | NULLABLE | |
| assigned_to | BIGINT UNSIGNED | FK → users.id, NULLABLE | For team scenarios |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `internship_submissions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| task_id | BIGINT UNSIGNED | FK → internship_tasks.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| submission_number | INT UNSIGNED | DEFAULT 1 | |
| content | TEXT | NULLABLE | Text/notes |
| file_urls | JSON | NULLABLE | [S3 paths] |
| time_log_minutes | INT UNSIGNED | NULLABLE | |
| status | ENUM('submitted','in_review','approved','revision_requested') | DEFAULT 'submitted' | |
| submitted_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `internship_evidence`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| submission_id | BIGINT UNSIGNED | FK → internship_submissions.id | |
| type | ENUM('screenshot','photo','screen_recording','document','other') | NOT NULL | |
| file_url | VARCHAR(500) | NOT NULL | S3 path |
| file_type | VARCHAR(10) | | |
| file_size_bytes | INT UNSIGNED | | |
| caption | VARCHAR(500) | NULLABLE | |
| created_at | TIMESTAMP | | |

### `sponsor_reviews`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| submission_id | BIGINT UNSIGNED | FK → internship_submissions.id | |
| sponsor_id | BIGINT UNSIGNED | FK → users.id | |
| decision | ENUM('approved','revision_requested') | NOT NULL | |
| rubric_scores | JSON | NULLABLE | {criterion: {score: 0-5, comment}} |
| overall_score | DECIMAL(3,1) | NULLABLE | 0.0 - 5.0 |
| feedback | TEXT | NULLABLE | |
| feedback_audio_url | VARCHAR(500) | NULLABLE | Voice note |
| risk_flags | JSON | NULLABLE | ['late','quality','team_issue'] |
| reviewed_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `sponsor_scorecards`
Weekly/monthly aggregate ratings from sponsor.

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| enrollment_id | BIGINT UNSIGNED | FK → internship_enrollments.id | |
| sponsor_id | BIGINT UNSIGNED | FK → users.id | |
| period_type | ENUM('weekly','monthly') | NOT NULL | |
| period_number | INT UNSIGNED | NOT NULL | Week or month # |
| scores | JSON | NOT NULL | {category: score} |
| overall_score | DECIMAL(3,1) | NOT NULL | |
| comments | TEXT | NULLABLE | |
| improvement_actions | JSON | NULLABLE | [action strings] |
| ready_for_next | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 10. Simulations

### `simulations`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| course_id | BIGINT UNSIGNED | FK → courses.id | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | | |
| type | ENUM('branching_scenario','role_play','case_study_lab','decision_room') | NOT NULL | |
| difficulty | ENUM('easy','medium','hard') | DEFAULT 'medium' | |
| estimated_minutes | INT UNSIGNED | DEFAULT 15 | |
| context_narrative | TEXT | NOT NULL | Story setup |
| is_published | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `simulation_nodes`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| simulation_id | BIGINT UNSIGNED | FK → simulations.id, CASCADE | |
| type | ENUM('story','decision','consequence','feedback','end') | NOT NULL | |
| content | TEXT | NOT NULL | Narrative / decision text |
| media_url | VARCHAR(500) | NULLABLE | Image, document, chart |
| is_start | BOOLEAN | DEFAULT FALSE | Entry node |
| is_best_practice | BOOLEAN | DEFAULT FALSE | Optimal path marker |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `simulation_choices`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| node_id | BIGINT UNSIGNED | FK → simulation_nodes.id, CASCADE | From node |
| next_node_id | BIGINT UNSIGNED | FK → simulation_nodes.id | To node |
| label | VARCHAR(500) | NOT NULL | Choice text |
| score_impact | JSON | NULLABLE | {judgment: +2, ethics: -1, risk: +1} |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | | |

### `simulation_attempts`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| simulation_id | BIGINT UNSIGNED | FK → simulations.id | |
| path_taken | JSON | NOT NULL | [node_ids in order] |
| choices_made | JSON | NOT NULL | [{node_id, choice_id}] |
| scores | JSON | NOT NULL | {judgment, ethics, stakeholder, risk} |
| total_score_pct | DECIMAL(5,2) | | |
| time_spent_seconds | INT UNSIGNED | | |
| status | ENUM('in_progress','completed') | DEFAULT 'in_progress' | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 11. Certificates & Portfolio

### `certificates`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | For QR verification |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| certifiable_type | VARCHAR(255) | | Polymorphic: Course, InternshipTrack |
| certifiable_id | BIGINT UNSIGNED | | |
| title | VARCHAR(255) | NOT NULL | |
| certificate_number | VARCHAR(50) | UNIQUE | JKM-2026-XXXXX |
| issued_at | TIMESTAMP | NOT NULL | |
| expires_at | TIMESTAMP | NULLABLE | |
| pdf_url | VARCHAR(500) | NOT NULL | S3 path |
| qr_code_url | VARCHAR(500) | NOT NULL | |
| metadata | JSON | NULLABLE | {score, duration, skills} |
| is_revoked | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `portfolio_items`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| enrollment_id | BIGINT UNSIGNED | FK → internship_enrollments.id | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| type | ENUM('deliverable','presentation','report','case_study','reflection') | NOT NULL | |
| file_url | VARCHAR(500) | NOT NULL | |
| skills_demonstrated | JSON | NULLABLE | [skill tags] |
| sponsor_score | DECIMAL(3,1) | NULLABLE | |
| is_public | BOOLEAN | DEFAULT FALSE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 12. Commerce & Payments

### `products`
Unified product table for courses, bundles, internships.

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| productable_type | VARCHAR(255) | | Polymorphic: Course, Bundle, InternshipTrack |
| productable_id | BIGINT UNSIGNED | | |
| name | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE | |
| price | DECIMAL(10,2) | NOT NULL | |
| currency | VARCHAR(3) | DEFAULT 'NGN' | |
| discount_price | DECIMAL(10,2) | NULLABLE | |
| discount_expires_at | TIMESTAMP | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `bundles`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| name | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE | |
| description | TEXT | | |
| thumbnail | VARCHAR(500) | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `bundle_items`

| Column | Type |
|---|---|
| bundle_id | BIGINT UNSIGNED FK |
| course_id | BIGINT UNSIGNED FK |

### `discount_codes`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| code | VARCHAR(50) | UNIQUE, NOT NULL | |
| type | ENUM('percentage','fixed') | NOT NULL | |
| value | DECIMAL(10,2) | NOT NULL | % or flat amount |
| max_uses | INT UNSIGNED | NULLABLE | |
| used_count | INT UNSIGNED | DEFAULT 0 | |
| product_id | BIGINT UNSIGNED | FK, NULLABLE | NULL = applies to all |
| valid_from | TIMESTAMP | NULLABLE | |
| valid_until | TIMESTAMP | NULLABLE | |
| is_active | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `orders`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| organization_id | BIGINT UNSIGNED | FK, NULLABLE | Org purchase |
| subtotal | DECIMAL(10,2) | NOT NULL | |
| discount_amount | DECIMAL(10,2) | DEFAULT 0 | |
| total | DECIMAL(10,2) | NOT NULL | |
| currency | VARCHAR(3) | DEFAULT 'NGN' | |
| discount_code_id | BIGINT UNSIGNED | FK, NULLABLE | |
| status | ENUM('pending','paid','failed','refunded','cancelled') | DEFAULT 'pending' | |
| paid_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `order_items`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| order_id | BIGINT UNSIGNED | FK → orders.id | |
| product_id | BIGINT UNSIGNED | FK → products.id | |
| quantity | INT UNSIGNED | DEFAULT 1 | |
| unit_price | DECIMAL(10,2) | NOT NULL | |
| total_price | DECIMAL(10,2) | NOT NULL | |
| created_at | TIMESTAMP | | |

### `payments`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| order_id | BIGINT UNSIGNED | FK → orders.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| gateway | ENUM('paystack','flutterwave','stripe','manual') | NOT NULL | |
| gateway_reference | VARCHAR(255) | NULLABLE | Provider transaction ID |
| gateway_response | JSON | NULLABLE | Full webhook payload |
| amount | DECIMAL(10,2) | NOT NULL | |
| currency | VARCHAR(3) | DEFAULT 'NGN' | |
| status | ENUM('pending','success','failed','refunded') | DEFAULT 'pending' | |
| paid_at | TIMESTAMP | NULLABLE | |
| refunded_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Indexes:** `gateway_reference`, `order_id`, `user_id`, `status`

---

## 13. Notifications

### `notifications`
Uses Laravel's built-in notification system extended.

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | CHAR(36) | PK | UUID |
| type | VARCHAR(255) | NOT NULL | Notification class |
| notifiable_type | VARCHAR(255) | NOT NULL | |
| notifiable_id | BIGINT UNSIGNED | NOT NULL | |
| data | JSON | NOT NULL | Notification payload |
| channel | ENUM('database','push','email') | DEFAULT 'database' | |
| category | ENUM('learning','internship','admin','system') | DEFAULT 'learning' | |
| read_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `notification_preferences`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK, UNIQUE | |
| push_enabled | BOOLEAN | DEFAULT TRUE | |
| email_enabled | BOOLEAN | DEFAULT TRUE | |
| quiet_hours_start | TIME | NULLABLE | e.g., 22:00 |
| quiet_hours_end | TIME | NULLABLE | e.g., 07:00 |
| learning_reminders | BOOLEAN | DEFAULT TRUE | |
| internship_reminders | BOOLEAN | DEFAULT TRUE | |
| announcements | BOOLEAN | DEFAULT TRUE | |
| frequency | ENUM('realtime','daily_digest','weekly_digest') | DEFAULT 'realtime' | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 14. Meetings & Events

### `meetings`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| organizer_id | BIGINT UNSIGNED | FK → users.id | |
| meetable_type | VARCHAR(255) | NULLABLE | Polymorphic: Cohort, InternshipEnrollment |
| meetable_id | BIGINT UNSIGNED | NULLABLE | |
| type | ENUM('live_session','coaching','standup','review','webinar','bootcamp') | NOT NULL | |
| platform | ENUM('zoom','teams','google_meet','other') | DEFAULT 'zoom' | |
| meeting_url | VARCHAR(500) | NULLABLE | |
| meeting_id_external | VARCHAR(255) | NULLABLE | Zoom/Teams meeting ID |
| scheduled_at | TIMESTAMP | NOT NULL | |
| duration_minutes | INT UNSIGNED | DEFAULT 60 | |
| agenda | TEXT | NULLABLE | |
| notes | TEXT | NULLABLE | Post-meeting |
| action_items | JSON | NULLABLE | [{task, assignee_id, due_date}] |
| replay_url | VARCHAR(500) | NULLABLE | |
| transcript | LONGTEXT | NULLABLE | |
| status | ENUM('scheduled','in_progress','completed','cancelled') | DEFAULT 'scheduled' | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `meeting_attendees`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| meeting_id | BIGINT UNSIGNED | FK → meetings.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| rsvp | ENUM('pending','accepted','declined','tentative') | DEFAULT 'pending' | |
| attended | BOOLEAN | DEFAULT FALSE | |
| joined_at | TIMESTAMP | NULLABLE | |
| left_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `events`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | | |
| type | ENUM('webinar','bootcamp','industry_session','workshop') | NOT NULL | |
| thumbnail | VARCHAR(500) | NULLABLE | |
| speaker_name | VARCHAR(255) | NULLABLE | |
| speaker_bio | TEXT | NULLABLE | |
| platform | ENUM('zoom','teams','google_meet','other') | DEFAULT 'zoom' | |
| meeting_url | VARCHAR(500) | NULLABLE | |
| scheduled_at | TIMESTAMP | NOT NULL | |
| duration_minutes | INT UNSIGNED | DEFAULT 60 | |
| max_registrations | INT UNSIGNED | NULLABLE | |
| price | DECIMAL(10,2) | DEFAULT 0 | 0 = free |
| replay_url | VARCHAR(500) | NULLABLE | |
| is_published | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `event_registrations`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| event_id | BIGINT UNSIGNED | FK → events.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| attended | BOOLEAN | DEFAULT FALSE | |
| registered_at | TIMESTAMP | NOT NULL | |
| created_at | TIMESTAMP | | |

---

## 15. Community

### `forums`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| course_id | BIGINT UNSIGNED | FK, NULLABLE | Course-specific |
| cohort_id | BIGINT UNSIGNED | FK, NULLABLE | Cohort-specific |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| is_general | BOOLEAN | DEFAULT FALSE | |
| sort_order | INT | DEFAULT 0 | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `forum_threads`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| forum_id | BIGINT UNSIGNED | FK → forums.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | Author |
| title | VARCHAR(255) | NOT NULL | |
| body | TEXT | NOT NULL | |
| is_pinned | BOOLEAN | DEFAULT FALSE | |
| is_locked | BOOLEAN | DEFAULT FALSE | |
| reply_count | INT UNSIGNED | DEFAULT 0 | Denormalized |
| last_reply_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | NULLABLE | |

### `forum_replies`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| thread_id | BIGINT UNSIGNED | FK → forum_threads.id | |
| user_id | BIGINT UNSIGNED | FK → users.id | |
| body | TEXT | NOT NULL | |
| parent_id | BIGINT UNSIGNED | FK → forum_replies.id, NULLABLE | Nested replies |
| is_accepted_answer | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | NULLABLE | |

### `direct_messages`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| sender_id | BIGINT UNSIGNED | FK → users.id | |
| recipient_id | BIGINT UNSIGNED | FK → users.id | |
| body | TEXT | NOT NULL | |
| attachment_url | VARCHAR(500) | NULLABLE | |
| read_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |

### `study_groups`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| name | VARCHAR(255) | NOT NULL | |
| course_id | BIGINT UNSIGNED | FK, NULLABLE | |
| created_by | BIGINT UNSIGNED | FK → users.id | |
| max_members | INT UNSIGNED | DEFAULT 10 | |
| is_public | BOOLEAN | DEFAULT TRUE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `study_group_members`

| Column | Type |
|---|---|
| study_group_id | BIGINT UNSIGNED FK |
| user_id | BIGINT UNSIGNED FK |
| joined_at | TIMESTAMP |

---

## 16. Governance & Content Workflow

### `content_versions`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| versionable_type | VARCHAR(255) | | Polymorphic: Lesson, Question |
| versionable_id | BIGINT UNSIGNED | | |
| version_number | INT UNSIGNED | NOT NULL | |
| data | JSON | NOT NULL | Full snapshot of content |
| change_summary | TEXT | NULLABLE | What changed |
| created_by | BIGINT UNSIGNED | FK → users.id | |
| created_at | TIMESTAMP | | |

### `content_approvals`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| approvable_type | VARCHAR(255) | | Polymorphic: Lesson, Question, Course |
| approvable_id | BIGINT UNSIGNED | | |
| status | ENUM('pending','approved','rejected','changes_requested') | DEFAULT 'pending' | |
| submitted_by | BIGINT UNSIGNED | FK → users.id | |
| reviewed_by | BIGINT UNSIGNED | FK, NULLABLE | |
| review_notes | TEXT | NULLABLE | |
| submitted_at | TIMESTAMP | NOT NULL | |
| reviewed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `question_flags`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| question_id | BIGINT UNSIGNED | FK → questions.id | |
| flagged_by | BIGINT UNSIGNED | FK → users.id | |
| reason | ENUM('incorrect_answer','unclear_stem','outdated','typo','duplicate','other') | NOT NULL | |
| description | TEXT | NULLABLE | |
| status | ENUM('open','in_review','resolved','dismissed') | DEFAULT 'open' | |
| resolved_by | BIGINT UNSIGNED | FK, NULLABLE | |
| resolution_notes | TEXT | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 17. Audit & System

### `audit_logs`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK, NULLABLE | NULL = system action |
| action | VARCHAR(50) | NOT NULL | create, update, delete, login, etc. |
| auditable_type | VARCHAR(255) | | Polymorphic |
| auditable_id | BIGINT UNSIGNED | | |
| old_values | JSON | NULLABLE | Before state |
| new_values | JSON | NULLABLE | After state |
| ip_address | VARCHAR(45) | NULLABLE | |
| user_agent | VARCHAR(500) | NULLABLE | |
| created_at | TIMESTAMP | | |

**Index:** `(auditable_type, auditable_id)`, `user_id`, `created_at`

### `career_pathways`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| title | VARCHAR(255) | NOT NULL | e.g., "Project Manager" |
| slug | VARCHAR(255) | UNIQUE | |
| description | TEXT | | |
| skills_required | JSON | NOT NULL | [skill tags] |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### `career_pathway_courses` (Pivot)

| Column | Type | Constraints | Description |
|---|---|---|---|
| pathway_id | BIGINT UNSIGNED | FK | |
| course_id | BIGINT UNSIGNED | FK | |
| sort_order | INT | DEFAULT 0 | |
| is_required | BOOLEAN | DEFAULT TRUE | |

### `course_ratings`

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| user_id | BIGINT UNSIGNED | FK | |
| course_id | BIGINT UNSIGNED | FK | |
| rating | TINYINT UNSIGNED | NOT NULL | 1–5 |
| review | TEXT | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Unique index:** `(user_id, course_id)`

### `media`
General media storage tracking.

| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK | |
| uuid | CHAR(36) | UNIQUE | |
| disk | VARCHAR(50) | DEFAULT 's3' | |
| path | VARCHAR(500) | NOT NULL | |
| filename | VARCHAR(255) | NOT NULL | |
| mime_type | VARCHAR(100) | | |
| size_bytes | INT UNSIGNED | | |
| uploaded_by | BIGINT UNSIGNED | FK → users.id | |
| created_at | TIMESTAMP | | |

---

## Entity Relationship Summary

```
Organizations ──< Users ──< CourseEnrollments >── Courses
                    │                                 │
                    │──< LessonProgress >── Lessons ──┘ (via Modules)
                    │
                    │──< QuizAttempts >── Quizzes ──< Questions
                    │                      │
                    │──< QuestionResponses ─┘
                    │
                    │──< UserMastery
                    │──< ErrorPatterns
                    │──< ReadinessScores
                    │──< DailyActivityLogs
                    │
                    │──< UserGamification
                    │──< UserBadges >── Badges
                    │──< UserMissions >── Missions
                    │
                    │──< InternshipEnrollments >── InternshipTracks
                    │    │──< InternshipTasks ──< InternshipSubmissions
                    │    │                        │──< InternshipEvidence
                    │    │                        │──< SponsorReviews
                    │    │──< SponsorScorecards
                    │
                    │──< SimulationAttempts >── Simulations ──< SimulationNodes
                    │                                           │──< SimulationChoices
                    │
                    │──< Certificates
                    │──< PortfolioItems
                    │──< Orders ──< Payments
                    │──< Notifications
                    │──< Bookmarks / Notes
                    │──< DirectMessages
                    │
                    │──< CohortMembers >── Cohorts ──< CohortWeeklyPlans
                    │                        │          │──< CohortPlanItems
                    │                        │──< Assignments ──< AssignmentSubmissions
                    │                        │──< Meetings ──< MeetingAttendees
                    │
                    │──< SpacedRepetitionItems >── Flashcards
                    │
                    │──< AuditLogs

Courses ──< Resources
        ──< Categories
        ──< Bundles (via BundleItems)

CareerPathways ──< CareerPathwayCourses >── Courses
```

---

## Total Table Count: ~60 tables

This schema supports the complete feature set while maintaining referential integrity and query performance through strategic indexing.

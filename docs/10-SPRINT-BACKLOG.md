# MasterStudy Gap Sprint Backlog

**Prepared:** April 13, 2026
**Source inputs:** `docs/08-MARKET-READINESS-ROADMAP.md`, `docs/09-MASTERSTUDY-FEATURE-PARITY-MATRIX.md`, `MasterStudy LMS Features vs. Your Current Project.docx`

## 1. Purpose

This document converts the parity matrix into an execution backlog with ticket sequencing.

It is not a product wish list. It is the recommended delivery order for closing the highest-value benchmark gaps with the least implementation churn.

## 2. Current Slice Already Landed

These items are now implemented enough to move out of the immediate sprint queue:

- learner course ratings are mounted and operational
- certificate download now returns a branded certificate file
- SCORM learner playback is wired into lesson delivery and progress sync
- lesson drip scheduling is implemented in admin and enforced in learner access
- facilitator gradebook v1 now exists
- subscription trial handling is stronger in the model layer

## 3. Sequencing Rules

- Finish partial systems before adding adjacent new ones.
- Land revenue-impacting work before cosmetic parity work.
- Prefer features that reduce manual facilitator/admin operations over novelty features.
- Keep post-launch parity items out of the launch-critical sprints unless a business dependency appears.

## 4. Sprint Map

| Sprint | Theme | Goal |
|---|---|---|
| Sprint 1 | Commerce and Access Hardening | Close the revenue and entitlement gaps that block self-serve launch |
| Sprint 2 | Assessment Integrity | Make quizzes and grading coherent enough for launch trust |
| Sprint 3 | Instructor Operations | Reduce facilitator/admin manual work and close high-friction cohort gaps |
| Sprint 4 | Delivery Parity | Improve course delivery quality and control surfaces |
| Sprint 5 | Expansion and Post-Launch Parity | Defer low-value or integration-heavy parity work |

## 5. Sprint 1: Commerce and Access Hardening

### Goal

Complete the missing monetization and entitlement work that affects paid launch readiness.

| Ticket | Priority | Size | Depends On | Scope | Acceptance |
|---|---|---:|---|---|---|
| `LMS-101` | Critical | L | none | Build learner-facing subscription checkout and plan selection flow using existing `Plan` and `Subscription` models | A learner can view plans, subscribe, and see current plan/trial state |
| `LMS-102` | Critical | M | `LMS-101` | Add membership entitlement checks to gated surfaces | Paid features can be restricted by plan feature flags without manual overrides |
| `LMS-103` | Critical | M | `LMS-101` | Add tax configuration and checkout calculation for self-serve purchases | Checkout, invoice, and receipt all show deterministic tax totals |
| `LMS-104` | High | M | `LMS-101` | Add course/public trial access rules on top of the subscription trial foundation | Trial users can access the intended courses for a bounded period and lose access correctly at expiry |
| `LMS-105` | High | M | `LMS-101` | Harden payment reconciliation and failed-payment recovery around subscriptions and orders | Payment retries, webhook replay, and final order/subscription states stay consistent |
| `LMS-106` | High | S | `LMS-101` | Add subscription/trial visibility to learner billing screens | Users and support can see active plan, trial end date, renewal date, and status |

## 6. Sprint 2: Assessment Integrity

### Goal

Remove the biggest trust gap in the benchmark: assessment inconsistency.

| Ticket | Priority | Size | Depends On | Scope | Acceptance |
|---|---|---:|---|---|---|
| `LMS-201` | Critical | L | none | Normalize quiz question types and statuses across migration, admin validation, and learner runtime | Admin can create, save, edit, and deliver each supported type without enum drift |
| `LMS-202` | High | M | `LMS-201` | Add image choice quiz type | Admin can author image-choice questions and learners can answer them |
| `LMS-203` | High | M | `LMS-201` | Add keyword-answer quiz type | Keyword responses can be authored, validated, and graded |
| `LMS-204` | Medium | M | `LMS-201` | Add embeddable/public quiz player | Quizzes can be embedded or shared in a controlled way |
| `LMS-205` | Critical | M | `LMS-201` | Add automated tests for quiz builder and grading regression | The common quiz authoring and attempt flows are covered by repeatable tests |

## 7. Sprint 3: Instructor Operations

### Goal

Close the operational gaps that still force facilitator/admin workarounds.

| Ticket | Priority | Size | Depends On | Scope | Acceptance |
|---|---|---:|---|---|---|
| `LMS-301` | Critical | M | none | Expand gradebook v1 to include quiz, mock, and attendance signals | Facilitators can review a learner in one gradebook without jumping across modules |
| `LMS-302` | Critical | M | none | Add manual course enrollment for admin/facilitator workflows | Staff can enroll a learner into a course without storefront checkout |
| `LMS-303` | High | S | `LMS-302` | Add student progress reset | Staff can safely reset learner progress with audit visibility |
| `LMS-304` | High | M | none | Add course co-instructors | Multiple instructors can collaborate on the same course with role-aware permissions |
| `LMS-305` | Medium | M | none | Add instructor application and approval workflow | Prospective instructors can apply and admins can approve or reject them |
| `LMS-306` | Medium | M | `LMS-101` | Add instructor earnings and commission reporting | Revenue splits can be calculated and surfaced for payout operations |

## 8. Sprint 4: Delivery Parity

### Goal

Improve the course-delivery experience beyond the minimum launch-critical fixes already landed.

| Ticket | Priority | Size | Depends On | Scope | Acceptance |
|---|---|---:|---|---|---|
| `LMS-401` | High | M | none | Add course expiry and pause controls | Enrollment access can expire or pause and learner UI reflects the state clearly |
| `LMS-402` | High | M | none | Upgrade the lesson viewer into a stronger dedicated course player | Learner delivery feels more focused and benchmark-aligned across media types |
| `LMS-403` | Medium | M | none | Add per-lesson Q&A | Learners can ask and review lesson-scoped questions |
| `LMS-404` | Medium | S | none | Add coming-soon course state and interest capture | Upcoming courses can be merchandised without pretending to be live |
| `LMS-405` | Medium | M | `LMS-401` | Add richer completion export options for certificates | Print/PDF output is deterministic and supportable |

## 9. Sprint 5: Expansion and Post-Launch Parity

### Goal

Keep these out of launch scope unless a direct commercial requirement appears.

| Ticket | Priority | Size | Depends On | Scope | Reason For Deferral |
|---|---|---:|---|---|---|
| `LMS-501` | Medium | S | none | Wishlist | Conversion nice-to-have, not launch-critical |
| `LMS-502` | Low | L | none | Drag-and-drop certificate builder | Adds complexity before certificate rules are fully stable |
| `LMS-503` | Low | L | none | Native Zoom / Google Meet provisioning | Current external-link approach is adequate for launch |
| `LMS-504` | Low | L | none | Google Classroom integration | Integration-heavy and not required for first market release |
| `LMS-505` | Low | M | none | External audio provider integrations | Current generic audio URLs are sufficient for launch |
| `LMS-506` | Low | L | none | Built-in live streaming stack | High complexity with weak launch leverage |

## 10. Recommended Next Ticket Order

If work starts immediately after the current slice, the next best order is:

1. `LMS-201` quiz-builder consistency
2. `LMS-101` learner-facing subscription checkout
3. `LMS-103` taxes
4. `LMS-302` manual course enrollment
5. `LMS-301` gradebook expansion
6. `LMS-401` course expiry and pause

## 11. Release View

### Pre-launch required

- `LMS-101`
- `LMS-103`
- `LMS-105`
- `LMS-201`
- `LMS-205`
- `LMS-301`
- `LMS-302`
- `LMS-401`

### Pre-launch if facilitator-led delivery is core

- `LMS-303`
- `LMS-304`
- `LMS-305`

### Post-launch by default

- `LMS-501`
- `LMS-502`
- `LMS-503`
- `LMS-504`
- `LMS-505`
- `LMS-506`

# Organization and internship continuation — 5 October 2026

Project: **JK Michaels**, `C:\JK Michaels app`. This implementation remains in the shared working tree. It has not been pushed or deployed as an application release. Course/lesson work by the other agent was preserved. Formatting used candidate copies and checked each source hash before writing back, to avoid replacing concurrent edits.

## Organization implementation

- People directory, search/status/department filters, active account management and clear reserved/available seat totals.
- Individual invitations and atomic CSV batches of up to 100 people. Invitations reserve seats for seven days, store only a token hash, queue encrypted email payloads and accept once. New members choose their own passwords; existing accounts must authenticate as the matching recipient.
- A shared organization lock enforces active-member plus outstanding-invitation limits across account creation and activation. Deactivation revokes database sessions, API tokens and device registrations and releases the seat.
- Scoped departments, manager contacts, member assignments and guarded deletion. Manager designation does not create an administrator role. Legacy foreign-tenant department memberships are excluded from the directory.
- Department/status/overdue learning reports with matching streamed CSV export, spreadsheet-formula protection, private response headers and audit events.
- Platform CSV imports validate the entire batch and never install a shared default password. Imported people use password recovery to select their password; importing does not send an email automatically.

Primary implementation: `OrganizationInvitationService`, `OrganizationSeatService`, `Organization/*Controller`, `Pages/Organization/*`, and migration `100002_add_organization_membership_workflows`.

## Internship and credential integrity

- Submission creation, resubmission, sponsor review, administrative task mutations and completion use a consistent learner → enrollment → task lock order. Version numbers remain unique in the database.
- Empty work, duplicate pending submissions, stale resubmission requests, reviewed/superseded decisions and closed-enrollment writes are rejected. Prior submissions and feedback remain intact.
- Learners can organize unsubmitted tasks but cannot assign review/approval/completion states. Submitted tasks require sponsor review. The task board now includes a submission form, stage filter, keyboard-operable cards and a focus-managed dialog.
- Sponsor access requires the assigned learner and matching organization, with an explicit platform-administrator exception. Self-review is rejected even for privileged accounts. Sponsor dashboard/review/scorecard queries exclude foreign-tenant legacy assignments.
- Track review criteria are configurable. The fallback criteria are Quality, Timeliness, Communication, Initiative and Technical skill. Approval requires every configured criterion scored 0–5. The server calculates the overall average; client-provided totals do not determine results.
- Periodic scorecards require all five documented categories, each an integer from 1–5. Their saved average is calculated on the server; repeated enrollment/period submissions are rejected under the enrollment lock.
- Internship completion requires the latest submission for every task to have an independent approval with rubric evidence, and every track milestone to have a task. It records task, review and milestone identities in credential metadata. Legacy `done` flags alone cannot qualify.
- Credential identity is unique by learner and certifiable record. Migration `100003_enforce_completion_identity` stops if historical duplicates exist; it never deletes duplicate records automatically. MariaDB rollback restores supporting foreign-key indexes before removing these constraints.
- Completed internships and submissions remain visible to their learner. Administrators cannot force unreviewed completion, reopen earned completion through status changes, delete submitted tasks, or delete tracks/milestones that have enrollments.

Primary implementation: `InternshipWorkflowService`, `CertificateService`, internship/sponsor controllers and models, `Sponsor/ReviewSubmission.vue`, `Sponsor/Scorecard.vue`, `Internship/TaskBoard.vue` and the two track-management pages.

## Verification

| Check | Result |
|---|---|
| Organization adversarial workflow suite | 8 tests passed within the full suite |
| Internship integrity suite | 8 tests passed, 90 assertions |
| Full SQLite suite | 118 tests / 826 assertions passed |
| Full MariaDB 10.6.21 suite | 118 tests / 826 assertions passed |
| JavaScript | 12 tests passed: 10 offline/privacy tests and 2 integration-feedback tests |
| Production frontend build | Passed |
| PHP formatting | Passed with `pint.json`; temporary runtimes and generated files excluded |
| Production-copy migration | 117 data tables / 1,359 rows; canonical hashes verified; 42 migrations / 198 foreign-key checks; live database unchanged |

The assessment pause regression now freezes its test clock and asserts exactly 480 remaining seconds after two minutes of a ten-minute attempt. This removes dependence on machine load while preserving the behavioral assertion.

Browser verification used the actual Laravel application at `127.0.0.1:8900`, synthetic accounts, and the non-delivering `array` mail transport:

1. Created Program Delivery with a manager contact and assigned Jordan Demo to it.
2. Created a synthetic invitation; available seats changed from 94 to 93 and reserved seats from 0 to 1. No real invitation was sent.
3. Applied the department report filter and verified the export URL carried the same filter.
4. Submitted a learner's written internship work and observed the task move to In review.
5. Signed in as its sponsor, rated the two configured criteria 4 and 5, and saved approval. The persisted screen showed **Approved · 4.5 / 5** and a closed review form.
6. Read the synthetic database result: enrollment completed, score 4.50, exactly one credential, and one task/review recorded in the completion evidence. [Machine-readable result](internship-browser-result.json).
7. Inspected desktop and 390px mobile organization/report/review layouts. Report and review pages had no horizontal document overflow. Temporary viewport overrides were reset.

Screenshots: [people directory](organization-people-desktop.png), [desktop report](organization-report-desktop.png), [mobile report](organization-report-mobile.png), [desktop rubric](internship-rubric-desktop.png), [mobile rubric](internship-rubric-mobile.png), [persisted approval](internship-review-approved.png).

## Remaining boundaries

This batch does not close the enterprise release. Organization program assignment, paid-course licensing and tenant billing remain; manager contacts are not delegated program administrators. Historical completion/review reconciliation, private internship evidence uploads, broader accessibility/device and simultaneous-writer acceptance, production application deployment and the other open A01–A17 acceptance gates remain recorded in [the audit](../11-ENTERPRISE-AUDIT-2026-10-05.md).

Organization SSO and independent Contabo daily-backup verification remain explicitly deferred by the user. These are not newly requested approvals. No additional real email, campaign or payment was sent by this continuation.

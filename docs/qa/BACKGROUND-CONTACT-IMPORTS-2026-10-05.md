# Background contact imports and workspace switching

Implemented in the JK Michaels project on 5 October 2026. These changes are running in the local preview; the production application release is still pending.

## Behaviour

- Accept UTF-8 CSV files up to 50 MB with no fixed contact-count limit. Required headers are `first_name`, `last_name`, `email`; use the provided template for optional address, company, source, notes and type fields.
- The default type fills blank type cells; per-row types override it. Import creates CRM contacts only, without messages, accounts or enrollment.
- Upload returns HTTP 202 with a durable receipt. A dedicated database-backed queue processes 100 records at a time even when the application's normal queue is configured as synchronous.
- Each transaction commits contacts, row errors, counters, byte offset and the next queued chunk together. Repeated uploads with the same request UUID and repeated deliveries of the same job do not create duplicate contacts. Changed content cannot reuse an upload reference.
- Duplicate checks include archived contacts, use an indexed normalized email and share the existing CRM write lock. Existing contacts are preserved. Valid rows continue past invalid/duplicate rows.
- The streaming reader retains at most 64 KB per CSV record, handles escaped quotes and multiline fields, and safely skips oversized records without losing the next record. It does not load the complete file into PHP memory.
- Only the importing active platform administrator can list results, cancel, retry or download the error report. Workers recheck account/role status for each chunk; revocation cancels the remaining work. Cancellation retains completed contacts. Failed imports resume from the committed checkpoint.
- Sources are stored privately and removed after completion/cancellation. The daily `crm:cleanup-imports` command removes terminal sources retained over seven days while preserving history/error records. Expired-source retries ask the administrator to upload again; existing emails will be skipped.
- The import dialog displays upload and processing progress, counts, recent history, resume/cancel and an error report. Closing it or leaving the page does not interrupt the worker. Only the latest 20 imports are shown.
- Workspace selection is now a bordered, tinted **Switch workspace** button. Its menu labels the current workspace, displays only authorized choices and supports keyboard navigation and Escape.

## Verification

- Full SQLite suite: **162 tests / 1,279 assertions passed**. This includes nine new background-import tests and six existing CRM workflow tests. Coverage includes files larger than 512 KB, more than 100 records, mixed types, multiline/oversized CSV records, replay protection, chunk rollback, retry, cancellation, expired sources, role/account revocation and ownership isolation.
- Full isolated MariaDB suite: **162 tests / 1,279 assertions passed**. Source archive SHA-256: `265c7231b15a76fd13ec3935d373bec15670f23ca45badbb046df96e81e9ed51`. The final explicit index removal in the migration's `down()` path also passed the targeted MariaDB migration suite (**2 tests / 21 assertions**) and the full SQLite suite. No production data was used for these tests.
- JavaScript regression suite: **12 passed**. Vite production build succeeded. PHP formatting checks passed after formatting the touched files.
- Real browser/queue acceptance uploaded a **2,165,420-byte CSV with 5,002 records**. Before starting the worker, the database had one queued job and zero imported rows, proving the upload request did not import inline. A real worker imported **5,000 contacts** (2,500 Customer and 2,500 Partner), skipped one duplicate and one invalid email, and removed the source. The administrator closed the dialog, switched into Learning using the keyboard, switched back, and reopened the saved completed result.
- The error-report response and exact CSV contents passed authenticated HTTP feature tests. The browser's download event did not report a completion, so browser file saving is not claimed. The report link remained available without a page error.
- Phone-width checks at 390 px showed no document overflow in the import dialog or workspace menu. Keyboard Home/Enter switched to Learning; selecting Administration returned successfully; Escape closed the menu and returned focus to its trigger. These checks do not constitute full accessibility certification.
- No emails or WhatsApp messages were sent. Local preview evidence is in [the redacted JSON record](background-contact-import-evidence.json), [the completed import](background-contact-import-complete-2026-10-05.png) and [workspace switcher](workspace-switch-button-2026-10-05.png).

## Deployment

Deploy migration `2026_10_05_100007_add_background_contact_imports.php`, the associated source and the rebuilt frontend together. Configure PHP `upload_max_filesize=50M`, `post_max_size=55M` and the web-server request body allowance to at least 55 MB for this application. Keep uploaded sources outside the public disk.

The dedicated worker command is `php artisan queue:work crm_imports --queue=contact-imports --sleep=2 --tries=3 --timeout=90 --memory=256 --max-time=3600`. A reviewed Linux service definition is provided in `ops/jkm-contact-imports.service`; a default-queue worker alone does not process these imports. The queue connection deliberately uses the same application database as the receipt/checkpoint transaction. Monitor this worker, queue age and failed jobs, and keep the existing scheduler running for retention cleanup. Do not point this worker at the isolated test/rehearsal database.

The local port-8900 preview has the upload limits applied and a dedicated import worker running. The production service is prepared but has not been installed/enabled by this change. The rollback refuses to remove a populated import ledger; preserve audit/checkpoint records during rollback.

## Test-data cleanup requested before deployment

At the user's request, the local preview's 5,000 bulk-import test contacts and three earlier synthetic demo contacts were removed on 5 October 2026. Their completed test batch, two row-error records and earlier two-contact import receipt were also cleared. The CRM and import queue are now empty. Existing audit history was retained, and a cleanup event was appended. A recovery snapshot remains in the Git-ignored `tmp` directory; neither preview databases nor `tmp` contents belong in a production deployment package. Production data was not changed. See [cleanup verification](contact-test-data-cleanup-2026-10-05.json).

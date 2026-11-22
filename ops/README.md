# JK Michaels operational rollout

These files support the deployment documented in the ignored `prod_deploy.txt`. They do not contain credentials. The application root is `/var/www/vhosts/jkmichaelspm.com/app.jkmichaelspm.com`; the application account is `jkmichaelspm.com_8glivfes07m` and PHP is `/opt/plesk/php/8.2/bin/php`.

## Verified on 5 October 2026

- SMTP authenticated and the one authorized delivery test reached the user's inbox.
- Project-specific ClamAV signatures update every six hours. Clean-file and harmless EICAR detection tests passed as the application account. The wrapper refuses scans when signatures have not been checked for 48 hours.
- PDF metadata/raster output and PPTX-to-PDF conversion passed with `document-sandbox.py`. The probe verified that host application paths, host network access and inherited environment secrets are unavailable. Invalid paths, symlinks and unsupported arguments were rejected.
- A protected production SQLite copy migrated to a separate MariaDB database. All 117 source data tables/1,366 rows were transferred and compared before pending migrations; 204 foreign-key relationships passed afterwards. See the date-normalization and legacy-classification details in the evidence.
- A separate application-owner runtime used that migrated copy. The scheduler's heartbeat dispatched to a dedicated queue, the worker consumed it, and both timestamps persisted. Loopback HTTP tested health/login, guest redirects and synthetic learner dashboard/catalog/download pages. The listener was stopped afterwards. No campaign, payment or external request ran in that server probe. The copied legacy resource also passed scanner/hash/private migration checks; its live source was unchanged. A separate local Paystack test fixture has recorded provider evidence.

The observations above preceded cutover. Release `20261005T125643Z` is now deployed with a dedicated production MariaDB database, active default/contact-import workers, one-minute scheduling and five-minute readiness monitoring. All 28 live operational checks pass in Paystack TEST mode. See the [deployment and recovery record](../docs/qa/PRODUCTION-DEPLOYMENT-2026-10-05.md). SSO and independent Contabo backup-service verification remain explicitly deferred by the user.

## Scanner and document isolation

`install-scanner.sh` installs only the project scanner/update mechanism. `install-document-sandbox.sh` installs root-owned wrappers; neither changes the Laravel environment. The host's masked generic ClamAV services remain masked. The document wrapper grants read-only runtime libraries, one input and one preview directory; it isolates networking/processes, clears environment variables and bounds time, memory, file size and descriptors. It must run as the application account, never root.

The isolation policy uses [Bubblewrap's documented namespace and bind-mount controls](https://github.com/containers/bubblewrap/blob/main/README.md). Bubblewrap itself is a mechanism; the arguments in `document-sandbox.py` define the policy. The host's Ubuntu security updates for Bubblewrap, Poppler and LibreOffice were applied before the successful conversion probe. No MariaDB upgrade or global service restart was performed.

Merge these non-secret settings into the reviewed production environment during application rollout:

```dotenv
MEDIA_REQUIRE_SCAN=true
MEDIA_SCANNER=/usr/local/bin/jkm-clamscan
SCANNER_HEALTH_FILE=/var/lib/jkmichaels/clamav/updated_at
DOCUMENT_PDFINFO=/usr/local/bin/jkm-pdfinfo
DOCUMENT_PDFTOPPM=/usr/local/bin/jkm-pdftoppm
DOCUMENT_LIBREOFFICE=/usr/local/bin/jkm-soffice
DOCUMENT_SANDBOX_EVIDENCE=/var/lib/jkmichaels/document-sandbox-health.json
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
SESSION_SECURE_COOKIE=true
APP_ENV=production
APP_DEBUG=false
```

Preserve the current application key and verified SMTP settings. Payment test keys remain test-only. Do not replace the production environment with a template. Sandbox evidence must match the installed wrappers/policy and be less than 30 days old; rerun the conversion/isolation probe after changing them. Keep PHP GD enabled.

## Database cutover and rollback

1. Freeze a reviewed source/build snapshot after concurrent authoring changes have settled. Install from its lockfiles and rerun the combined checks.
2. Enter maintenance mode, stop writes and workers, and take a fresh protected database plus storage/environment snapshot. The earlier rehearsal is evidence, not a current cutover backup.
3. Create a dedicated application SQL database and restricted application account. Do not use the test/rehearsal database or credentials as production.
4. Rehearse the current source snapshot again. Apply the source's original migrations, transfer and compare every row, then apply all pending migrations. `rehearse-sql-migration.php` deliberately accepts only the fixed isolated database; it is not a production import command.
5. Record type transformations. The historical `free`/`premium`/`enterprise` course labels map to `self_paced`, while `legacy_commercial_type` retains the original label and prices remain unchanged. Unknown labels abort. Resource media types support 127 characters. SQL `DATE` fields retain the date component; source timestamps remain in the protected SQLite snapshot and the rehearsal lists every affected column/count.
6. Retain all nullable historical assessment/revision/audit fields on code rollback. Do not run destructive `migrate:rollback` against new learner/payment evidence. An engine rollback requires stopping writes and reconciling records created after cutover before restoring the prior snapshot.
7. Atomically change the application release, assets and database configuration, rebuild Laravel caches as the application owner, and verify restricted file permissions. Inspect pending jobs and scheduled campaigns before enabling workers so validation does not send unintended messages.

## Queue and scheduler

`jkm-queue.service`, `jkm-scheduler.service` and `jkm-scheduler.timer` target this application account and PHP version. Install/enable only after the new application schema and environment are in place. The worker timeout is 120 seconds; database visibility must remain at least 180 seconds. Keep the timer unique; avoid a duplicate cron entry.

Verify both `operations:scheduler-heartbeat` and `operations:queue-heartbeat` within three minutes. `php artisan enterprise:check --json --allow-test-payments` is a sandbox readiness gate, not authorization for live billing. Production rollout also needs monitoring/alerts, representative load, current storage migration evidence and the remaining audit acceptance checks.

## Validation-only scripts

`validation-bootstrap.php`, `validation-router.php` and `validate-runtime.php` are restricted to `/var/lib/jkmichaels/readiness-20261005` and the separate rehearsal database. The router requires a secret probe header and allows only specific GET routes. Synthetic sign-in exists only inside this protected test router. Never use these files as a public web entry point or copy their settings into the live application. The temporary listener is not a service and is stopped after verification.

## New data and media checks before cutover

Include forward migrations through `2026_10_05_100006_add_payment_refund_ledger.php`. Contact imports retain types/address fields and their replay ledger; course prerequisites retain graph relationships; the refund ledger records financial intent and enrollment provenance. Do not run their destructive down migrations over newly created business records.

Run `media:migrate-legacy-resources` first as an inventory, then `--apply` under the application account during a protected cutover. It scans/copies/hash-checks before removing public access. The one live legacy resource was migrated during this release and its former public URL returns 404. Preserve the backed-up original, private asset and database references together for rollback. `media:cleanup-uploads` is scheduled hourly and preserves held locks and finalized assets.

Run `learning:reconcile` as a reporting check. The current production-copy report found 32 historical completions without supporting current-curriculum evidence. It deliberately returns nonzero and preserves them. Do not fabricate certificates, delete them, or run ad hoc status resets. Their classification is pending user input.

`billing:reconcile-refunds` runs every ten minutes. Unknown/pending/processing/needs-attention records remain visible until verified. It never resubmits a refund POST. Failed or ambiguous outcomes require review of the existing Paystack transaction. Verify that production test mode is displayed after installing the supplied test keys; no live key is authorized by the green sandbox checks. Actual inbound provider webhooks and a renewal invoice cycle still require deployed evidence.

## Background contact imports

The contact-import UI now accepts CSV files up to 50 MB and queues processing on the dedicated `crm_imports` database connection and `contact-imports` queue. Deploy migration `2026_10_05_100007_add_background_contact_imports.php` with the updated application and frontend. Configure this application's PHP upload limit to `50M`, POST limit to `55M`, and web-server body limit to at least 55 MB. The queue connection must continue to use the application's database so chunk checkpoints and the next job commit atomically.

Install and enable the reviewed `jkm-contact-imports.service` during the coordinated production release, after schema/configuration updates. A worker listening only to the default queue will leave contact imports waiting. The daily `crm:cleanup-imports` schedule retains failed sources for seven days and preserves result/error records. Completed/cancelled sources are removed immediately. Monitor queue age, failed jobs and worker health. Verify a representative large import, close/reopen recovery, row error report, cancellation and revocation before treating the feature as live. See [implementation and local evidence](../docs/qa/BACKGROUND-CONTACT-IMPORTS-2026-10-05.md).

## Production monitoring

`jkm-health.service` and `jkm-health.timer` run the readiness command every five minutes under the application account. They are installed and enabled for the deployed test-payment release; results and failures are retained in the systemd journal. Check `systemctl status jkm-health.timer` and `journalctl -u jkm-health.service`. External notification/alert routing is not configured. Remove `--allow-test-payments` only during the approved live-billing transition after provider acceptance; do not change keys simply to make a check green.

## AI and WhatsApp configuration

Set `FLEXICHURCH_API_KEY` in the application's private `.env` or deployment secret store. `FLEXICHURCH_BASE_URL` must use the intended provider's HTTPS endpoint; the remaining non-secret defaults are listed in `.env.example`. These names describe the external provider used by JK Michaels. Never put the key in a `VITE_*` variable or frontend code. Preserve existing application/database/mail settings when adding it.

Check the effective configuration of the process serving the affected page: a configured production environment does not configure a local preview. After a production environment change, rebuild Laravel's configuration cache under the application owner and restart applicable long-running workers using the normal deployment procedure. Do not copy a cached production configuration into local tests.

On Windows, AMpps PHP may require an explicit trusted CA bundle for outbound HTTPS. Configure `curl.cainfo` and `openssl.cafile` for that PHP runtime and restart it. Use certificates from the operating system's trusted store or an approved maintained bundle; never disable TLS verification to resolve a connection failure. The port-8900 preview launcher has these per-process settings. A successful provider status response verifies API reachability/authentication; actual WhatsApp delivery additionally requires a connected phone and an authorized recipient test.

# JK Michaels production deployment — 5 October 2026

Release `20261005T125643Z` is deployed at **https://app.jkmichaelspm.com**. Traffic reopened at **13:09:59 UTC (14:09:59 WAT)**. The subsequent live verification passed all **28 operational checks**. Payments are explicitly **Paystack TEST**, not live billing.

The deployment used a frozen snapshot of `C:\JK Michaels app`, not a fresh Git pull or a moving working tree. The Git baseline remains `a1eba2381311ae98a2d7bf2e44b45f1c45caff1f`; the release includes reviewed uncommitted work. No claim of a GitHub release commit is made. Local databases, credentials, test uploads, validation routers, tests and operator scripts were excluded from the application archive.

## Release and verification

| Evidence | Result |
|---|---|
| Runtime archive SHA-256 | `40c02a9fd4fe681c301a123f2df0ce50aff3a27da5d3bd9a1eebd391a872a29d` |
| Final deployed integrity | All 929 archive files hash-verified, allowing only the recorded readiness correction; validation router, debug page and local SQLite database absent |
| Production asset build | Passed; frozen source verified unchanged during compilation |
| Frozen SQL suite | 175 tests / 1,415 assertions passed on isolated MariaDB |
| Readiness command correction | Absolute executable scanner paths are now recognized; 1 regression test / 2 assertions passed on SQLite and isolated MariaDB |
| Final SQLite-to-SQL rehearsal | 117 data tables / 1,369 rows; canonical row hashes verified before forward migrations |
| SQL schema | 47 migrations applied; 208 foreign-key checks passed; production restore dump matched the verified rehearsal dump |
| Live database | Dedicated `jkm_app_20261005`, with its own restricted application user; test/rehearsal databases remain separate |
| Preserved production records | 29 users and 130 course enrollments |
| CRM | 0 contacts and 0 import batches; no cleared preview contacts transferred |
| Queues | Default and contact-import workers active and enabled; actual heartbeat jobs consumed; 0 pending and 0 failed jobs at verification |
| Scheduler | Enabled one-minute timer; real scheduler and queue heartbeats persisted |
| Health monitor | Enabled five-minute systemd timer; readiness output goes to the journal; no external alert recipient configured |
| Scheduled monitor confirmation | The automatic 13:15 UTC run completed successfully with exit status 0 |
| Learning media | One live legacy resource scanned, copied privately and hash-verified; former public URL returns 404; anonymous private URL requires sign-in |
| Upload limits | Domain PHP upload `50M`, POST `55M`, memory `256M`; nginx body limit 128 MB |
| HTTPS and UI | Health, login, registration, guest denial and four entry assets passed; live login rendered without browser console warnings/errors |
| Public file boundaries | `/.env`, `/storage/app/private` and `/vendor/autoload.php` return 404 |
| Persona routes | Existing verified learner, facilitator, SME and sponsor accounts returned 200 through read-only application-kernel checks |
| Admin access | Existing administrator is unverified and is redirected to email verification; no account was silently verified or password changed |
| Organization administrator | No active production account available; prior isolated fixture coverage remains the evidence |

The only runtime patch after the archive freeze is `app/Console/Commands/EnterpriseReadiness.php`, SHA-256 `2e457bc7294787c01f6169876c2241e6d03c43cf8d8f6fec31887fd50631349f`. The original archive and manifest remain immutable; a separate hotfix record identifies this overlay. The scanner itself successfully scanned the migrated live resource before the checker correction.

Machine-readable evidence: [production verification](production-deployment-2026-10-05.json). UI evidence: [live sign-in screenshot](production-login-2026-10-05.png).

## Backup and recovery

Protected deployment directory: `/root/jkm-release-20261005T125643Z`.

- `rollback-backup/application.tar.gz` contains the prior application, dependencies, environment and storage. SHA-256: `676e4b578142a4efc395c9226937de37a830389fa587adff4cf711c6ab3ac11f`.
- `rollback-backup/database.sqlite` is the final maintenance-mode source snapshot. Its integrity, foreign keys and all 118 restored table counts passed. Every archived regular file was extracted and hash-compared.
- `final-migrated.sql`, migration results, private database settings, cutover journal, service logs and the readiness patch record are protected in that directory.
- The preceding application remains at `/var/www/vhosts/jkmichaelspm.com/.jkm-releases/pre-20261005T125643Z`.
- The live path is an application-owned symlink to `/var/www/vhosts/jkmichaelspm.com/.jkm-releases/20261005T125643Z`. Runtime storage is `/var/www/vhosts/jkmichaelspm.com/.jkm-shared/storage`.

The first switch failed because the release symlink was root-owned and Plesk enforces matching symlink ownership. The guard returned to the prior release; correcting ownership restored its HTTPS checks. Before retrying, all business tables were compared with the maintenance snapshot to ensure no intervening changes would be lost. The retry then passed live HTTPS checks and reopened traffic.

For a future rollback, enter maintenance and stop the queue/import workers and scheduler first. Take a **new** SQL dump and shared-storage/environment backup. Inspect records written since this release before selecting code or database recovery. Do not blindly restore the old SQLite snapshot, run destructive down migrations, or discard new learning/payment/contact data. Preserve matching code, environment, database and storage as a recovery set. The earlier guarded rollback was before new-release traffic; post-cutover data reconciliation is a separate recovery operation.

Useful checks, as an authorized server operator:

```sh
systemctl status jkm-queue.service jkm-contact-imports.service jkm-scheduler.timer jkm-health.timer
journalctl -u jkm-health.service -u jkm-queue.service -u jkm-contact-imports.service --since '30 minutes ago'
cd /var/www/vhosts/jkmichaelspm.com/app.jkmichaelspm.com
runuser -u jkmichaelspm.com_8glivfes07m -- /opt/plesk/php/8.2/bin/php artisan enterprise:check --json --allow-test-payments
```

## Remaining acceptance

This is a verified production deployment with test payments, not clearance for unrestricted enterprise use. Thirty-two historical completions still require evidence review; their original records were retained and no certificates were invented. The audit's wider tenant/export/offline, device/accessibility, representative-load and provider webhook/renewal acceptance remain open where previously recorded. SMTP and the existing AI/WhatsApp provider configuration were preserved; this deployment sent no campaign, WhatsApp message or additional test email.

Organization SSO and independent verification of Contabo's daily backup service remain deferred by the user. Live billing requires separate acceptance and configuration. The five-minute monitor records failures in the system journal; external alert routing remains to be configured.

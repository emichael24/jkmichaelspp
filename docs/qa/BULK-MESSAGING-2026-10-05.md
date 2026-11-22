# Bulk messaging and rich-text email — 5 October 2026

Implemented in `C:\JK Michaels app` and subsequently activated on the local application at `http://127.0.0.1:8900`. **Production has not been changed.** Validation used an isolated in-memory database, an in-memory email transport, a mocked WhatsApp service, and synthetic browser fixtures. No real campaigns or correspondence were sent.

## Problems corrected

- Personal Messages only offered an individual user search. Platform administrators now have a visible bulk-campaign entry there, including inside the New Message dialog. CRM Contacts also provides a bulk-campaign shortcut with the current contact type preselected.
- Campaign selection exposed only a few hardcoded user roles. The composer now supports platform users, CRM contacts, or their combined audience; dynamically loaded user roles; organization members and a specific learning organization; CRM contact types (lead, prospect, customer, partner, student, alumni); and a specific CRM organization.
- CRM quick-send posted `recipient_type` and `recipient_id`, while its controller required `user_id`. The endpoint now resolves actual CRM contacts, including contacts without a platform account, and returns the redirect expected by Inertia forms. Individual correspondence is queued and logged as a CRM activity.
- Campaigns and CRM email used plain text inputs and delivery used `Mail::raw`. Both now use the same rich-text email component, with headings, emphasis, lists, links, tables, undo/redo, and preview. The server sanitizes HTML with a tag/attribute allowlist and sends HTML with a readable plain-text alternative. Existing plain-text campaigns remain plain text.

## Channels and recipient behavior

1. **Email:** active recipients with valid email addresses and campaign consent. Linked or matching platform accounts must be active, verified, in an active organization where applicable, and opted in. Individual CRM correspondence is separate from marketing campaigns.
2. **WhatsApp:** valid international numbers and campaign consent. Numbers are normalized for duplicate detection; actual provider acknowledgment is still required before recording a successful send.
3. **Platform inbox:** active, verified accounts with announcements enabled. CRM contacts need a linked or email-matched account. Each recipient receives a separate private conversation message from the campaign administrator. Recipients can view and reply to that administrator across organization boundaries only after receiving the campaign; other recipients remain inaccessible.

Preview and launch use the same audience resolver. Preview shows the eligible count, exclusions, duplicates, and at most five sample recipients. Duplicate email addresses, phone numbers, or inbox account IDs receive one message per campaign. Eligibility is rechecked before delivery, including consent withdrawal, contact archival, account/organization deactivation, and address changes.

CRM contact forms now expose email and WhatsApp campaign subscriptions, defaulting to off. Existing imports do not silently enroll contacts. Subscription changes enter the audit trail. Signed unsubscribe links work without a login; GET only displays confirmation, and POST changes the preference, including a matching platform account's marketing preference.

## Queue, scheduling, and history

The existing notification outbox, scheduled dispatcher, quiet hours, daily delivery limits, cancellation, and provider-uncertainty handling are retained. CRM deliveries can reference a contact without requiring a user. Inbox delivery uses a unique delivery ID and writes the message and completion state in one transaction. Replaying a completed worker does not send again.

The campaign detail page previews formatted content and recipient counts. Delivery rows are matched by message-log ID, which also handles CRM recipients without user IDs. Successful CRM delivery updates `last_contacted_at`; merely queueing correspondence does not claim it has been sent.

## Main implementation files

- `app/Services/CampaignAudienceService.php`: audience validation, eligibility, normalization, deduplication, preview.
- `app/Services/CampaignDeliveryService.php`: durable recipient expansion and campaign lifecycle.
- `app/Services/NotificationDeliveryService.php`: contact recipients, HTML email and plain-text alternative, inbox delivery, delivery-time eligibility.
- `app/Services/EmailContent.php`: server-side email HTML sanitization and text extraction.
- `app/Http/Controllers/Admin/MessagingController.php`: audience options, preview, campaign validation and corrected CRM quick-send contract.
- `app/Http/Controllers/ContactMessagePreferenceController.php`: signed contact unsubscribe confirmation.
- `app/Services/CommunityAccessService.php`: narrowly scoped access to a broadcast's administrator conversation.
- `resources/js/Components/Messaging/`: reusable audience selection and rich-text email UI.
- `resources/js/Pages/Admin/ERP/Messaging/` and `Contacts/`: campaign and individual email workflows.
- `tests/Feature/BulkMessagingTest.php`: 13 targeted feature tests.

## Validation

Passed **49 tests / 409 assertions** across:

```text
tests/Feature/BulkMessagingTest.php
tests/Feature/NotificationDeliveryTest.php
tests/Feature/CrmContactWorkflowTest.php
tests/Feature/AiWhatsAppIntegrationTest.php
tests/Feature/TenantAndCompletionTest.php
```

Coverage includes role/organization/CRM-type filtering; combined-audience deduplication; sanitized HTML and plain-text MIME bodies; contact-only correspondence; changed addresses, consent and archived contacts; guest unsubscribe confirmation; mocked WhatsApp acknowledgments; cross-organization inbox privacy; quiet hours; cancellation; non-admin denials; scheduled dispatch; replay protection; and frequency limits.

The isolated production Vite build passed. Changed PHP files were formatted with Pint. Browser checks covered the real Vue components with synthetic data: desktop rich-text formatting and preview; switching channels while retaining message text; mobile CRM filters; CRM list and detail email actions; and a 390-pixel viewport without horizontal document overflow. Screenshot: `tmp/jk-bulk-messaging/crm-email-desktop.png` (local, ignored test artifact).

## Deployment requirements and limits

- Deploy the reviewed code and rebuilt assets together with `2026_10_05_120000_add_crm_campaign_delivery.php`. It depends on the repository's current CRM and notification schema. The shared workspace contains unrelated ongoing changes; do not deploy the whole working directory without reviewing the release set.
- Run the reviewed migrations before serving these pages, then restart queue workers and use the existing `notifications:dispatch` scheduler. The new migration was exercised on isolated SQLite; production-database migration execution remains a deployment check.
- Keep SMTP and WhatsApp configuration in protected server configuration. This pass did not change credentials, pair phones, or submit real sends.
- Email/WhatsApp delivery remains asynchronous. Provider acceptance is not proof of inbox arrival; platform inbox delivery is not proof of reading.
- A campaign selects one channel. Create separate campaigns when the same announcement needs multiple channels. Attachments and image uploads are outside this change.
- Very large audience expansion retains the existing synchronous launch transaction and should be load-tested at the intended production audience size.

## Local activation follow-up

The user confirmed that the page being refreshed was `http://127.0.0.1:8900/admin/erp/messaging`. Its public asset manifest still referenced the earlier build, and `preview-v2.sqlite` did not contain the new messaging migration.

On 5 October 2026, the local database was backed up using SQLite's consistent backup API, then only `2026_10_05_120000_add_crm_campaign_delivery.php` was applied. The previous asset manifest was saved. A fresh build was published to `public/build`, retaining older hashed assets for existing tabs and switching the manifest atomically. Local route and compiled-view caches were cleared. The running server and its non-delivery mail settings were preserved; production was not contacted or deployed.

The actual local application was checked with the existing administrator session. The personal Messages link opened `/messages` and displayed the inbox with the new bulk-campaign shortcut. Following that shortcut opened the campaign dialog with email, WhatsApp and platform-inbox channels, user and organization selectors, and the rich-text email toolbar. The local CRM list displayed its new Bulk campaign shortcut; there were no contact records in that preview database to open an individual composer. Individual CRM editors had already passed the fixture-based browser checks above.

Activation evidence, the database backup, and previous manifest are retained under `tmp/jk-bulk-messaging/local-activation/`. The updated campaign asset was fetched from port 8900 and matched the published build hash. Campaign, delivery and direct-message counts remained unchanged, and no messages were sent. The shared preview database recorded a separate `crm_test_data_cleanup` audit action at 13:43:38 WAT, and its contact count changed from 5,003 in the backup to zero during this work. That concurrent cleanup was preserved; this activation did not restore or seed contacts.

# Course and lesson authoring implementation

Date: 2026-10-05. Project: `C:\JK Michaels app` (JK Michaels).

Follow-up: PDF/PPTX embedding and source protection are implemented in the [protected document reader](PROTECTED-DOCUMENT-READER-2026-10-05.md). That note supersedes the original inline-PDF and source-link behavior described below.

This focused change addresses the reported Commerce 404 and basic course/lesson forms. It is implemented locally in the shared working tree. This note does not certify the whole platform as enterprise ready, and this side conversation has not deployed these changes.

## Implemented experience

- Shared visual editor for admin and facilitator course descriptions and lesson articles: headings, emphasis, lists, links, quotes, code blocks, tables, dividers, undo/redo and expanded editing. Lesson images support an accessible description or an explicit decorative choice.
- Guided course setup with a live preview card, setup checklist, draft workflow and visible validation errors.
- Curriculum builder with module creation/renaming, lesson links, totals, drag ordering, keyboard-accessible move buttons, removal confirmation and empty states. Reordering validates the complete current set on the server and respects course ownership and review locks.
- Lesson Studio with content format cards, course outline, readiness checklist, settings, explicit save status, Ctrl/Cmd+S and protection against leaving with unsaved changes. This is explicit saving, not background autosave.
- Upload controls for video, audio, PDF, slides, inline images and SCORM ZIPs, with progress, cancellation, retry and useful errors. Videos use resumable chunks within the current editor session. Existing approved HTTP/HTTPS media links remain supported.
- Private media ownership and learner access checks, including references embedded in rich text. PDFs can be viewed inline and explicitly downloaded when lesson settings and access permit it.
- Desktop/mobile learner preview for article, video, audio, PDF and slide content. SCORM packages use the saved learner launch flow; the authoring preview does not execute a package.
- YouTube/Vimeo URL recognition and a shared hosted player for preview and the learner video component, including position reporting and chapter seeking. Other URLs use native media playback.
- `/admin/commerce` redirects to `/admin/billing`. The regression test also checks the working tree's SQLite-compatible billing aggregation.

## Main implementation locations

- `resources/js/Components/Authoring/`: RichTextEditor, MediaUpload, CurriculumBuilder, CourseSetupGuide and LessonPreview.
- `resources/js/Components/Admin/LessonEditor.vue`: shared lesson authoring interface.
- Admin and Facilitator course/lesson create and edit pages: integration and save redirects.
- `app/Http/Controllers/AuthoringMediaController.php` and `AuthoringCurriculumController.php`: scoped upload and reorder endpoints.
- `PrivateMediaService`, `PrivateMediaController` and `LessonAuthoringService`: media validation, attachment ownership and authorized delivery.
- `resources/js/Components/Lesson/HostedVideo.vue`, `VideoPlayer.vue` and `resources/js/lib/hostedVideo.js`: hosted video handling.
- `resources/js/Components/UI/Button.vue`: consistent alignment of icons inside button labels.
- `tests/Feature/AuthoringStudioTest.php`: focused behavior/security regressions.

## Verified locally

- PHPUnit: **15 tests, 169 assertions passed** across AuthoringStudioTest, CourseAuthoringTest, PrivateMediaTest and ContentGovernanceTest.
- Covers admin/facilitator rich content persistence and save redirects; private PDF/image ownership, enrollment access and revoked access; SVG MIME spoofing; locked-course uploads; scoped/full-set ordering; Commerce routing and SQLite billing.
- Six Node assertions passed for hosted video URL recognition, including rejected spoofed hosts and unsafe schemes.
- `npm run build` passed after the final player/button changes.
- Browser: admin article formatting, table insertion/removal, image upload and alternative text, save/reopen persistence, desktop/mobile preview and curriculum navigation.
- Browser: facilitator guided course creation saves a draft and preserves its visual description; module creation, lesson creation, save-to-editor redirect, saved-state feedback and learner preview all pass through the actual UI.
- Responsive check at 390 × 844: no horizontal document overflow in the lesson editor.

Screenshots:

- `authoring-course-setup.png`
- `authoring-curriculum.png`
- `authoring-lesson-studio.png`
- `authoring-mobile-preview.png`
- `authoring-narrow-editor.png`

The browser checks use a separate SQLite database, session directory and media directory under ignored `tmp/jk-authoring-preview`, served only at `127.0.0.1:8901`. They do not modify production course data.

## Release checks still required

- Coordinate the working tree changes with the main release; the repository contains unrelated ongoing enterprise work. Do not deploy a partial selection without its backend dependencies and matching built assets.
- On the deployment host, validate upload limits, private disk permissions, malware scanner availability and fail-closed behavior with representative files. Local browser fixtures intentionally do not invoke a production scanner.
- Check uploaded audio/video decoding, large-video chunk retry, and third-party hosted-video playback using the intended real course assets. URL parsing and compilation passed; external provider playback was not certified by these local checks.
- Validate the intended SCORM packages in the saved learner player. This change does not claim general SCORM conformance or complete MasterStudy feature parity.
- Run authenticated smoke checks after deployment for Commerce, both authoring roles, saving/reopening and learner media delivery. The previously reported live pages remain unchanged until deployment.

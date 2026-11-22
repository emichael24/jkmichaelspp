# Protected PDF and PowerPoint lessons

Implemented locally in `C:\JK Michaels app` on 2026-10-05. No production deployment or production configuration changes were performed in this side conversation.

## Behavior

Uploaded PDF and PPTX lessons open inside a shared document reader, also used in the author preview. The reader provides page/slide navigation, a page selector, keyboard navigation, zoom, fit-to-width, fullscreen, loading states and retry. PowerPoint presentations are rendered as static slides, preserving the visual layout rather than exposing an editable presentation.

The viewing flow returns rendered PNG pages, not PDF/PPTX source bytes. Each page has the viewer's account ID, UTC timestamp and page number burned into its pixels. A download action appears only when the server confirms that the lesson allows source downloads. New lessons default to downloads disabled. Existing lesson permissions are preserved; disable **Allow source downloads** on any existing lesson that should be protected.

The original private-media endpoint also enforces download permission. Hiding a button alone is not the protection: direct source requests, `?download=1` and range requests are denied for learners without a download-enabled, accessible lesson. Authors can still manage their own source files; platform administrators retain administrative access.

Every page request checks enrollment/publication/release rules again. Revoked enrollment prevents subsequent page requests. Reader responses are private/no-store, have same-origin resource policy and are not added to the application's offline cache. The reader does not expose a public Office/Google viewer or send files to an external document service.

## Limits

- Browser content cannot be made impossible to copy: screenshots, cameras, developer tools and authorized page-image capture remain possible. This protects source distribution and deters copying; it is not DRM or a promise of screenshot prevention.
- Printing and ordinary save shortcuts are suppressed inside the reader and the print stylesheet hides its pages. A user-controlled browser can bypass client-side restrictions.
- Existing public document URLs must be replaced with private uploads. New external PDF/PPTX lesson links are rejected because the application cannot protect a file hosted elsewhere. The reader fails closed for legacy links.
- PPTX animations, transitions, embedded video/audio and speaker notes are not part of the static slide reader. Upload media as a separate lesson format where needed.
- The page-image mode does not provide selectable or screen-reader-readable document text. Provide an equivalent accessible article/transcript through lesson content when required.
- Maximum: 20 MB source uploads and 200 pages/slides. Presentations with macros, embedded active objects, unsafe archive paths or externally linked media are rejected. Embed the media in the file or remove it before uploading; ordinary web hyperlinks are inert in the rendered output.

## Implementation

- `ProtectedDocumentService`: validates PPTX structure, converts through LibreOffice, reads/rasterizes PDFs through Poppler, caches private page renders, applies per-viewer watermarks using PHP GD, bounds pages and subprocess timeouts, and serializes concurrent preparation with file locks. Converter processes receive a minimal operating-system environment, excluding HTTP request variables and application credentials.
- `ProtectedDocumentController`: lesson-scoped manifests/pages and owner-scoped author previews.
- `PrivateMediaController`: original-source download authorization.
- `LessonAuthoringService`: private document upload requirement.
- `PrivateMediaService`: presentation validation and compatibility with libmagic versions that classify OOXML as ZIP/octet-stream.
- `DocumentReader.vue`: shared canvas-based reader; `PdfViewer.vue` and `LessonPreview.vue` integrate it.
- `config/documents.php`: renderer locations, page limits and timeouts.

## Deployment requirements

Provision patched LibreOffice Impress, Poppler (`pdfinfo` and `pdftoppm`), PHP GD and PHP ZIP for the application runtime. Keep the malware scan enabled. Set these environment variables when binaries are not on the service PATH:

```dotenv
DOCUMENT_LIBREOFFICE=/usr/bin/soffice
DOCUMENT_PDFINFO=/usr/bin/pdfinfo
DOCUMENT_PDFTOPPM=/usr/bin/pdftoppm
DOCUMENT_CONVERT_TIMEOUT=180
```

The private `local` storage disk must be writable and must not be served directly by the web server. Both source files and `document-previews/` belong on that disk. Do not expose the preview directory through a public storage symlink. Cache and conversion output require disk capacity monitoring.

Run conversion under a restricted service account with resource limits and no outbound network access. Rendering parses uploaded documents; archive validation and malware scanning complement, but do not replace, operating-system isolation and patched converters. Install the intended presentation fonts and test representative course decks for fidelity.

Initial PowerPoint preparation can be slower than later reads. Configure the application/proxy request timeouts to accommodate the bounded preparation timeout, or move preparation to a dedicated worker before a high-volume launch. A file lock makes concurrent preparation return a retryable response. Rendering failures never fall back to releasing the original source.

Before release: rebuild assets, refresh application config, verify the converter executables as the service user, and smoke-test PDF and PPTX uploads, page navigation, denied source downloads and enrollment revocation through an actual learner account.

## Verification

- Focused PHPUnit suite: **20 tests, 208 assertions passed**, including source denial, range denial, download opt-in/revocation, enrollment revocation, author ownership, release locks, public-link rejection and unsafe PPTX archive/relationship rejection.
- Real two-page PDF and two-slide PPTX fixtures converted locally; distinct pages, page bounds and different burned watermarks for different viewers verified.
- Follow-up renderer regression suite: **6 tests, 41 assertions passed**. The converter retains operating-system paths even when the HTTP runtime exposes request-only server variables, while excluding application credentials and HTTP authorization headers.
- Production asset build passed.
- Browser author preview: PDF and PPTX rendered, second-page/slide navigation and fullscreen verified. Evidence: `protected-pdf-reader.png` and `protected-presentation-reader.png`.

### Local preview PDF incident

The reported `/admin/lessons/11/edit` reader at port 8900 returned “The document reader is temporarily unavailable” because its PHP process did not load GD. The page count was available, but the watermark renderer could not run. On 2026-10-05 that local preview was restarted with `-d extension=gd -d extension=zip`, preserving its existing SQLite database, session cookie and application key. The lesson's actual PDF then rendered successfully in browser preview. No production runtime or lesson content was changed for this repair.

The repeatable local launch script is `tmp/jk-enterprise-audit/start-preview-with-documents.ps1` (ignored tooling). Use it when port 8900 is free. It supplies explicit document-converter paths and loads both extensions in the PHP process that serves HTTP. Passing `-d extension=gd` only to `artisan serve` does not pass that option to its separate child web-server process.

The isolated preview uses its own database/media/sessions. LibreOffice was downloaded from the official Document Foundation distribution, verified against its SHA-256 hash and extracted into ignored local preview tooling; the system Office installation and production host were not changed.

Converter references: [LibreOffice command-line parameters](https://help.libreoffice.org/latest/en-US/text/shared/guide/start_parameters.html) and [PDF export options](https://help.libreoffice.org/latest/en-US/text/shared/guide/pdf_params.html).

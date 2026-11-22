# LMS audit: lesson types, drip and PDF/PowerPoint lessons (2026-11-22)

## What was broken

| Area | Problem | Fix |
| --- | --- | --- |
| PDF / PowerPoint lessons | The reader only worked when Poppler (`pdfinfo`, `pdftoppm`), PHP GD and LibreOffice were installed and on the web user's PATH. Without them every document lesson failed, both locally and on the server. | `DOCUMENT_RENDERER=auto` (default): server-rendered watermarked pages when the tools exist, otherwise the learner's browser renders the file (PDF.js for PDFs, a client-side renderer for .pptx). `php artisan documents:check` reports which mode a server uses. |
| Lesson types | Only article, video, audio, PDF, slides and mixed. No quiz, assignment or case study in the curriculum. | New lesson types **Quiz**, **Assignment** and **Case study**, chosen from a "Select lesson type" picker in the curriculum builder. |
| Drip | Release rules existed per lesson but were hidden in the lesson sidebar; no course-level view, no "lock lessons in order". | New **Drip** tab on the course builder (admin and facilitator): per-lesson release (immediately / days after enrollment / date), quick schedules (a module or lesson every N days) and **Lock lessons in order**. New courses open straight on the Curriculum tab. |
| Lesson save | Saving a lesson with no estimated duration crashed (NOT NULL column). | Blank duration keeps the stored/default value. |
| Quiz player | The player lived at the POST-only `/quizzes/{id}/start` URL, so saving an answer or refreshing returned "405 Method Not Allowed" and submissions failed. | Start now redirects to `GET /quiz-attempts/{attempt}`. |
| Curriculum builder | Adding a module/reordering reloaded the page back to the Details tab. | State is preserved; the builder stays on the current tab. |

## How the new lesson types work

- **Quiz**: questions (multiple choice, multiple select, true/false) are written in the lesson editor or reused from the course question bank. Pass mark, time limit, attempts and shuffling are per quiz. The lesson completes when the learner reaches the pass mark. Lesson quizzes follow the lesson's drip and sequence rules.
- **Assignment**: instructions + optional reference PDF; learners submit text and up to 5 files (20 MB each). Lesson completes on submission. Graders use **Review submissions** (inbox icon in the curriculum, or the lesson editor sidebar) to score, give feedback or return work for revision; learners are notified.
- **Case study**: scenario + optional exhibit PDF + a list of questions; learners answer every question; graded like an assignment.
- Course revisions copy lesson quizzes, assignments and case-study questions.

## Deploying to app.jkmichaelspm.com

1. Upload the code, then on the server in the app directory:
   ```bash
   npm ci && npm run build          # new front-end dependencies: pdfjs-dist, pptx-preview
   php artisan migrate --force      # adds lesson activities + course drip columns
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan documents:check
   ```
   If you build locally instead, upload the whole `public/build` folder.
2. Optional but recommended for best PowerPoint fidelity and burned-in watermarks (Ubuntu/Debian):
   ```bash
   sudo apt-get update
   sudo apt-get install -y --no-install-recommends poppler-utils libreoffice-impress \
     fonts-dejavu fonts-liberation fonts-crosextra-carlito fonts-crosextra-caladea
   sudo apt-get install -y php-gd php-zip   # use the versioned packages matching the site's PHP, e.g. php8.2-gd
   sudo systemctl reload php*-fpm
   php artisan documents:check
   ```
   If `.env` sets `DOCUMENT_PDFINFO`, `DOCUMENT_PDFTOPPM` or `DOCUMENT_LIBREOFFICE` to the sandbox wrappers in `ops/`, run `ops/install-document-sandbox.sh` too, or remove those lines so the system binaries are used.
3. Smoke test: open a PDF lesson and a PowerPoint lesson as a learner, take a lesson quiz, submit an assignment and grade it.

## Security notes

- In browser mode the source file is delivered to the signed-in, entitled learner's browser (XHR only, `no-store`, same-origin). Server mode never sends the source. Set `DOCUMENT_RENDERER=server` to forbid browser mode once the converters are installed.
- Submission attachments are stored on the private disk and readable only by the learner and the course's graders; they are malware-scanned when `MEDIA_REQUIRE_SCAN` is on.

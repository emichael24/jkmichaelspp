import DOMPurify from 'dompurify';
import { getDownloadedLessons, getPendingProgress, saveProgressOffline, syncOfflineProgress, getOfflineLesson } from './lib/offlineStore';

const status = document.getElementById('offline-status');
const list = document.getElementById('saved-readings');
const article = document.getElementById('reading');
let selected;
let expiry;

function closeReading() {
    clearTimeout(expiry);
    article.classList.add('hidden');
    document.getElementById('reading-content').replaceChildren();
    document.getElementById('reading-title').textContent = '';
    selected = null;
}

async function refresh() {
    try {
        const lessons = await getDownloadedLessons();
        list.replaceChildren();
        for (const lesson of lessons) {
            const button = document.createElement('button');
            button.className = 'block w-full rounded-xl border border-surface-200 bg-white p-5 text-left font-semibold hover:border-primary-500';
            button.textContent = lesson.title;
            button.addEventListener('click', () => openReading(lesson.lessonId));
            list.append(button);
        }
        const pending = await getPendingProgress();
        status.textContent = lessons.length ? `${lessons.length} saved readings · ${pending.length} completion events awaiting server confirmation.` : 'No saved readings are available in this tab. Sign in online and save a lesson first.';
    } catch (error) { status.textContent = error.message; }
}

async function openReading(id) {
    const lesson = await getOfflineLesson(id);
    if (!lesson) { closeReading(); await refresh(); return; }
    selected = lesson;
    document.getElementById('reading-title').textContent = lesson.title;
    document.getElementById('reading-content').innerHTML = DOMPurify.sanitize(`${lesson.summary || ''}${lesson.text_content || ''}`, {
        USE_PROFILES: { html: true }, FORBID_TAGS: ['img', 'video', 'audio', 'source', 'iframe', 'style', 'form', 'input', 'button', 'textarea', 'select', 'object'], FORBID_ATTR: ['style', 'src', 'srcset'],
    });
    article.classList.remove('hidden');
    document.getElementById('reading-title').focus();
    clearTimeout(expiry);
    expiry = setTimeout(() => { closeReading(); refresh(); }, Math.max(0, lesson.expiresAt - Date.now()));
}

document.getElementById('reading-complete').addEventListener('click', async event => {
    if (!selected) return;
    event.target.disabled = true;
    try { await saveProgressOffline(selected.lessonId); await refresh(); status.textContent += ' Saved on this device. Completion is final after the server accepts it.'; }
    catch (error) { status.textContent = error.message; }
    finally { event.target.disabled = false; }
});
document.getElementById('sync-readings').addEventListener('click', async event => {
    event.target.disabled = true;
    try { const result = await syncOfflineProgress(); await refresh(); status.textContent = `${result.accepted} accepted; ${result.pending} pending. ${result.blocked ? 'Some progress requires renewed lesson access. Reopen the workspace online.' : ''}`; }
    catch (error) { status.textContent = error.response?.data?.message || 'Could not connect. Pending progress is still on this device until its permission expires.'; }
    finally { event.target.disabled = false; }
});
window.addEventListener('offline-data-changed', () => { if (selected) openReading(selected.lessonId).catch(closeReading); });
window.addEventListener('pagehide', () => { closeReading(); list.replaceChildren(); });
window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
refresh();

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { loadPdfjs } from '@/lib/pdfjs';
import { ChevronLeft, ChevronRight, ZoomIn, ZoomOut, Maximize, ShieldCheck, Download, FileText, LoaderCircle, RotateCcw } from 'lucide-vue-next';

const props = defineProps({ manifestUrl: String, title: { type: String, default: 'Lesson document' } });
const root = ref(null), canvas = ref(null), slideHost = ref(null), metadata = ref(null), page = ref(1), pageInput = ref(1), zoom = ref(100), loading = ref(true), error = ref('');
const pages = ref(0);
let manifestRequest, pageRequest, generation = 0, pdfDocument = null, slideViewer = null, renderTask = null;

const browserMode = computed(() => metadata.value?.mode === 'browser');
const slideMode = computed(() => browserMode.value && metadata.value?.format === 'presentation');
const label = computed(() => metadata.value?.format === 'presentation' ? 'Slide' : 'Page');

async function message(exception) {
    if (exception.response?.data instanceof Blob) {
        try { return JSON.parse(await exception.response.data.text()).message; } catch {}
    }
    if (exception.response?.data instanceof ArrayBuffer) {
        try { return JSON.parse(new TextDecoder().decode(exception.response.data)).message; } catch {}
    }
    return exception.response?.data?.message || exception.message || 'Unable to open this document. Check your connection and try again.';
}

function cleanup() {
    renderTask?.cancel?.(); renderTask = null;
    pdfDocument?.destroy?.(); pdfDocument = null;
    try { slideViewer?.destroy?.(); } catch { /* The viewer may already be detached. */ }
    slideViewer = null;
    if (slideHost.value) slideHost.value.innerHTML = '';
}

async function openDocument() {
    const version = ++generation;
    manifestRequest?.abort(); pageRequest?.abort(); cleanup();
    metadata.value = null; error.value = ''; loading.value = true; page.value = 1; pageInput.value = 1; zoom.value = 100; pages.value = 0;
    if (canvas.value) canvas.value.width = 0;
    if (!props.manifestUrl) { error.value = 'Upload this document to enable the protected reader.'; loading.value = false; return; }
    manifestRequest = new AbortController();
    try {
        const { data } = await axios.get(props.manifestUrl, { signal: manifestRequest.signal, timeout: 210000, headers: { Accept: 'application/json' } });
        if (version !== generation) return;
        metadata.value = { mode: 'raster', ...data };
        if (metadata.value.mode === 'browser') {
            await openInBrowser(version);
        } else {
            pages.value = data.pages;
        }
        if (version !== generation) return;
        await nextTick(); await loadPage();
    } catch (exception) {
        if (!axios.isCancel(exception) && version === generation) { error.value = await message(exception); loading.value = false; }
    }
}

/** Fallback when the server cannot render pages: fetch the source once and draw it locally. */
async function openInBrowser(version) {
    const { data } = await axios.get(metadata.value.source_url, {
        responseType: 'arraybuffer', signal: manifestRequest.signal, timeout: 180000,
        headers: { 'X-Document-Reader': '1', Accept: 'application/pdf, application/octet-stream' },
    });
    if (version !== generation) return;
    if (metadata.value.format === 'presentation') {
        const { init } = await import('pptx-preview');
        await nextTick();
        const width = Math.max(320, Math.min(1280, slideHost.value?.clientWidth || 960));
        slideViewer = init(slideHost.value, { width, height: Math.round(width * 9 / 16), mode: 'slide' });
        await slideViewer.load(data);
        pages.value = slideViewer.slideCount || 0;
        if (!pages.value) throw new Error('This presentation has no slides.');
    } else {
        const pdfjs = await loadPdfjs();
        pdfDocument = await pdfjs.getDocument({ data: new Uint8Array(data), isEvalSupported: false, enableXfa: false }).promise;
        pages.value = pdfDocument.numPages;
    }
}

function stampWatermark(context, width, height) {
    const mark = metadata.value?.watermark;
    if (!mark) return;
    context.save();
    context.fillStyle = 'rgba(90, 105, 125, 0.28)';
    context.font = `${Math.max(14, Math.round(width / 70))}px sans-serif`;
    context.textAlign = 'center';
    for (let y = 90; y < height; y += 270) context.fillText(mark, width / 2, y);
    context.fillStyle = 'rgba(35, 45, 65, 0.85)';
    context.textAlign = 'left';
    context.font = `${Math.max(11, Math.round(width / 110))}px sans-serif`;
    context.fillText(`${mark} | ${page.value}/${pages.value}`, 14, height - 12);
    context.restore();
}

async function loadPage() {
    pageRequest?.abort();
    const request = new AbortController(); pageRequest = request;
    error.value = ''; loading.value = true;
    try {
        if (slideMode.value) {
            slideViewer.renderSingleSlide(page.value - 1);
            return;
        }
        if (browserMode.value) {
            renderTask?.cancel?.();
            const pdfPage = await pdfDocument.getPage(page.value);
            if (request.signal.aborted || !canvas.value) return;
            const base = pdfPage.getViewport({ scale: 1 });
            const viewport = pdfPage.getViewport({ scale: Math.min(3, 1600 / Math.max(base.width, 1)) });
            canvas.value.width = Math.floor(viewport.width); canvas.value.height = Math.floor(viewport.height);
            const context = canvas.value.getContext('2d');
            renderTask = pdfPage.render({ canvas: canvas.value, canvasContext: context, viewport });
            await renderTask.promise;
            renderTask = null;
            stampWatermark(context, canvas.value.width, canvas.value.height);
            return;
        }
        if (canvas.value) canvas.value.width = 0;
        const { data } = await axios.get(`${metadata.value.page_url}/${page.value}`, { responseType: 'blob', signal: request.signal, timeout: 120000, headers: { Accept: 'application/json' } });
        if (request.signal.aborted) return;
        const bitmap = await createImageBitmap(data);
        try {
            if (request.signal.aborted || !canvas.value) return;
            canvas.value.width = bitmap.width; canvas.value.height = bitmap.height;
            canvas.value.getContext('2d').drawImage(bitmap, 0, 0);
        } finally { bitmap.close(); }
    } catch (exception) {
        if (exception?.name === 'RenderingCancelledException') return;
        if (!axios.isCancel(exception) && !request.signal.aborted) error.value = await message(exception);
    } finally { if (!request.signal.aborted) loading.value = false; }
}
function goTo(value) {
    const target = Math.max(1, Math.min(pages.value || 1, Math.trunc(Number(value)) || 1));
    pageInput.value = target;
    if (target !== page.value) { page.value = target; loadPage(); }
}
function keyboard(event) {
    if ((event.ctrlKey || event.metaKey) && ['s', 'p'].includes(event.key.toLowerCase())) { event.preventDefault(); event.stopPropagation(); return; }
    if (event.target.matches('input,select,textarea')) return;
    if (event.key === 'ArrowRight' || event.key === 'PageDown') { event.preventDefault(); goTo(page.value + 1); }
    if (event.key === 'ArrowLeft' || event.key === 'PageUp') { event.preventDefault(); goTo(page.value - 1); }
}
async function fullscreen() {
    try { if (document.fullscreenElement) await document.exitFullscreen(); else await root.value?.requestFullscreen(); }
    catch { /* Keep the inline reader usable when fullscreen is unavailable. */ }
}
watch(() => props.manifestUrl, openDocument, { immediate: true });
onBeforeUnmount(() => { generation++; manifestRequest?.abort(); pageRequest?.abort(); cleanup(); });
</script>

<template>
    <section ref="root" tabindex="0" :aria-label="`${title} protected reader`" class="document-reader overflow-hidden rounded-2xl border border-surface-200 bg-surface-100 outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @keydown="keyboard" @contextmenu.prevent @dragstart.prevent>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-200 bg-white px-4 py-3">
            <div class="min-w-0"><h3 class="flex items-center gap-2 text-sm font-semibold text-surface-900"><FileText class="h-4 w-4 shrink-0 text-primary-600" /><span class="truncate">{{ title }}</span></h3><p class="mt-1 flex items-center gap-1.5 text-[11px] text-surface-500"><ShieldCheck class="h-3.5 w-3.5" />Protected reader · personalized watermark</p></div>
            <a v-if="metadata?.download_url" :href="metadata.download_url" class="inline-flex items-center gap-2 rounded-lg border border-surface-200 px-3 py-2 text-xs font-semibold hover:bg-surface-50"><Download class="h-3.5 w-3.5" />Download source</a>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-surface-200 bg-white px-3 py-2">
            <div class="flex items-center gap-1.5"><button type="button" aria-label="Previous page" :disabled="!pages || page <= 1" class="reader-button" @click="goTo(page - 1)"><ChevronLeft class="h-4 w-4" /></button><label class="flex items-center gap-2 text-xs text-surface-600">{{ label }}<input v-model="pageInput" aria-label="Page number" type="number" min="1" :max="pages || 1" :disabled="!pages" class="w-14 rounded-lg border border-surface-200 bg-white px-2 py-1.5 text-center" @change="goTo(pageInput)" @keydown.enter="goTo(pageInput)" /><span>of {{ pages || '—' }}</span></label><button type="button" aria-label="Next page" :disabled="!pages || page >= pages" class="reader-button" @click="goTo(page + 1)"><ChevronRight class="h-4 w-4" /></button></div>
            <div class="flex items-center gap-1"><template v-if="!slideMode"><button type="button" aria-label="Zoom out" :disabled="zoom <= 50" class="reader-button" @click="zoom = Math.max(50, zoom - 25)"><ZoomOut class="h-4 w-4" /></button><button type="button" aria-label="Fit page to width" class="rounded-lg px-2 py-2 text-xs font-medium hover:bg-surface-100" @click="zoom = 100">{{ zoom === 100 ? 'Fit width' : `${zoom}%` }}</button><button type="button" aria-label="Zoom in" :disabled="zoom >= 200" class="reader-button" @click="zoom = Math.min(200, zoom + 25)"><ZoomIn class="h-4 w-4" /></button></template><button type="button" aria-label="Fullscreen reader" class="reader-button" @click="fullscreen"><Maximize class="h-4 w-4" /></button></div>
        </div>
        <div class="reader-pages relative max-h-[75vh] min-h-64 overflow-auto p-3 sm:p-6" :aria-busy="loading">
            <div v-if="loading" role="status" class="grid min-h-56 place-content-center justify-items-center gap-3 text-sm text-surface-600"><LoaderCircle class="h-6 w-6 animate-spin text-primary-600" /><p>{{ pages ? `Loading ${label.toLowerCase()} ${page}…` : 'Preparing your document…' }}</p><p v-if="!pages" class="text-xs text-surface-400">Large presentations may take a moment.</p></div>
            <div v-if="error" role="alert" class="mx-auto my-10 max-w-sm text-center"><p class="text-sm leading-6 text-surface-600">{{ error }}</p><button type="button" class="mx-auto mt-4 flex items-center gap-2 rounded-lg bg-surface-900 px-4 py-2 text-sm text-white" @click="pages ? loadPage() : openDocument()"><RotateCcw class="h-4 w-4" />Try again</button></div>
            <div v-show="!loading && !error && !slideMode" class="mx-auto" :style="{ width: `${zoom}%` }"><canvas ref="canvas" role="img" :aria-label="`${title}, ${label.toLowerCase()} ${page} of ${pages || 0}. For an accessible text version, refer to the lesson notes or contact your facilitator.`" class="block h-auto w-full select-none bg-white shadow-md" /></div>
            <div v-show="slideMode && !error" class="slide-stage relative mx-auto select-none" role="img" :aria-label="`${title}, slide ${page} of ${pages || 0}`">
                <div ref="slideHost" class="slide-host w-full" />
                <div v-if="slideMode && metadata?.watermark" class="slide-watermark" aria-hidden="true"><span v-for="n in 3" :key="n">{{ metadata.watermark }}</span><span class="slide-watermark-footer">{{ metadata.watermark }} | {{ page }}/{{ pages }}</span></div>
            </div>
        </div>
        <p class="border-t border-surface-200 bg-white px-4 py-3 text-[11px] text-surface-500">{{ metadata?.download_url ? 'The author has enabled source downloads.' : 'Read in this lesson. Source download and printing are disabled.' }} <span class="hidden sm:inline">Use ← → to navigate.</span></p>
        <p class="print-notice">This protected lesson document is available only in the online reader.</p>
    </section>
</template>

<style scoped>
.reader-button { display: inline-flex; align-items: center; justify-content: center; padding: .5rem; border-radius: .5rem; color: #475569; }
.reader-button:hover { background: #f1f5f9; }
.reader-button:disabled { opacity: .35; cursor: not-allowed; }
.reader-button:focus-visible { outline: 2px solid #c2410c; outline-offset: 2px; }
.document-reader:fullscreen { border: 0; border-radius: 0; display: flex; flex-direction: column; }
.document-reader:fullscreen .reader-pages { max-height: none; flex: 1; }
.slide-host :deep(.pptx-preview-wrapper) { max-width: 100%; background: #fff !important; box-shadow: 0 4px 12px rgb(15 23 42 / .12); }
.slide-host :deep(.pptx-preview-wrapper-next),
.slide-host :deep(.pptx-preview-wrapper-pagination) { display: none !important; }
.slide-watermark { position: absolute; inset: 0; pointer-events: none; display: flex; flex-direction: column; justify-content: space-around; align-items: center; color: rgb(90 105 125 / .28); font-size: clamp(11px, 1.4vw, 16px); font-weight: 600; z-index: 200; }
.slide-watermark-footer { position: absolute; left: 12px; bottom: 6px; color: rgb(35 45 65 / .8); font-size: 11px; font-weight: 500; }
.print-notice { display: none; }
@media print { .document-reader > * { display: none !important; } .document-reader > .print-notice { display: block !important; } }
</style>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { UploadCloud, FileCheck2, RotateCcw, X, LoaderCircle, Link2 } from 'lucide-vue-next';

const props = defineProps({ type: { type: String, required: true }, courseId: [Number, String], modelValue: String, disabled: Boolean, allowLink: { type: Boolean, default: true } });
const emit = defineEmits(['update:modelValue', 'uploaded', 'busy']);
const input = ref(null), file = ref(null), progress = ref(0), busy = ref(false), error = ref(''), dragging = ref(false), linkMode = ref(false), link = ref('');
let controller, uploadId, nextChunk = 0;
const formats = {
    video: { accept: '.mp4,.mov,.webm', label: 'MP4, MOV or WebM', max: 500 },
    audio: { accept: '.mp3,.m4a,.ogg,.wav', label: 'MP3, M4A, OGG or WAV', max: 50 },
    pdf: { accept: '.pdf', label: 'PDF document', max: 20 },
    slides: { accept: '.pdf,.pptx', label: 'PDF or PowerPoint (.pptx)', max: 20 },
    image: { accept: '.jpg,.jpeg,.png,.webp', label: 'JPG, PNG or WebP', max: 8 },
    scorm: { accept: '.zip', label: 'SCORM 1.2 or 2004 ZIP · one SCO', max: 100 },
};
const format = computed(() => formats[props.type]);
const size = computed(() => file.value ? `${(file.value.size / 1024 / 1024).toFixed(1)} MB` : '');
function setBusy(value) { busy.value = value; emit('busy', value); }
function selectFile(selected) {
    if (props.disabled || busy.value || !selected) return;
    error.value = '';
    if (!selected.size || selected.size > format.value.max * 1024 * 1024) { error.value = `Choose a non-empty file up to ${format.value.max} MB.`; return; }
    if (!format.value.accept.split(',').some(ext => selected.name.toLowerCase().endsWith(ext))) { error.value = `Choose ${format.value.label}.`; return; }
    file.value = selected;
    uploadId = `${crypto.randomUUID()}.${selected.name.split('.').pop()}`;
    nextChunk = 0;
    upload();
}
async function upload() {
    if (!file.value || busy.value) return;
    controller = new AbortController(); error.value = ''; setBusy(true);
    const chunkSize = 5 * 1024 * 1024;
    const count = props.type === 'video' ? Math.ceil(file.value.size / chunkSize) : 1;
    try {
        let result;
        for (let i = nextChunk; i < count; i++) {
            const body = new FormData();
            body.append('course_id', props.courseId);
            body.append('file', props.type === 'video' ? file.value.slice(i * chunkSize, (i + 1) * chunkSize) : file.value, file.value.name);
            if (props.type === 'video') { body.append('fileName', uploadId); body.append('chunkIndex', i); body.append('totalChunks', count); }
            const response = await axios.post(`/authoring/media/${props.type}`, body, {
                signal: controller.signal,
                onUploadProgress: event => { progress.value = Math.min(99, Math.round(((i + (event.total ? event.loaded / event.total : 0)) / count) * 100)); },
            });
            result = response.data;
            // Keep the final chunk retryable until the server confirms assembly.
            nextChunk = Math.min(i + 1, count - 1);
        }
        const url = result?.url || result?.video_url || result?.scorm_path;
        if (result?.status !== 'success' || !url) throw new Error('The server did not confirm the upload. Please retry.');
        progress.value = 100;
        emit('update:modelValue', url); emit('uploaded', { ...result, url, name: file.value.name });
    } catch (exception) {
        error.value = axios.isCancel(exception) ? 'Upload paused. Retry to continue.' : Object.values(exception.response?.data?.errors || {}).flat()[0] || exception.response?.data?.message || exception.message || 'Upload failed. Please retry.';
    } finally { setBusy(false); }
}
function useLink() {
    try { const url = new URL(link.value); if (!['https:', 'http:'].includes(url.protocol)) throw new Error(); emit('update:modelValue', url.href); error.value = ''; linkMode.value = false; }
    catch { error.value = 'Enter a complete HTTPS or HTTP URL.'; }
}
onBeforeUnmount(() => { controller?.abort(); emit('busy', false); });
</script>

<template>
    <div class="space-y-3">
        <div v-if="modelValue && !busy && !error" class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <FileCheck2 class="h-6 w-6 shrink-0 text-emerald-600" aria-hidden="true" />
            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-emerald-950">{{ file?.name || 'Media attached' }}</p><p class="mt-1 text-xs text-emerald-700">{{ size || 'Ready for this lesson' }}</p></div>
            <button type="button" :disabled="disabled" @click="emit('update:modelValue', ''); file = null; progress = 0" aria-label="Remove media from lesson" class="rounded-lg p-2 text-emerald-800 hover:bg-emerald-100 disabled:opacity-50"><X class="h-4 w-4" /></button>
        </div>
        <div v-else class="rounded-2xl border-2 border-dashed p-6 transition-colors" :class="dragging ? 'border-primary-600 bg-primary-50' : 'border-surface-200 bg-surface-50/60'" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; selectFile($event.dataTransfer.files[0])">
            <input ref="input" type="file" :accept="format.accept" class="sr-only" tabindex="-1" :disabled="disabled || busy" @change="selectFile($event.target.files[0]); $event.target.value = ''" />
            <div v-if="busy" class="space-y-3" role="status" aria-live="polite">
                <div class="flex items-center gap-3"><LoaderCircle class="h-5 w-5 animate-spin text-primary-600" /><span class="min-w-0 flex-1 truncate text-sm font-medium">{{ progress >= 99 ? 'Checking and securing your file…' : `Uploading ${file.name}` }}</span><button type="button" @click="controller?.abort()" class="text-xs font-semibold text-surface-600">Cancel</button></div>
                <div role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="Upload progress" class="h-2 overflow-hidden rounded-full bg-surface-200"><div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${progress}%` }" /></div>
                <p class="text-xs text-surface-500">{{ progress }}% · {{ size }} · Keep this page open until the file is ready.</p>
            </div>
            <div v-else class="text-center"><div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl border border-surface-200 bg-white"><UploadCloud class="h-6 w-6 text-primary-600" /></div><button type="button" :disabled="disabled" @click="input.click()" class="rounded-lg px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-600 disabled:opacity-50">Choose {{ type === 'image' || type === 'audio' ? 'an' : 'a' }} {{ type === 'scorm' ? 'SCORM package' : `${type} file` }}</button><p class="mt-1 text-xs text-surface-500">or drag and drop here</p><p class="mt-3 text-xs text-surface-500">{{ format.label }} · up to {{ format.max }} MB</p></div>
        </div>
        <div v-if="error" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><p>{{ error }}</p><button v-if="file" type="button" @click="upload" :disabled="busy || disabled" class="mt-2 inline-flex items-center gap-2 font-semibold"><RotateCcw class="h-4 w-4" />Retry upload</button></div>
        <button v-if="allowLink && !busy && !modelValue && type !== 'scorm'" type="button" :disabled="disabled" @click="linkMode = !linkMode" class="inline-flex items-center gap-2 text-xs font-medium text-surface-600"><Link2 class="h-3.5 w-3.5" />{{ linkMode ? 'Close link entry' : 'Use an existing media link' }}</button>
        <div v-if="linkMode" class="flex flex-wrap gap-2"><input v-model="link" :aria-label="`${type} URL`" type="url" placeholder="https://…" class="min-w-0 flex-1 rounded-xl border border-surface-300 px-3 py-2 text-sm" @keydown.enter.prevent="useLink" /><button type="button" @click="useLink" class="rounded-xl bg-surface-900 px-4 py-2 text-sm font-medium text-white">Attach link</button></div>
    </div>
</template>

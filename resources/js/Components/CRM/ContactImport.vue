<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Download, Upload } from 'lucide-vue-next';
import Modal from '@/Components/UI/Modal.vue';
import Button from '@/Components/UI/Button.vue';
import { contactTypes, titleCase } from './contactFields';
const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);
const file = ref(null), fileInput = ref(null), type = ref('lead'), errors = ref([]);
const imports = ref([]), maxMb = ref(50), uploading = ref(false), uploadPercent = ref(0), loading = ref(false), statusError = ref(''), acting = ref(null);
let timer, requestId = null;
const isActive = batch => ['queued', 'processing'].includes(batch.status);
const statusLabel = status => ({ queued: 'Queued', processing: 'Processing', completed: 'Completed', completed_with_errors: 'Completed with skipped rows', failed: 'Needs attention', cancelled: 'Cancelled' }[status] || status);
function resetRequest() { requestId = null; }
function chooseFile(event) { file.value = event.target.files[0] || null; resetRequest(); errors.value = []; }
function close() { if (!uploading.value) { emit('close'); router.reload({ only: ['contacts'] }); } }
function messages(error) {
    if (error.response?.status === 413) return ['The server rejected the file size. The server upload limit must allow 50 MB files.'];
    if (error.response?.data?.errors) return Object.values(error.response.data.errors).flat();
    return ['The request could not finish. Check your connection and sign-in, then retry. Your existing imports are preserved.'];
}
async function load() {
    if (loading.value || !props.show) return;
    clearTimeout(timer); loading.value = true;
    try { const { data } = await axios.get('/admin/erp/contacts/imports'); imports.value = data.imports; maxMb.value = data.max_file_mb; statusError.value = ''; }
    catch { statusError.value = 'Status could not refresh. Background processing continues; retry when your connection returns.'; }
    finally { loading.value = false; if (props.show && (imports.value.some(isActive) || statusError.value)) timer = setTimeout(load, statusError.value ? 10000 : 3000); }
}
async function upload() {
    if (uploading.value || !file.value) return;
    errors.value = [];
    if (file.value.size > maxMb.value * 1024 * 1024) { errors.value = [`Choose a CSV no larger than ${maxMb.value} MB.`]; return; }
    requestId ||= crypto.randomUUID();
    const data = new FormData(); data.append('file', file.value); data.append('type', type.value); data.append('request_id', requestId);
    uploading.value = true; uploadPercent.value = 0;
    try {
        await axios.post('/admin/erp/contacts/imports', data, { onUploadProgress: event => { uploadPercent.value = Math.min(100, Math.round(event.loaded * 100 / (event.total || file.value.size))); } });
        file.value = null; if (fileInput.value) fileInput.value.value = ''; resetRequest(); await load();
    } catch (error) { errors.value = messages(error); }
    finally { uploading.value = false; }
}
async function act(batch, action) {
    if (acting.value) return;
    acting.value = batch.id; errors.value = [];
    try { await axios.post(`/admin/erp/contacts/imports/${batch.id}/${action}`); await load(); }
    catch (error) { errors.value = messages(error); }
    finally { acting.value = null; }
}
watch(() => props.show, value => { clearTimeout(timer); if (value) { errors.value = []; load(); } });
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Modal :show="show" size="xl" title="Import contacts" subtitle="Upload a CSV and keep working while your contacts are processed." :closeable="!uploading" @close="close">
        <div class="space-y-6">
            <div v-if="errors.length" role="alert" class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800"><ul class="list-inside list-disc"><li v-for="error in errors" :key="error">{{ error }}</li></ul></div>
            <form @submit.prevent="upload" class="space-y-4">
                <div class="rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm text-surface-700">
                    <p class="font-semibold text-surface-900">Large files, processed in the background</p>
                    <p class="mt-1">CSV files up to {{ maxMb }} MB, with no fixed contact-count limit. Valid rows are imported; duplicate emails and invalid rows are skipped and included in an error report.</p>
                    <a href="/admin/erp/contacts/import/template" class="mt-3 inline-flex items-center gap-2 font-semibold text-primary-700 underline underline-offset-4"><Download class="h-4 w-4" aria-hidden="true" /> Download CSV template</a>
                </div>
                <div>
                    <label for="contact-import-type" class="mb-1.5 block text-sm font-medium text-surface-700">Default contact type</label>
                    <select id="contact-import-type" v-model="type" :disabled="uploading" @change="resetRequest" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm" aria-describedby="import-type-hint"><option v-for="value in contactTypes" :key="value" :value="value">{{ titleCase(value) }}</option></select>
                    <p id="import-type-hint" class="mt-1.5 text-sm text-surface-500">Used when the CSV type column is blank. Fill in each row's type to import a mix of contacts.</p>
                </div>
                <div>
                    <label for="contact-import-file" class="mb-1.5 block text-sm font-medium text-surface-700">CSV file</label>
                    <input ref="fileInput" id="contact-import-file" type="file" accept=".csv,text/csv,text/plain" required :disabled="uploading" @change="chooseFile" class="block w-full rounded-xl border border-dashed border-surface-300 p-4 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-primary-700" aria-describedby="import-file-hint" />
                    <p id="import-file-hint" class="mt-1.5 text-sm text-surface-500">UTF-8 CSV. First name, last name and email are required. Importing does not send messages or enroll contacts.</p>
                </div>
                <div v-if="uploading" role="status" class="space-y-2 text-sm text-surface-600"><p>{{ uploadPercent < 100 ? `Uploading… ${uploadPercent}%` : 'Upload complete. Adding to the import queue…' }}</p><progress :value="uploadPercent" max="100" aria-label="File upload progress" class="h-2 w-full accent-primary-600" /></div>
                <div class="flex justify-end"><Button type="submit" :disabled="uploading || !file"><Upload class="mr-2 h-4 w-4" aria-hidden="true" />{{ uploading ? 'Uploading…' : 'Start background import' }}</Button></div>
            </form>
            <section aria-labelledby="recent-imports-heading" class="border-t border-surface-200 pt-5">
                <div class="flex items-center justify-between gap-3"><h3 id="recent-imports-heading" class="font-semibold text-surface-900">Your recent imports</h3><button type="button" @click="load" :disabled="loading" class="min-h-11 rounded-lg px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 disabled:opacity-50">Refresh status</button></div>
                <p v-if="statusError" role="alert" class="mb-3 text-sm text-danger-700">{{ statusError }}</p>
                <p v-if="!imports.length" class="py-4 text-sm text-surface-500">{{ loading ? 'Loading imports…' : 'Your imports and results will appear here.' }}</p>
                <div class="space-y-3">
                    <article v-for="batch in imports" :key="batch.id" class="rounded-xl border border-surface-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2"><p class="min-w-0 break-all text-sm font-semibold text-surface-900">{{ batch.filename }}</p><span class="rounded-full bg-surface-100 px-2.5 py-1 text-xs font-medium text-surface-700">{{ statusLabel(batch.status) }}</span></div>
                        <p class="mt-1 text-xs text-surface-500">{{ new Date(batch.created_at).toLocaleString() }} · Default: {{ titleCase(batch.default_type) }}</p>
                        <progress v-if="isActive(batch)" :value="batch.progress" max="100" :aria-label="`${batch.filename} processing progress`" class="mt-3 h-2 w-full accent-primary-600" />
                        <p class="mt-3 text-sm text-surface-700" role="status">{{ batch.imported_count.toLocaleString() }} imported · {{ batch.skipped_count.toLocaleString() }} duplicates · {{ batch.invalid_count.toLocaleString() }} invalid</p>
                        <p v-if="isActive(batch)" class="mt-1 text-xs text-surface-500">{{ batch.status === 'queued' ? 'Waiting for a background worker.' : `${batch.processed_count.toLocaleString()} records checked · ${batch.progress}% of file processed.` }} You can close this window; processing continues.</p>
                        <p v-if="batch.error_message" class="mt-2 text-sm text-danger-700">{{ batch.error_message }}</p>
                        <p v-if="batch.status === 'cancelled'" class="mt-2 text-sm text-surface-600">Stopped. Contacts already imported have been kept.</p>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <a v-if="batch.skipped_count + batch.invalid_count > 0" :href="`/admin/erp/contacts/imports/${batch.id}/errors`" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary-700 underline underline-offset-4"><Download class="h-4 w-4" aria-hidden="true" /> Download error report</a>
                            <button v-if="batch.status === 'failed'" type="button" :disabled="acting === batch.id" @click="act(batch, 'retry')" class="min-h-11 text-sm font-semibold text-primary-700 disabled:opacity-50">Resume import</button>
                            <button v-if="isActive(batch) || batch.status === 'failed'" type="button" :disabled="acting === batch.id" @click="act(batch, 'cancel')" class="min-h-11 text-sm font-medium text-surface-600 underline disabled:opacity-50">Cancel remaining rows</button>
                        </div>
                    </article>
                </div>
            </section>
            <div class="flex justify-end"><Button variant="ghost" :disabled="uploading" @click="close">Close</Button></div>
        </div>
    </Modal>
</template>

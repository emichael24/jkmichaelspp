<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import RichTextEditor from '@/Components/Authoring/RichTextEditor.vue';
import { LayoutTemplate, Send, Palette, LoaderCircle } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: String, default: '' },
    subject: { type: String, default: '' },
    error: String,
    disabled: Boolean,
    showTemplates: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue', 'apply-template']);

const preview = ref(false), previewHtml = ref(''), previewBusy = ref(false);
const templates = ref([]), templateId = ref('');
const testBusy = ref(false), testMessage = ref(''), testError = ref('');
const categoryLabels = { general: 'General', newsletter: 'Newsletter', announcement: 'Announcement', event: 'Event', follow_up: 'Follow-up', welcome: 'Welcome', reminder: 'Reminder' };

onMounted(async () => {
    if (!props.showTemplates) return;
    try { templates.value = (await axios.get('/admin/erp/email-templates')).data.templates || []; } catch { templates.value = []; }
});

function applyTemplate() {
    const template = templates.value.find(item => String(item.id) === String(templateId.value));
    templateId.value = '';
    if (!template) return;
    const hasContent = (props.modelValue || '').replace(/<[^>]*>/g, '').trim() !== '';
    if (hasContent && !window.confirm(`Replace your current message with the “${template.name}” template?`)) return;
    emit('update:modelValue', template.body);
    emit('apply-template', template);
}

let previewVersion = 0;
async function loadPreview() {
    const version = ++previewVersion;
    previewBusy.value = true;
    try {
        const { data } = await axios.post('/admin/erp/email-design/preview', { body: props.modelValue });
        if (version === previewVersion) previewHtml.value = data.html;
    } catch {
        if (version === previewVersion) previewHtml.value = '<p style="font-family:Arial;padding:24px;color:#b91c1c">Preview could not be loaded. Check your connection.</p>';
    } finally { if (version === previewVersion) previewBusy.value = false; }
}
watch(preview, open => { if (open) loadPreview(); });

async function sendTest() {
    testBusy.value = true; testMessage.value = ''; testError.value = '';
    try {
        const { data } = await axios.post('/admin/erp/email-design/test', { subject: props.subject || 'Test message', body: props.modelValue });
        testMessage.value = data.message;
    } catch (exception) {
        testError.value = Object.values(exception.response?.data?.errors || {}).flat()[0] || exception.response?.data?.message || 'The test email could not be sent.';
    } finally { testBusy.value = false; }
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm font-medium text-surface-800">Email message</span>
            <div class="flex flex-wrap items-center gap-2">
                <label v-if="showTemplates && templates.length" class="relative">
                    <span class="sr-only">Start from a template</span>
                    <LayoutTemplate class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-surface-500" />
                    <select v-model="templateId" :disabled="disabled" class="rounded-lg border-surface-200 py-1.5 pl-8 pr-8 text-xs font-semibold" @change="applyTemplate">
                        <option value="">Use a template…</option>
                        <optgroup v-for="(label, category) in categoryLabels" :key="category" :label="label">
                            <option v-for="template in templates.filter(item => item.category === category)" :key="template.id" :value="template.id">{{ template.name }}</option>
                        </optgroup>
                    </select>
                </label>
                <div class="flex rounded-lg bg-surface-100 p-1" role="group" aria-label="Email editor mode">
                    <button type="button" :aria-pressed="!preview" @click="preview = false" :class="['rounded-md px-3 py-1.5 text-xs font-semibold', !preview ? 'bg-white shadow-sm' : 'text-surface-500']">Write</button>
                    <button type="button" :aria-pressed="preview" @click="preview = true" :class="['rounded-md px-3 py-1.5 text-xs font-semibold', preview ? 'bg-white shadow-sm' : 'text-surface-500']">Preview</button>
                </div>
            </div>
        </div>
        <RichTextEditor v-show="!preview" :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" label="Email message" placeholder="Write your email. Add headings, format text and include useful links…" compact email-mode :disabled="disabled" />
        <div v-if="preview" class="relative overflow-hidden rounded-xl border border-surface-200 bg-surface-100">
            <div v-if="previewBusy" class="absolute inset-x-0 top-0 flex items-center gap-2 bg-white/80 px-4 py-2 text-xs text-surface-600"><LoaderCircle class="h-3.5 w-3.5 animate-spin" />Rendering with your branding…</div>
            <iframe :srcdoc="previewHtml" title="Branded email preview" sandbox="" class="block h-[560px] w-full bg-white" />
        </div>
        <p v-if="error" role="alert" class="text-sm text-danger-700">{{ error }}</p>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs leading-5 text-surface-500">Personalise with <code v-pre class="rounded bg-surface-100 px-1">{{first_name}}</code>. Your logo, colours and footer are added automatically. <a href="/admin/erp/email-design" target="_blank" class="inline-flex items-center gap-1 font-semibold text-primary-700"><Palette class="h-3.5 w-3.5" />Email design</a></p>
            <button type="button" :disabled="disabled || testBusy || !modelValue" class="inline-flex items-center gap-2 rounded-lg border border-surface-200 px-3 py-1.5 text-xs font-semibold text-surface-700 hover:bg-surface-50 disabled:opacity-40" @click="sendTest"><Send class="h-3.5 w-3.5" />{{ testBusy ? 'Sending…' : 'Send me a test' }}</button>
        </div>
        <p v-if="testMessage" role="status" class="text-xs text-emerald-700">{{ testMessage }}</p>
        <p v-if="testError" role="alert" class="text-xs text-danger-700">{{ testError }}</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import RichTextEditor from '@/Components/Authoring/RichTextEditor.vue';
import SafeHtml from '@/Components/SafeHtml.vue';
defineProps({ modelValue: { type: String, default: '' }, error: String, disabled: Boolean });
defineEmits(['update:modelValue']);
const preview = ref(false);
</script>
<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-3"><span class="text-sm font-medium text-surface-800">Email message</span><div class="flex rounded-lg bg-surface-100 p-1" role="group" aria-label="Email editor mode"><button type="button" :aria-pressed="!preview" @click="preview = false" :class="['rounded-md px-3 py-1.5 text-xs font-semibold', !preview ? 'bg-white shadow-sm' : 'text-surface-500']">Write</button><button type="button" :aria-pressed="preview" @click="preview = true" :class="['rounded-md px-3 py-1.5 text-xs font-semibold', preview ? 'bg-white shadow-sm' : 'text-surface-500']">Preview</button></div></div>
        <RichTextEditor v-show="!preview" :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" label="Email message" placeholder="Write your email. Add headings, format text and include useful links…" compact email-mode :disabled="disabled" />
        <div v-if="preview" class="min-h-64 rounded-xl border border-surface-200 bg-surface-50 p-4 sm:p-6"><div class="rounded-lg bg-white p-5 sm:p-8"><p class="mb-5 text-xs font-bold tracking-widest text-primary-700">JK MICHAELS</p><SafeHtml v-if="modelValue" :content="modelValue" /><p v-else class="text-sm text-surface-400">Your email preview will appear here.</p><p class="mt-7 border-t border-surface-100 pt-4 text-xs text-surface-500">JK Michaels · Manage your message preferences</p></div></div>
        <p v-if="error" role="alert" class="text-sm text-danger-700">{{ error }}</p>
        <p class="text-xs text-surface-500">Formatting is preserved in email. A plain-text version is included for accessibility.</p>
    </div>
</template>

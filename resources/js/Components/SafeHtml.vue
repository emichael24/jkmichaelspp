<script setup>
import { computed } from 'vue';
import DOMPurify from 'dompurify';
const props = defineProps({ content: { type: String, default: '' } });
const html = computed(() => DOMPurify.sanitize(props.content || '', {
    USE_PROFILES: { html: true },
    FORBID_TAGS: ['style', 'form', 'input', 'button', 'textarea', 'select'],
    FORBID_ATTR: ['style'],
}));
</script>
<template><div class="lesson-prose" v-html="html" /></template>

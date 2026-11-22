<script setup>
import DocumentReader from './DocumentReader.vue';
defineProps({ pdfUrl: String, slidesUrl: String, isDownloadable: Boolean, lessonId: { type: Number, required: true } });
</script>
<template>
    <div class="space-y-6">
        <DocumentReader v-if="pdfUrl" :manifest-url="`/lessons/${lessonId}/documents/pdf`" title="Lesson PDF" />
        <DocumentReader v-if="slidesUrl" :manifest-url="`/lessons/${lessonId}/documents/slides`" title="Lesson presentation" />
        <p v-if="!pdfUrl && !slidesUrl" class="py-12 text-center text-sm text-surface-500">No document is attached to this lesson.</p>
    </div>
</template>

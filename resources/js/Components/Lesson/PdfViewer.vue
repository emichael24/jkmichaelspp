<template>
    <div class="space-y-4">
        <!-- PDF viewer -->
        <div v-if="pdfUrl" class="rounded-xl border border-surface-200 overflow-hidden bg-surface-100">
            <div class="flex items-center justify-between px-4 py-3 bg-white border-b border-surface-100">
                <h3 class="font-semibold text-surface-900 text-sm flex items-center gap-2">
                    <FileText class="w-4 h-4 text-danger-500" />
                    PDF Document
                </h3>
                <div class="flex items-center gap-2">
                    <a
                        v-if="isDownloadable"
                        :href="`/lessons/${lessonId}/download/pdf`"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 text-xs font-medium transition-colors"
                    >
                        <Download class="w-3.5 h-3.5" />
                        Download
                    </a>
                    <a
                        :href="pdfUrl"
                        target="_blank"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-50 text-primary-600 hover:bg-primary-100 text-xs font-medium transition-colors"
                    >
                        <ExternalLink class="w-3.5 h-3.5" />
                        Open in new tab
                    </a>
                </div>
            </div>
            <iframe
                :src="pdfUrl"
                class="w-full border-0"
                style="height: 70vh; min-height: 500px;"
                title="PDF Viewer"
            />
        </div>

        <!-- Slides viewer -->
        <div v-if="slidesUrl" class="rounded-xl border border-surface-200 overflow-hidden bg-surface-100">
            <div class="flex items-center justify-between px-4 py-3 bg-white border-b border-surface-100">
                <h3 class="font-semibold text-surface-900 text-sm flex items-center gap-2">
                    <Presentation class="w-4 h-4 text-accent-500" />
                    Slides
                </h3>
                <div class="flex items-center gap-2">
                    <a
                        v-if="isDownloadable"
                        :href="`/lessons/${lessonId}/download/slides`"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 text-xs font-medium transition-colors"
                    >
                        <Download class="w-3.5 h-3.5" />
                        Download
                    </a>
                    <a
                        :href="slidesUrl"
                        target="_blank"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-50 text-primary-600 hover:bg-primary-100 text-xs font-medium transition-colors"
                    >
                        <ExternalLink class="w-3.5 h-3.5" />
                        Open in new tab
                    </a>
                </div>
            </div>
            <iframe
                :src="slidesUrl"
                class="w-full border-0"
                style="height: 70vh; min-height: 500px;"
                title="Slides Viewer"
            />
        </div>

        <!-- Empty state -->
        <div v-if="!pdfUrl && !slidesUrl" class="text-center py-12">
            <FileText class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500 text-sm">No PDF or slides available for this lesson.</p>
        </div>
    </div>
</template>

<script setup>
import { FileText, Download, ExternalLink, Presentation } from 'lucide-vue-next';

defineProps({
    pdfUrl: { type: String, default: null },
    slidesUrl: { type: String, default: null },
    isDownloadable: { type: Boolean, default: false },
    lessonId: { type: Number, required: true },
});
</script>

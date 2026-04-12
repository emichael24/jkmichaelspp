<template>
    <div class="space-y-6">
        <!-- Estimated read time -->
        <div class="flex items-center gap-2 text-sm text-surface-500">
            <Clock class="w-4 h-4" />
            <span>{{ durationMinutes || estimatedReadTime }} min read</span>
        </div>

        <!-- Summary (collapsible) -->
        <div v-if="summary" class="rounded-xl bg-primary-50/50 border border-primary-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-primary-800 text-sm flex items-center gap-2">
                    <Sparkles class="w-4 h-4" />
                    Quick Summary
                </h3>
                <button
                    @click="summaryExpanded = !summaryExpanded"
                    class="text-xs font-medium text-primary-600 hover:text-primary-700 transition-colors"
                >
                    {{ summaryExpanded ? 'Collapse' : 'Expand' }}
                </button>
            </div>
            <div
                :class="['text-sm text-primary-900 leading-relaxed transition-all', !summaryExpanded ? 'line-clamp-3' : '']"
                v-html="summary"
            />
        </div>

        <!-- Key Terms -->
        <div v-if="keyTerms && keyTerms.length" class="rounded-xl border border-surface-200 p-5">
            <h3 class="font-semibold text-surface-900 text-sm mb-4 flex items-center gap-2">
                <BookOpen class="w-4 h-4 text-accent-500" />
                Key Terms
            </h3>
            <div class="grid sm:grid-cols-2 gap-3">
                <div
                    v-for="(term, index) in keyTerms"
                    :key="index"
                    class="flex gap-3 p-3 rounded-lg bg-surface-50 hover:bg-surface-100 transition-colors"
                >
                    <div class="w-1 rounded-full bg-accent-400 shrink-0" />
                    <div>
                        <p class="font-semibold text-sm text-surface-800">{{ term.term || term }}</p>
                        <p v-if="term.definition" class="text-xs text-surface-500 mt-0.5">{{ term.definition }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div v-if="textContent" class="prose prose-surface max-w-none prose-headings:font-display prose-a:text-primary-600 prose-img:rounded-xl">
            <div v-html="textContent" />
        </div>

        <!-- Empty state -->
        <div v-if="!textContent && !summary" class="text-center py-12">
            <FileText class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500 text-sm">No reading content available for this lesson.</p>
            <p class="text-surface-400 text-xs mt-1">Try the Watch or Listen tabs instead.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Clock, Sparkles, BookOpen, FileText } from 'lucide-vue-next';

const props = defineProps({
    textContent: { type: String, default: null },
    summary: { type: String, default: null },
    keyTerms: { type: Array, default: () => [] },
    durationMinutes: { type: Number, default: null },
});

const summaryExpanded = ref(false);

const estimatedReadTime = computed(() => {
    if (!props.textContent) return 5;
    const words = props.textContent.replace(/<[^>]*>/g, '').split(/\s+/).length;
    return Math.max(1, Math.ceil(words / 200));
});
</script>

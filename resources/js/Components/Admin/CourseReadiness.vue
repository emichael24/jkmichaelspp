<script setup>
import { CheckCircle2, Circle, ClipboardCheck } from 'lucide-vue-next';
defineProps({ issues: { type: Array, default: () => [] }, status: { type: String, default: 'draft' }, published: Boolean });
</script>
<template>
    <section aria-labelledby="course-readiness-title" class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-100 px-5 py-4">
            <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-surface-100 text-surface-600"><ClipboardCheck class="h-5 w-5" /></span><div><h2 id="course-readiness-title" class="text-sm font-semibold text-surface-900">Publication checklist</h2><p class="mt-0.5 text-xs text-surface-500">Based on your saved course content</p></div></div>
            <span :class="['rounded-full px-3 py-1 text-xs font-medium', published ? 'bg-emerald-50 text-emerald-800' : 'bg-surface-100 text-surface-600']">{{ published ? 'Published' : (status || 'draft').replaceAll('_', ' ') }}</span>
        </div>
        <div class="px-5 py-4">
            <div v-if="!issues.length" class="flex items-center gap-2 text-sm text-emerald-800"><CheckCircle2 class="h-4 w-4 shrink-0" /> Content checks passed. {{ published ? 'This course is available to learners.' : 'Complete the required review before publishing.' }}</div>
            <template v-else><p class="mb-3 text-sm font-medium text-surface-700">{{ issues.length }} {{ issues.length === 1 ? 'item needs' : 'items need' }} attention before review or publication</p><ul class="grid gap-2 sm:grid-cols-2"><li v-for="issue in issues" :key="issue" class="flex items-start gap-2 text-sm text-surface-600"><Circle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />{{ issue }}</li></ul></template>
        </div>
    </section>
</template>

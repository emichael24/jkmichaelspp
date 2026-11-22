<script setup>
import { computed } from 'vue';
import { BookOpen, CheckCircle2, ArrowRight, GraduationCap } from 'lucide-vue-next';
const props = defineProps({ form: Object });
const checks = computed(() => [
    { label: 'Clear course title', complete: !!props.form.title?.trim() },
    { label: 'Course description', complete: !!props.form.description?.replace(/<[^>]*>/g, '').trim() },
    { label: 'Category selected', complete: !!props.form.category_id },
    { label: 'Learning outcomes', complete: props.form.learning_outcomes?.some(value => value.trim()) },
]);
</script>
<template>
    <aside class="space-y-5 xl:sticky xl:top-24">
        <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm"><div class="bg-surface-900 p-6 text-white"><div class="mb-7 flex items-center justify-between"><span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-orange-300">COURSE PREVIEW</span><GraduationCap class="h-6 w-6 text-surface-500" /></div><h2 class="break-words text-xl font-semibold leading-tight">{{ form.title || 'Your next great course' }}</h2><p class="mt-3 text-xs leading-5 text-surface-400">{{ form.subtitle || 'A clear promise. Practical skills. Meaningful progress.' }}</p><div class="mt-5 flex gap-2 text-[10px] capitalize"><span class="rounded-md border border-white/15 px-2 py-1">{{ form.level || 'Beginner' }}</span><span class="rounded-md border border-white/15 px-2 py-1">{{ (form.type || 'self_paced').replace('_', ' ') }}</span></div></div><div class="p-5"><h3 class="text-sm font-semibold">Setup checklist</h3><ul class="mt-4 space-y-3"><li v-for="check in checks" :key="check.label" class="flex items-center gap-2 text-xs" :class="check.complete ? 'text-surface-700' : 'text-surface-400'"><CheckCircle2 class="h-4 w-4" :class="check.complete ? 'text-emerald-600' : 'text-surface-300'" />{{ check.label }}</li></ul></div></section>
        <section class="rounded-2xl border border-primary-100 bg-primary-50 p-5"><BookOpen class="h-5 w-5 text-primary-700" /><h3 class="mt-3 text-sm font-semibold text-primary-950">Next: build your curriculum</h3><p class="mt-2 text-xs leading-6 text-primary-800">After saving, organize modules, add lessons (articles, PDFs, slides, video, quizzes, assignments, case studies) and set a drip schedule in the Drip tab.</p><p class="mt-4 flex items-center gap-2 text-xs font-semibold text-primary-900">Saved as a draft <ArrowRight class="h-3.5 w-3.5" /></p></section>
    </aside>
</template>

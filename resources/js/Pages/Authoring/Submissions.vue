<script>
import { h } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

export default {
    // Admins and facilitators share this grading page inside their own workspace shell.
    layout: (render, page) => render(page.props.auth?.user?.roles?.includes('platform_admin') ? AdminLayout : FacilitatorLayout, () => page),
};
</script>

<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Inbox, FileText, CheckCircle2, RotateCcw } from 'lucide-vue-next';
import SimplePagination from '@/Components/UI/SimplePagination.vue';

const props = defineProps({
    lesson: Object, course: Object, assignment: Object, submissions: Object, backUrl: String, courseUrl: String,
});

const open = ref(props.submissions.data.find(item => ['submitted', 'resubmitted', 'in_review'].includes(item.status))?.id ?? null);
const grades = reactive(Object.fromEntries(props.submissions.data.map(item => [item.id, { score: item.score ?? '', feedback: item.feedback ?? '', processing: false, errors: {} }])));
const statusLabel = status => ({ submitted: 'Awaiting review', resubmitted: 'Resubmitted', in_review: 'In review', graded: 'Graded', returned: 'Returned' }[status] || status);
const statusClass = status => status === 'graded' ? 'bg-emerald-50 text-emerald-700' : status === 'returned' ? 'bg-amber-50 text-amber-800' : 'bg-primary-50 text-primary-700';
const formatDate = value => value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';

function grade(submission, status) {
    const state = grades[submission.id];
    router.put(`/authoring/submissions/${submission.id}/grade`, { score: state.score === '' ? null : Number(state.score), feedback: state.feedback, status }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { state.processing = true; state.errors = {}; },
        onFinish: () => { state.processing = false; },
        onError: errors => { state.errors = errors; },
        onSuccess: () => { open.value = null; },
    });
}
</script>

<template>
    <Head :title="`Submissions: ${lesson.title}`" />
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-center gap-3">
            <Link :href="backUrl" aria-label="Back to lesson" class="rounded-lg p-2 hover:bg-surface-100"><ArrowLeft class="h-5 w-5 text-surface-400" /></Link>
            <div>
                <p class="text-sm text-surface-400"><Link :href="courseUrl" class="hover:text-primary-700">{{ course.title }}</Link> / {{ lesson.content_type === 'case_study' ? 'Case study' : 'Assignment' }}</p>
                <h1 class="text-2xl font-bold text-surface-900">{{ lesson.title }}</h1>
            </div>
        </div>

        <div v-if="!submissions.data.length" class="rounded-2xl border border-dashed border-surface-300 bg-white p-12 text-center">
            <Inbox class="mx-auto h-10 w-10 text-surface-300" />
            <p class="mt-3 font-semibold text-surface-700">No submissions yet</p>
            <p class="mt-1 text-sm text-surface-500">Learner work appears here as soon as it is submitted.</p>
        </div>

        <article v-for="submission in submissions.data" :key="submission.id" class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
            <button type="button" class="flex w-full flex-wrap items-center gap-3 px-5 py-4 text-left hover:bg-surface-50" :aria-expanded="open === submission.id" @click="open = open === submission.id ? null : submission.id">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-surface-900">{{ submission.learner.name || submission.learner.email }}</p>
                    <p class="text-xs text-surface-500">Submission {{ submission.submission_number }} · {{ formatDate(submission.submitted_at) }}</p>
                </div>
                <span v-if="submission.score !== null" class="text-sm font-semibold">{{ submission.score }} / {{ assignment.max_score }}</span>
                <span :class="['rounded-full px-3 py-1 text-xs font-semibold', statusClass(submission.status)]">{{ statusLabel(submission.status) }}</span>
            </button>
            <div v-if="open === submission.id" class="space-y-5 border-t border-surface-100 p-5">
                <div v-for="(item, index) in submission.answers" :key="index" class="rounded-xl bg-surface-50 p-4">
                    <p class="text-sm font-semibold">{{ index + 1 }}. {{ item.prompt }}</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-surface-700">{{ item.answer }}</p>
                </div>
                <div v-if="submission.content" class="rounded-xl bg-surface-50 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-surface-500">{{ submission.answers.length ? 'Additional notes' : 'Response' }}</p><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-surface-700">{{ submission.content }}</p></div>
                <div v-if="submission.files.length" class="flex flex-wrap gap-2">
                    <a v-for="file in submission.files" :key="file.url" :href="file.url" class="inline-flex items-center gap-2 rounded-lg border border-surface-200 px-3 py-2 text-xs font-medium text-primary-700 hover:bg-primary-50"><FileText class="h-4 w-4" />{{ file.name }}</a>
                </div>
                <div class="grid gap-4 sm:grid-cols-[160px_1fr]">
                    <label class="text-xs font-medium text-surface-600">Score (out of {{ assignment.max_score }})
                        <input v-model="grades[submission.id].score" type="number" min="0" :max="assignment.max_score" class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm" />
                        <span v-if="assignment.passing_score !== null" class="mt-1 block text-[11px] text-surface-400">Pass mark {{ assignment.passing_score }}</span>
                    </label>
                    <label class="text-xs font-medium text-surface-600">Feedback for the learner
                        <textarea v-model="grades[submission.id].feedback" rows="4" class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm" placeholder="What went well, and what should improve?" />
                    </label>
                </div>
                <p v-for="message in Object.values(grades[submission.id].errors)" :key="message" role="alert" class="text-xs text-rose-700">{{ message }}</p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" :disabled="grades[submission.id].processing" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50 disabled:opacity-50" @click="grade(submission, 'returned')"><RotateCcw class="h-4 w-4" />Return for revision</button>
                    <button type="button" :disabled="grades[submission.id].processing" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 disabled:opacity-50" @click="grade(submission, 'graded')"><CheckCircle2 class="h-4 w-4" />Save grade</button>
                </div>
            </div>
        </article>

        <SimplePagination :page="submissions" />
    </div>
</template>

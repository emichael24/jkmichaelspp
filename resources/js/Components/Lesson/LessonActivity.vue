<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ListChecks, Timer, RotateCcw, Trophy, Send, Paperclip, X, CheckCircle2, Clock3, AlertCircle, FileText } from 'lucide-vue-next';
import SafeHtml from '@/Components/SafeHtml.vue';
import PdfViewer from '@/Components/Lesson/PdfViewer.vue';

const props = defineProps({ lesson: { type: Object, required: true }, activity: { type: Object, default: null } });

const kindLabel = computed(() => ({ quiz: 'Quiz', assignment: 'Assignment', case_study: 'Case study' }[props.lesson.content_type]));
const starting = ref(false);
function startQuiz() {
    starting.value = true;
    router.post(`/quizzes/${props.activity.quiz_id}/start`, {}, { onFinish: () => { starting.value = false; } });
}

const latest = computed(() => props.activity?.submissions?.[0] || null);
const statusLabel = status => ({ submitted: 'Awaiting review', resubmitted: 'Resubmitted · awaiting review', in_review: 'In review', graded: 'Graded', returned: 'Returned for revision' }[status] || status);
const statusClass = status => status === 'graded' ? 'bg-emerald-50 text-emerald-700' : status === 'returned' ? 'bg-amber-50 text-amber-800' : 'bg-primary-50 text-primary-700';

const form = useForm({ content: '', answers: (props.activity?.prompts || []).map(() => ''), files: [] });
const fileInput = ref(null);
function addFiles(event) {
    const chosen = Array.from(event.target.files || []);
    form.files = [...form.files, ...chosen].slice(0, 5);
    event.target.value = '';
}
function submitWork() {
    form.post(`/lessons/${props.lesson.id}/submission`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
const formatSize = bytes => bytes ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : '';
const formatDate = value => value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
</script>

<template>
    <div class="space-y-6">
        <div v-if="!activity || activity.available === false" class="rounded-2xl border border-surface-200 bg-surface-50 p-8 text-center">
            <AlertCircle class="mx-auto h-8 w-8 text-surface-400" />
            <p class="mt-3 text-sm font-semibold text-surface-700">This {{ kindLabel?.toLowerCase() }} is not ready yet.</p>
            <p class="mt-1 text-xs text-surface-500">Your facilitator is still preparing it. Please check back soon.</p>
        </div>

        <!-- Quiz -->
        <template v-else-if="activity.kind === 'quiz'">
            <SafeHtml v-if="lesson.text_content" :content="lesson.text_content" />
            <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 bg-surface-900 px-6 py-5 text-white">
                    <div class="flex items-center gap-3"><ListChecks class="h-6 w-6 text-orange-300" /><div><p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-orange-300">Knowledge check</p><h2 class="text-lg font-semibold">{{ lesson.title }}</h2></div></div>
                    <span v-if="activity.passed" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-200"><Trophy class="h-3.5 w-3.5" />Passed</span>
                </div>
                <dl class="grid grid-cols-2 gap-px bg-surface-100 sm:grid-cols-4">
                    <div class="bg-white p-4"><dt class="text-[11px] text-surface-500">Questions</dt><dd class="mt-1 text-lg font-semibold">{{ activity.question_count }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-[11px] text-surface-500">Pass mark</dt><dd class="mt-1 text-lg font-semibold">{{ activity.passing_score_pct }}%</dd></div>
                    <div class="bg-white p-4"><dt class="text-[11px] text-surface-500">Time limit</dt><dd class="mt-1 text-lg font-semibold">{{ activity.time_limit_minutes ? `${activity.time_limit_minutes} min` : 'None' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-[11px] text-surface-500">Attempts</dt><dd class="mt-1 text-lg font-semibold">{{ activity.attempts_used }}{{ activity.max_attempts ? ` / ${activity.max_attempts}` : '' }}</dd></div>
                </dl>
                <div class="flex flex-wrap items-center gap-3 p-5">
                    <button v-if="activity.can_attempt || activity.in_progress" type="button" :disabled="starting" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-800 disabled:opacity-50" @click="startQuiz">
                        <Timer class="h-4 w-4" />{{ starting ? 'Opening…' : activity.in_progress ? 'Resume quiz' : activity.attempts_used ? 'Retake quiz' : 'Start quiz' }}
                    </button>
                    <p v-else class="text-sm text-surface-600">You have used all attempts for this quiz.</p>
                    <Link v-if="activity.last_attempt_id" :href="`/quiz-attempts/${activity.last_attempt_id}/review`" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 px-4 py-3 text-sm font-medium text-surface-700 hover:bg-surface-50"><RotateCcw class="h-4 w-4" />Review last attempt</Link>
                    <p v-if="activity.best_score !== null" class="ml-auto text-sm text-surface-600">Best score: <strong :class="activity.passed ? 'text-emerald-700' : 'text-surface-900'">{{ Math.round(activity.best_score) }}%</strong></p>
                </div>
                <p class="border-t border-surface-100 px-5 py-3 text-xs text-surface-500">{{ activity.passed ? 'Lesson complete. You can retake the quiz to improve your score.' : `Score ${activity.passing_score_pct}% or higher to complete this lesson.` }}</p>
            </section>
        </template>

        <!-- Assignment / case study -->
        <template v-else>
            <section class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm sm:p-7">
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-primary-700">{{ activity.kind === 'case_study' ? 'Case scenario' : 'Your task' }}</p>
                <SafeHtml v-if="lesson.text_content" :content="lesson.text_content" class="mt-3" />
                <p class="mt-4 text-xs text-surface-500">Scored out of {{ activity.max_score }}{{ activity.passing_score !== null ? ` · pass mark ${activity.passing_score}` : '' }} · up to {{ activity.max_submissions }} submission{{ activity.max_submissions === 1 ? '' : 's' }}</p>
            </section>

            <PdfViewer v-if="lesson.pdf_url" :pdf-url="lesson.pdf_url" :lesson-id="lesson.id" :is-downloadable="lesson.is_downloadable" />

            <section v-for="submission in activity.submissions" :key="submission.id" class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">Submission {{ submission.submission_number }} <span class="font-normal text-surface-400">· {{ formatDate(submission.submitted_at) }}</span></h3>
                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold', statusClass(submission.status)]">{{ statusLabel(submission.status) }}</span>
                </div>
                <div v-if="submission.score !== null && ['graded', 'returned'].includes(submission.status)" class="mt-4 rounded-xl bg-surface-50 p-4">
                    <p class="text-sm"><strong class="text-lg">{{ submission.score }}</strong> / {{ activity.max_score }}</p>
                    <p v-if="submission.feedback" class="mt-2 whitespace-pre-wrap text-sm leading-6 text-surface-700">{{ submission.feedback }}</p>
                </div>
                <details class="mt-3"><summary class="cursor-pointer text-xs font-semibold text-primary-700">View your response</summary>
                    <div class="mt-3 space-y-3 text-sm text-surface-700">
                        <div v-for="(item, index) in submission.answers" :key="index"><p class="font-semibold">{{ index + 1 }}. {{ item.prompt }}</p><p class="mt-1 whitespace-pre-wrap">{{ item.answer }}</p></div>
                        <p v-if="submission.content" class="whitespace-pre-wrap">{{ submission.content }}</p>
                        <a v-for="file in submission.files" :key="file.url" :href="file.url" class="flex items-center gap-2 text-xs font-medium text-primary-700"><FileText class="h-4 w-4" />{{ file.name }}</a>
                    </div>
                </details>
            </section>

            <form v-if="activity.can_submit" class="space-y-4 rounded-2xl border border-primary-100 bg-white p-5 shadow-sm sm:p-7" @submit.prevent="submitWork">
                <h3 class="flex items-center gap-2 text-base font-semibold"><Send class="h-4 w-4 text-primary-600" />{{ latest ? 'Submit a revision' : 'Submit your work' }}</h3>
                <template v-if="activity.kind === 'case_study' && activity.prompts.length">
                    <label v-for="(prompt, index) in activity.prompts" :key="index" class="block">
                        <span class="text-sm font-medium text-surface-800">{{ index + 1 }}. {{ prompt }}</span>
                        <textarea v-model="form.answers[index]" rows="5" required class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm" placeholder="Write your analysis…" />
                        <span v-if="form.errors[`answers.${index}`]" role="alert" class="mt-1 block text-xs text-rose-700">{{ form.errors[`answers.${index}`] }}</span>
                    </label>
                    <label class="block"><span class="text-sm font-medium text-surface-800">Additional notes <span class="font-normal text-surface-400">(optional)</span></span><textarea v-model="form.content" rows="3" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm" /></label>
                </template>
                <label v-else class="block">
                    <span class="text-sm font-medium text-surface-800">Your response</span>
                    <textarea v-model="form.content" rows="8" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm" placeholder="Write your answer here, or attach your work below." />
                    <span v-if="form.errors.content" role="alert" class="mt-1 block text-xs text-rose-700">{{ form.errors.content }}</span>
                </label>
                <div v-if="activity.allow_file_upload">
                    <input ref="fileInput" type="file" multiple class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.zip" @change="addFiles" />
                    <button type="button" :disabled="form.files.length >= 5" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 px-4 py-2 text-xs font-semibold text-surface-700 hover:bg-surface-50 disabled:opacity-40" @click="fileInput.click()"><Paperclip class="h-4 w-4" />Attach files</button>
                    <span class="ml-2 text-[11px] text-surface-400">Up to 5 files · 20 MB each</span>
                    <ul v-if="form.files.length" class="mt-3 space-y-1">
                        <li v-for="(file, index) in form.files" :key="index" class="flex items-center gap-2 text-xs text-surface-600"><Paperclip class="h-3.5 w-3.5" />{{ file.name }} <span class="text-surface-400">{{ formatSize(file.size) }}</span><button type="button" :aria-label="`Remove ${file.name}`" class="ml-1 text-surface-400 hover:text-rose-600" @click="form.files.splice(index, 1)"><X class="h-3.5 w-3.5" /></button></li>
                    </ul>
                    <p v-for="(message, key) in form.errors" v-show="key.startsWith('files')" :key="key" role="alert" class="mt-1 text-xs text-rose-700">{{ message }}</p>
                </div>
                <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-surface-100"><div class="h-full bg-primary-600" :style="{ width: `${form.progress.percentage}%` }" /></div>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-800 disabled:opacity-50"><Send class="h-4 w-4" />{{ form.processing ? 'Submitting…' : 'Submit for review' }}</button>
            </form>
            <p v-else-if="latest && ['submitted', 'resubmitted', 'in_review'].includes(latest.status)" class="flex items-center gap-2 rounded-xl bg-primary-50 p-4 text-sm text-primary-800"><Clock3 class="h-4 w-4" />Your work is with your facilitator. You'll be notified when it has been reviewed.</p>
            <p v-else-if="latest" class="flex items-center gap-2 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"><CheckCircle2 class="h-4 w-4" />This activity is complete.</p>
        </template>
    </div>
</template>

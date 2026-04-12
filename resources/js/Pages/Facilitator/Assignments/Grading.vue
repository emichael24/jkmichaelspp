<template>
    <Head :title="`Grade: ${assignment.title}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/facilitator/cohorts/${cohort.id}/assignments`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">{{ assignment.title }}</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }} · {{ submissions.length }} submissions · Max {{ assignment.max_score }} pts</p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-surface-900">{{ submissions.length }}</p>
                <p class="text-xs text-surface-500">Total</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-warning-600">{{ pendingCount }}</p>
                <p class="text-xs text-surface-500">Pending</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-success-600">{{ gradedCount }}</p>
                <p class="text-xs text-surface-500">Graded</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-primary-600">{{ avgScore }}</p>
                <p class="text-xs text-surface-500">Avg Score</p>
            </div>
        </div>

        <!-- Submissions list -->
        <div v-if="submissions.length" class="space-y-3">
            <div
                v-for="sub in submissions"
                :key="sub.id"
                class="bg-white rounded-xl border border-surface-200/60 p-5"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-surface-900 text-sm">
                                {{ sub.user?.first_name }} {{ sub.user?.last_name }}
                            </span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(sub.status)]">
                                {{ sub.status.replace(/_/g, ' ') }}
                            </span>
                            <span class="text-xs text-surface-400">#{{ sub.submission_number }}</span>
                        </div>
                        <p class="text-xs text-surface-400">Submitted {{ formatDate(sub.submitted_at) }}</p>
                        <p v-if="sub.content" class="text-sm text-surface-600 mt-2 line-clamp-2">{{ sub.content }}</p>
                        <div v-if="sub.file_urls && sub.file_urls.length" class="flex gap-2 mt-2">
                            <span v-for="(f, i) in sub.file_urls" :key="i" class="inline-flex items-center gap-1 px-2 py-1 bg-surface-50 rounded text-xs text-surface-500">
                                <Paperclip class="w-3 h-3" /> File {{ i + 1 }}
                            </span>
                        </div>
                    </div>

                    <div v-if="sub.status === 'graded'" class="text-right">
                        <p class="text-2xl font-bold" :class="sub.score >= assignment.max_score * 0.7 ? 'text-success-600' : 'text-warning-600'">
                            {{ sub.score }}<span class="text-sm text-surface-400">/{{ assignment.max_score }}</span>
                        </p>
                    </div>
                </div>

                <!-- Grading form (show for ungraded) -->
                <div v-if="sub.status !== 'graded'" class="mt-4 p-4 bg-surface-50 rounded-xl space-y-3">
                    <h4 class="text-sm font-semibold text-surface-700">Grade this submission</h4>

                    <!-- Rubric scoring -->
                    <div v-if="assignment.rubric && assignment.rubric.length" class="space-y-2">
                        <div v-for="(crit, ci) in assignment.rubric" :key="ci" class="flex items-center justify-between gap-3">
                            <span class="text-sm text-surface-600 flex-1">{{ crit.criterion }}</span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model.number="gradeForms[sub.id].rubric_scores[crit.criterion]"
                                    type="number"
                                    :min="0"
                                    :max="crit.max_score"
                                    class="w-16 px-2 py-1 border border-surface-200 rounded text-sm text-center"
                                />
                                <span class="text-xs text-surface-400">/ {{ crit.max_score }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else>
                        <label class="block text-sm text-surface-600 mb-1">Score</label>
                        <input v-model.number="gradeForms[sub.id].score" type="number" :min="0" :max="assignment.max_score" class="w-24 px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        <span class="text-sm text-surface-400 ml-1">/ {{ assignment.max_score }}</span>
                    </div>

                    <div>
                        <label class="block text-sm text-surface-600 mb-1">Feedback</label>
                        <textarea v-model="gradeForms[sub.id].feedback" rows="2" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Written feedback..."></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button @click="submitGrade(sub, 'graded')" class="px-4 py-2 bg-success-600 text-white rounded-lg text-sm font-medium hover:bg-success-700">
                            <Check class="w-4 h-4 inline mr-1" /> Grade
                        </button>
                        <button @click="submitGrade(sub, 'returned')" class="px-4 py-2 bg-warning-100 text-warning-700 rounded-lg text-sm font-medium hover:bg-warning-200">
                            <RotateCcw class="w-4 h-4 inline mr-1" /> Return for Revision
                        </button>
                    </div>
                </div>

                <!-- Show existing feedback if graded -->
                <div v-else-if="sub.feedback" class="mt-3 p-3 bg-surface-50 rounded-lg">
                    <p class="text-xs font-semibold text-surface-500 mb-1">Feedback</p>
                    <p class="text-sm text-surface-700">{{ sub.feedback }}</p>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Inbox class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500">No submissions yet.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Paperclip, Check, RotateCcw, Inbox } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    assignment: { type: Object, required: true },
    submissions: { type: Array, default: () => [] },
});

// Build per-submission grade forms
const gradeForms = reactive({});
props.submissions.forEach(sub => {
    if (sub.status !== 'graded') {
        const rubricScores = {};
        (props.assignment.rubric || []).forEach(c => { rubricScores[c.criterion] = 0; });
        gradeForms[sub.id] = { score: 0, rubric_scores: rubricScores, feedback: '' };
    }
});

const pendingCount = computed(() => props.submissions.filter(s => ['submitted', 'resubmitted'].includes(s.status)).length);
const gradedCount = computed(() => props.submissions.filter(s => s.status === 'graded').length);
const avgScore = computed(() => {
    const graded = props.submissions.filter(s => s.score !== null && s.score !== undefined);
    if (!graded.length) return '—';
    return Math.round(graded.reduce((sum, s) => sum + s.score, 0) / graded.length);
});

function submitGrade(sub, status) {
    const form = gradeForms[sub.id];
    let totalScore = form.score;

    // Calculate from rubric if available
    if (props.assignment.rubric?.length) {
        totalScore = Object.values(form.rubric_scores).reduce((s, v) => s + (v || 0), 0);
    }

    router.put(`/facilitator/cohorts/${props.cohort.id}/submissions/${sub.id}/grade`, {
        score: totalScore,
        rubric_scores: props.assignment.rubric?.length ? form.rubric_scores : null,
        feedback: form.feedback,
        status,
    });
}

function statusBadge(status) {
    const map = {
        submitted: 'bg-warning-50 text-warning-700',
        resubmitted: 'bg-accent-50 text-accent-700',
        in_review: 'bg-primary-50 text-primary-700',
        graded: 'bg-success-50 text-success-700',
        returned: 'bg-danger-50 text-danger-700',
    };
    return map[status] || 'bg-surface-100 text-surface-600';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <div>
        <!-- Result banner -->
        <div
            :class="[
                'rounded-2xl p-6 mb-8',
                attempt.passed
                    ? 'bg-gradient-to-br from-success-500 to-success-600'
                    : 'bg-gradient-to-br from-danger-500 to-danger-600',
            ]"
        >
            <div class="max-w-3xl mx-auto flex flex-col sm:flex-row items-center gap-6 text-white">
                <div class="w-24 h-24 rounded-full bg-white/20 backdrop-blur flex items-center justify-center shrink-0">
                    <component :is="attempt.passed ? Trophy : RotateCcw" class="w-10 h-10 text-white" />
                </div>
                <div class="text-center sm:text-left">
                    <h1 class="text-2xl font-display font-bold mb-1">
                        {{ attempt.passed ? 'Congratulations!' : 'Keep Practicing' }}
                    </h1>
                    <p class="text-white/80 text-sm">
                        {{ attempt.passed ? 'You passed this assessment!' : `You need ${attempt.passing_score}% to pass. Keep going!` }}
                    </p>
                </div>
                <div class="sm:ml-auto text-center shrink-0">
                    <div class="text-5xl font-display font-bold">{{ Math.round(attempt.score_pct) }}%</div>
                    <p class="text-white/70 text-sm mt-1">{{ attempt.correct_count }}/{{ attempt.total_count }} correct</p>
                </div>
            </div>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <p class="text-2xl font-bold text-surface-900">{{ Math.round(attempt.score_pct) }}%</p>
                <p class="text-xs text-surface-500 mt-1">Score</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <p class="text-2xl font-bold text-success-600">{{ attempt.correct_count }}</p>
                <p class="text-xs text-surface-500 mt-1">Correct</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <p class="text-2xl font-bold text-danger-600">{{ attempt.total_count - attempt.correct_count }}</p>
                <p class="text-xs text-surface-500 mt-1">Incorrect</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <p class="text-2xl font-bold text-surface-900">{{ formattedTime }}</p>
                <p class="text-xs text-surface-500 mt-1">Time Spent</p>
            </div>
        </div>

        <!-- Domain breakdown -->
        <div v-if="domainEntries.length > 1" class="bg-white rounded-2xl border border-surface-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-surface-900 mb-4">Domain Performance</h2>
            <div class="space-y-3">
                <div v-for="[domain, data] in domainEntries" :key="domain">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm font-medium text-surface-700">{{ domain }}</span>
                        <span class="text-sm font-semibold" :class="data.pct >= attempt.passing_score ? 'text-success-600' : 'text-danger-600'">
                            {{ data.pct }}% ({{ data.correct }}/{{ data.total }})
                        </span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-surface-100 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="data.pct >= attempt.passing_score ? 'bg-success-500' : 'bg-danger-500'"
                            :style="{ width: `${data.pct}%` }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-2 mb-6">
            <button
                v-for="tab in filterTabs"
                :key="tab.value"
                @click="activeFilter = tab.value"
                :class="[
                    'px-4 py-2 rounded-xl text-sm font-medium transition-colors',
                    activeFilter === tab.value
                        ? 'bg-primary-100 text-primary-700'
                        : 'text-surface-500 hover:bg-surface-100',
                ]"
            >
                {{ tab.label }} ({{ tab.count }})
            </button>
        </div>

        <!-- Questions review -->
        <div class="space-y-6">
            <QuestionCard
                v-for="(item, idx) in filteredReview"
                :key="item.question_id"
                :question="{ id: item.question_id, stem: item.stem, type: item.type, options: item.options, domain: item.domain }"
                :current-index="getOriginalIndex(item)"
                :total-count="review.length"
                :selected-ids="item.your_answer_ids || []"
                :is-review="true"
                :correct-answer-ids="item.correct_answer_ids || []"
                :explanation="item.is_correct ? item.explanation_correct : (item.explanation_incorrect?.[item.your_answer_ids?.[0]] || item.explanation_correct)"
                :exam-tip="item.exam_tip"
            />
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-4 mt-8 pb-8">
            <Link
                v-if="lessonUrl"
                :href="lessonUrl"
                class="px-6 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors"
            >
                Back to Lesson
            </Link>
            <Link
                v-if="courseSlug && !lessonUrl"
                :href="`/courses/${courseSlug}`"
                class="px-6 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors"
            >
                Back to Course
            </Link>
            <Link
                href="/dashboard"
                class="px-6 py-3 rounded-xl border border-surface-300 text-surface-700 font-semibold text-sm hover:bg-surface-50 transition-colors"
            >
                Go to Dashboard
            </Link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Trophy, RotateCcw } from 'lucide-vue-next';
import QuestionCard from '@/Components/Quiz/QuestionCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    attempt: Object,
    quizTitle: String,
    courseSlug: String,
    lessonUrl: { type: String, default: null },
    review: Array,
});

const activeFilter = ref('all');

const formattedTime = computed(() => {
    const secs = props.attempt.time_spent_seconds || 0;
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${m}m ${s}s`;
});

const domainEntries = computed(() =>
    props.attempt.domain_scores ? Object.entries(props.attempt.domain_scores) : []
);

const filterTabs = computed(() => [
    { value: 'all', label: 'All', count: props.review.length },
    { value: 'correct', label: 'Correct', count: props.review.filter(r => r.is_correct).length },
    { value: 'incorrect', label: 'Incorrect', count: props.review.filter(r => !r.is_correct).length },
    { value: 'flagged', label: 'Flagged', count: props.review.filter(r => r.is_flagged).length },
]);

const filteredReview = computed(() => {
    if (activeFilter.value === 'correct') return props.review.filter(r => r.is_correct);
    if (activeFilter.value === 'incorrect') return props.review.filter(r => !r.is_correct);
    if (activeFilter.value === 'flagged') return props.review.filter(r => r.is_flagged);
    return props.review;
});

function getOriginalIndex(item) {
    return props.review.findIndex(r => r.question_id === item.question_id);
}
</script>

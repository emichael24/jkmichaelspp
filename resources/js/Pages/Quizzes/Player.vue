<template>
    <div class="min-h-screen bg-surface-50 flex flex-col">
        <!-- Top bar -->
        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-lg border-b border-surface-200/60">
            <div class="max-w-5xl mx-auto flex items-center justify-between h-14 px-4">
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        @click="showExitConfirm = true"
                        class="p-1.5 rounded-lg hover:bg-surface-100 transition-colors text-surface-500"
                    >
                        <X class="w-5 h-5" />
                    </button>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-surface-900 truncate">{{ quiz.title }}</p>
                        <p class="text-xs text-surface-400">{{ quiz.course_title }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Progress -->
                    <span class="text-xs font-medium text-surface-500 hidden sm:inline">
                        {{ answeredCount }}/{{ questions.length }} answered
                    </span>
                    <!-- Timer -->
                    <QuizTimer
                        v-if="quiz.time_limit_minutes"
                        :total-seconds="remainingSeconds"
                        @expired="handleTimeExpired"
                    />
                </div>
            </div>

            <!-- Progress bar -->
            <div class="h-1 bg-surface-100">
                <div
                    class="h-full bg-gradient-to-r from-primary-500 to-accent-500 transition-all duration-300"
                    :style="{ width: `${progressPct}%` }"
                />
            </div>
        </header>

        <!-- Main content -->
        <div class="flex-1 max-w-5xl mx-auto w-full px-4 py-6 lg:py-8">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Question area -->
                <div class="flex-1 min-w-0">
                    <QuestionCard
                        :question="currentQuestion"
                        :current-index="currentIndex"
                        :total-count="questions.length"
                        :selected-ids="currentAnswer"
                        :confidence="currentConfidence"
                        :is-flagged="currentFlag"
                        @select="handleSelect"
                        @confidence="handleConfidence"
                        @flag="toggleFlag"
                    />

                    <!-- Navigation -->
                    <div class="flex items-center justify-between mt-6">
                        <button
                            @click="goTo(currentIndex - 1)"
                            :disabled="currentIndex === 0"
                            :class="[
                                'flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors',
                                currentIndex === 0
                                    ? 'text-surface-300 cursor-not-allowed'
                                    : 'text-surface-600 hover:bg-surface-100',
                            ]"
                        >
                            <ChevronLeft class="w-4 h-4" />
                            Previous
                        </button>

                        <template v-if="currentIndex < questions.length - 1">
                            <button
                                @click="saveAndNext"
                                class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700 transition-colors shadow-sm"
                            >
                                Next
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </template>
                        <template v-else>
                            <button
                                @click="showSubmitConfirm = true"
                                class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-success-600 text-white text-sm font-semibold hover:bg-success-700 transition-colors shadow-sm"
                            >
                                <Send class="w-4 h-4" />
                                Submit Quiz
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Sidebar: Nav Grid -->
                <div class="lg:w-72 shrink-0">
                    <QuizNavGrid
                        :questions="questions"
                        :current-index="currentIndex"
                        :answers="answers"
                        :flags="flags"
                        @goto="goTo"
                    />

                    <button
                        @click="showSubmitConfirm = true"
                        class="mt-4 w-full px-4 py-3 rounded-xl border-2 border-primary-200 text-primary-700 text-sm font-semibold hover:bg-primary-50 transition-colors"
                    >
                        Finish & Submit
                    </button>
                </div>
            </div>
        </div>

        <!-- Exit confirm modal -->
        <Modal :show="showExitConfirm" @close="showExitConfirm = false">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-warning-100 flex items-center justify-center">
                        <AlertTriangle class="w-5 h-5 text-warning-600" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-surface-900">Leave Quiz?</h3>
                        <p class="text-sm text-surface-500">Your progress will be saved. You can resume later.</p>
                    </div>
                </div>
                <div class="flex gap-3 justify-end">
                    <button
                        @click="showExitConfirm = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-surface-600 hover:bg-surface-100 transition-colors"
                    >
                        Continue Quiz
                    </button>
                    <button
                        @click="exitQuiz"
                        class="px-4 py-2 rounded-xl bg-warning-600 text-white text-sm font-semibold hover:bg-warning-700 transition-colors"
                    >
                        Leave Quiz
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Submit confirm modal -->
        <Modal :show="showSubmitConfirm" @close="showSubmitConfirm = false">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                        <Send class="w-5 h-5 text-primary-600" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-surface-900">Submit Quiz?</h3>
                        <p class="text-sm text-surface-500">
                            You've answered {{ answeredCount }} of {{ questions.length }} questions.
                            <span v-if="flaggedCount" class="text-warning-600 font-medium">
                                {{ flaggedCount }} flagged for review.
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="unansweredCount > 0" class="mb-4 p-3 rounded-xl bg-warning-50 border border-warning-200">
                    <p class="text-sm text-warning-700">
                        <strong>{{ unansweredCount }}</strong> question(s) are still unanswered. They will be marked incorrect.
                    </p>
                </div>

                <div class="flex gap-3 justify-end">
                    <button
                        @click="showSubmitConfirm = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-surface-600 hover:bg-surface-100 transition-colors"
                    >
                        Review Answers
                    </button>
                    <button
                        @click="submitQuiz"
                        :disabled="submitting"
                        class="px-5 py-2 rounded-xl bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50"
                    >
                        {{ submitting ? 'Submitting…' : 'Submit Quiz' }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    X, ChevronLeft, ChevronRight, Send, AlertTriangle,
} from 'lucide-vue-next';
import QuestionCard from '@/Components/Quiz/QuestionCard.vue';
import QuizTimer from '@/Components/Quiz/QuizTimer.vue';
import QuizNavGrid from '@/Components/Quiz/QuizNavGrid.vue';
import Modal from '@/Components/UI/Modal.vue';

const props = defineProps({
    quiz: Object,
    attempt: Object,
    questions: Array,
    existingResponses: { type: Object, default: () => ({}) },
});

// State
const currentIndex = ref(0);
const showExitConfirm = ref(false);
const showSubmitConfirm = ref(false);
const submitting = ref(false);

// answers: { questionId: [selectedIds] }
const answers = reactive({});
// confidence: { questionId: 'low'|'medium'|'high' }
const confidence = reactive({});
// flags: { questionId: true/false }
const flags = reactive({});
// per-question time tracking
const questionTimers = reactive({});
let questionStartTime = Date.now();

// Hydrate from existing responses
Object.entries(props.existingResponses).forEach(([qId, resp]) => {
    if (resp.selected_answer_ids?.length) answers[qId] = resp.selected_answer_ids;
    if (resp.confidence_level) confidence[qId] = resp.confidence_level;
    if (resp.is_flagged) flags[qId] = true;
    if (resp.time_spent_seconds) questionTimers[qId] = resp.time_spent_seconds;
});

// Computed
const currentQuestion = computed(() => props.questions[currentIndex.value]);
const currentAnswer = computed(() => answers[currentQuestion.value?.id] || []);
const currentConfidence = computed(() => confidence[currentQuestion.value?.id] || null);
const currentFlag = computed(() => !!flags[currentQuestion.value?.id]);

const answeredCount = computed(() =>
    props.questions.filter(q => answers[q.id]?.length > 0).length
);
const unansweredCount = computed(() => props.questions.length - answeredCount.value);
const flaggedCount = computed(() =>
    props.questions.filter(q => flags[q.id]).length
);
const progressPct = computed(() =>
    props.questions.length > 0 ? (answeredCount.value / props.questions.length) * 100 : 0
);

// Timer calculation
const remainingSeconds = computed(() => {
    if (props.attempt.expires_at) return Math.max(0, Math.ceil((new Date(props.attempt.expires_at).getTime() - Date.now()) / 1000));
    if (!props.quiz.time_limit_minutes) return 0;
    const elapsed = Math.floor((Date.now() - new Date(props.attempt.started_at).getTime()) / 1000);
    const total = props.quiz.time_limit_minutes * 60;
    return Math.max(0, total - elapsed);
});

// Handlers
function handleSelect(selectedIds) {
    answers[currentQuestion.value.id] = selectedIds;
}

function handleConfidence(level) {
    confidence[currentQuestion.value.id] = level;
}

function toggleFlag() {
    const qId = currentQuestion.value.id;
    flags[qId] = !flags[qId];
}

function trackTime() {
    const qId = currentQuestion.value.id;
    const elapsed = Math.floor((Date.now() - questionStartTime) / 1000);
    questionTimers[qId] = (questionTimers[qId] || 0) + elapsed;
    questionStartTime = Date.now();
}

function saveCurrentAnswer() {
    trackTime();
    const qId = currentQuestion.value.id;
    const selected = answers[qId];
    if (!selected || selected.length === 0) return;

    router.post(
        `/quiz-attempts/${props.attempt.id}/answer`,
        {
            question_id: qId,
            selected_answer_ids: selected,
            confidence_level: confidence[qId] || null,
            time_spent_seconds: questionTimers[qId] || 0,
            is_flagged: !!flags[qId],
        },
        { preserveScroll: true, preserveState: true }
    );
}

function goTo(idx) {
    if (idx < 0 || idx >= props.questions.length) return;
    saveCurrentAnswer();
    currentIndex.value = idx;
    questionStartTime = Date.now();
}

function saveAndNext() {
    saveCurrentAnswer();
    currentIndex.value = Math.min(currentIndex.value + 1, props.questions.length - 1);
    questionStartTime = Date.now();
}

function exitQuiz() {
    saveCurrentAnswer();
    showExitConfirm.value = false;
    if (props.quiz.course_slug) {
        router.visit(`/courses/${props.quiz.course_slug}`);
    } else {
        router.visit('/dashboard');
    }
}

function handleTimeExpired() {
    submitQuiz();
}

function submitQuiz() {
    saveCurrentAnswer();
    submitting.value = true;
    router.post(`/quiz-attempts/${props.attempt.id}/complete`, {}, {
        onFinish: () => { submitting.value = false; },
    });
}

// Save answer on component unmount
onBeforeUnmount(() => {
    const qId = currentQuestion.value?.id;
    if (qId && answers[qId]?.length > 0) {
        trackTime();
    }
});
</script>

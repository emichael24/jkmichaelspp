<template>
    <div>
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-surface-400 mb-6">
            <Link href="/courses" class="hover:text-surface-600 transition-colors">Courses</Link>
            <ChevronRight class="w-3.5 h-3.5" />
            <Link v-if="quiz.course" :href="`/courses/${quiz.course.slug}`" class="hover:text-surface-600 transition-colors">
                {{ quiz.course.title }}
            </Link>
            <ChevronRight class="w-3.5 h-3.5" />
            <span class="text-surface-600 font-medium">{{ quiz.title }}</span>
        </div>

        <div class="max-w-3xl mx-auto">
            <!-- Quiz card -->
            <div class="bg-white rounded-2xl border border-surface-200 overflow-hidden">
                <!-- Header gradient -->
                <div class="bg-gradient-to-br from-primary-600 via-primary-500 to-accent-500 p-8 text-white">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2.5 py-0.5 rounded-full bg-white/20 text-xs font-semibold uppercase tracking-wider">
                            {{ typeLabel }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-display font-bold mb-2">{{ quiz.title }}</h1>
                    <p v-if="quiz.module" class="text-white/70 text-sm">Module: {{ quiz.module.title }}</p>
                </div>

                <!-- Quiz details -->
                <div class="p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        <div class="text-center p-3 rounded-xl bg-surface-50">
                            <HelpCircle class="w-5 h-5 text-primary-500 mx-auto mb-1" />
                            <p class="text-lg font-bold text-surface-900">{{ quiz.question_count }}</p>
                            <p class="text-xs text-surface-500">Questions</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-surface-50">
                            <Clock class="w-5 h-5 text-accent-500 mx-auto mb-1" />
                            <p class="text-lg font-bold text-surface-900">
                                {{ quiz.time_limit_minutes ? `${quiz.time_limit_minutes} min` : 'None' }}
                            </p>
                            <p class="text-xs text-surface-500">Time Limit</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-surface-50">
                            <Target class="w-5 h-5 text-success-500 mx-auto mb-1" />
                            <p class="text-lg font-bold text-surface-900">{{ quiz.passing_score_pct }}%</p>
                            <p class="text-xs text-surface-500">Passing Score</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-surface-50">
                            <RotateCcw class="w-5 h-5 text-warning-500 mx-auto mb-1" />
                            <p class="text-lg font-bold text-surface-900">
                                {{ quiz.max_attempts || '∞' }}
                            </p>
                            <p class="text-xs text-surface-500">Max Attempts</p>
                        </div>
                    </div>

                    <!-- Attempt info -->
                    <div v-if="attemptCount > 0" class="mb-6 p-4 rounded-xl bg-surface-50 border border-surface-100">
                        <p class="text-sm text-surface-600">
                            You've completed <strong class="text-surface-900">{{ attemptCount }}</strong> attempt(s)
                            <template v-if="quiz.max_attempts">
                                out of <strong class="text-surface-900">{{ quiz.max_attempts }}</strong> allowed
                            </template>.
                        </p>
                    </div>

                    <!-- Start button -->
                    <div class="flex flex-col items-center gap-4">
                        <button
                            v-if="canAttempt"
                            @click="startQuiz"
                            :disabled="starting"
                            class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-colors shadow-sm disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <Play class="w-5 h-5" />
                            {{ attemptCount > 0 ? 'Retake Quiz' : 'Start Quiz' }}
                        </button>
                        <div
                            v-else
                            class="w-full p-4 rounded-xl bg-warning-50 border border-warning-200 text-center"
                        >
                            <p class="text-sm text-warning-700 font-medium">Maximum attempts reached</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Previous attempts -->
            <div v-if="previousAttempts.length > 0" class="mt-8">
                <h2 class="text-lg font-semibold text-surface-900 mb-4">Previous Attempts</h2>
                <div class="space-y-3">
                    <Link
                        v-for="(att, idx) in previousAttempts"
                        :key="att.id"
                        :href="`/quiz-attempts/${att.id}/review`"
                        class="flex items-center justify-between bg-white p-4 rounded-xl border border-surface-200 hover:border-primary-300 hover:shadow-sm transition-all group"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold',
                                    att.score_pct >= quiz.passing_score_pct
                                        ? 'bg-success-100 text-success-700'
                                        : 'bg-danger-100 text-danger-700',
                                ]"
                            >
                                {{ Math.round(att.score_pct) }}%
                            </div>
                            <div>
                                <p class="text-sm font-medium text-surface-900">
                                    Attempt #{{ previousAttempts.length - idx }}
                                </p>
                                <p class="text-xs text-surface-400">
                                    {{ att.correct_count }}/{{ att.total_count }} correct · {{ formatTime(att.time_spent_seconds) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-surface-400 group-hover:text-primary-500 transition-colors">
                            <span class="text-xs text-surface-400">{{ formatDate(att.completed_at) }}</span>
                            <ChevronRight class="w-4 h-4" />
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    ChevronRight, HelpCircle, Clock, Target, RotateCcw, Play,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    quiz: Object,
    previousAttempts: Array,
    canAttempt: Boolean,
    attemptCount: Number,
});

const starting = ref(false);

const typeLabel = {
    topic_quiz: 'Topic Quiz',
    module_test: 'Module Test',
    practice_exam: 'Practice Exam',
    diagnostic: 'Diagnostic',
    certification_prep: 'Certification Prep',
}[props.quiz.type] || props.quiz.type;

function startQuiz() {
    starting.value = true;
    router.post(`/quizzes/${props.quiz.id}/start`, {}, {
        onFinish: () => { starting.value = false; },
    });
}

function formatTime(seconds) {
    if (!seconds) return '—';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${s}s`;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}
</script>

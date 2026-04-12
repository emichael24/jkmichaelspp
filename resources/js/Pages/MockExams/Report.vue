<template>
    <div class="max-w-6xl mx-auto">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-surface-500 mb-6">
            <Link :href="route('mock-exams.index')" class="hover:text-primary-600">Mock Exams</Link>
            <span>/</span>
            <span class="text-surface-700">Report</span>
        </nav>

        <!-- Score banner -->
        <div class="rounded-2xl p-8 mb-8 text-white"
             :class="attempt.passed ? 'bg-gradient-to-br from-success-500 to-success-700' : 'bg-gradient-to-br from-danger-500 to-danger-700'">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-20 h-20 rounded-full bg-white/20 flex items-center justify-center">
                    <Trophy v-if="attempt.passed" class="w-10 h-10" />
                    <RotateCcw v-else class="w-10 h-10" />
                </div>
                <div>
                    <p class="text-lg font-medium opacity-80">{{ attempt.passed ? 'Congratulations!' : 'Keep Practicing' }}</p>
                    <h1 class="text-4xl font-display font-bold">{{ attempt.score_pct }}%</h1>
                    <p class="text-sm opacity-70 mt-1">{{ mockExam.title }}</p>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <p class="text-2xl font-bold">{{ attempt.correct_count }}/{{ attempt.total_count }}</p>
                    <p class="text-xs opacity-70">Correct</p>
                </div>
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <p class="text-2xl font-bold">{{ attempt.passing_score }}%</p>
                    <p class="text-xs opacity-70">Passing Score</p>
                </div>
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <p class="text-2xl font-bold">{{ formatTime(attempt.time_spent_seconds) }}</p>
                    <p class="text-xs opacity-70">Time Spent</p>
                </div>
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <p class="text-2xl font-bold">{{ attempt.passed ? 'PASS' : 'FAIL' }}</p>
                    <p class="text-xs opacity-70">Result</p>
                </div>
            </div>
        </div>

        <!-- Tab navigation -->
        <div class="flex gap-1 mb-6 border-b border-surface-200 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition"
                :class="activeTab === tab.key
                    ? 'border-primary-600 text-primary-600'
                    : 'border-transparent text-surface-500 hover:text-surface-700'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Domain Analysis -->
        <div v-show="activeTab === 'domains'" class="space-y-4">
            <div
                v-for="(score, domain) in attempt.domain_scores"
                :key="domain"
                class="bg-white rounded-xl border border-surface-200 p-5"
            >
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-surface-900">{{ domain }}</h3>
                    <span class="text-sm font-bold" :class="score.pct >= attempt.passing_score ? 'text-success-600' : 'text-danger-600'">
                        {{ score.pct }}%
                    </span>
                </div>
                <div class="w-full h-2.5 bg-surface-100 rounded-full">
                    <div class="h-full rounded-full transition-all"
                         :class="score.pct >= attempt.passing_score ? 'bg-success-500' : 'bg-danger-500'"
                         :style="{ width: score.pct + '%' }"
                    ></div>
                </div>
                <p class="text-xs text-surface-500 mt-1">{{ score.correct }}/{{ score.total }} correct</p>
            </div>
        </div>

        <!-- Time Analysis -->
        <div v-show="activeTab === 'time'" class="space-y-6">
            <div class="bg-white rounded-xl border border-surface-200 p-6">
                <h3 class="font-semibold text-surface-900 mb-4">Average Time per Question</h3>
                <p class="text-4xl font-bold text-primary-600">{{ timeAnalysis.avg_time_seconds }}s</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl border border-surface-200 p-6">
                    <h4 class="font-semibold text-surface-900 mb-3 flex items-center gap-2">
                        <Zap class="w-4 h-4 text-success-500" /> Fastest Questions
                    </h4>
                    <div v-for="(q, i) in timeAnalysis.fastest" :key="i" class="flex items-center justify-between py-2 border-b border-surface-50 last:border-0">
                        <span class="text-sm text-surface-700 truncate mr-4">{{ q.stem }}</span>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-mono">{{ q.time }}s</span>
                            <Check v-if="q.correct" class="w-4 h-4 text-success-500" />
                            <X v-else class="w-4 h-4 text-danger-500" />
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-surface-200 p-6">
                    <h4 class="font-semibold text-surface-900 mb-3 flex items-center gap-2">
                        <Clock class="w-4 h-4 text-warning-500" /> Slowest Questions
                    </h4>
                    <div v-for="(q, i) in timeAnalysis.slowest" :key="i" class="flex items-center justify-between py-2 border-b border-surface-50 last:border-0">
                        <span class="text-sm text-surface-700 truncate mr-4">{{ q.stem }}</span>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-mono">{{ q.time }}s</span>
                            <Check v-if="q.correct" class="w-4 h-4 text-success-500" />
                            <X v-else class="w-4 h-4 text-danger-500" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-surface-200 p-6">
                <h4 class="font-semibold text-surface-900 mb-3">Time by Domain</h4>
                <div class="space-y-3">
                    <div v-for="d in timeAnalysis.by_domain" :key="d.domain" class="flex items-center justify-between">
                        <span class="text-sm text-surface-700">{{ d.domain }}</span>
                        <span class="text-sm font-mono text-surface-600">avg {{ d.avg_time }}s ({{ d.count }} Qs)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confidence Analysis -->
        <div v-show="activeTab === 'confidence'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-surface-200 p-6 text-center">
                    <p class="text-3xl font-bold text-surface-900">{{ confidenceAnalysis.total_rated }}</p>
                    <p class="text-sm text-surface-500">Confidence Rated</p>
                </div>
                <div class="bg-white rounded-xl border border-warning-200 p-6 text-center">
                    <p class="text-3xl font-bold text-warning-600">{{ confidenceAnalysis.overconfident }}</p>
                    <p class="text-sm text-surface-500">Overconfident</p>
                    <p class="text-xs text-surface-400 mt-1">Rated high, got wrong</p>
                </div>
                <div class="bg-white rounded-xl border border-accent-200 p-6 text-center">
                    <p class="text-3xl font-bold text-accent-600">{{ confidenceAnalysis.underconfident }}</p>
                    <p class="text-sm text-surface-500">Underconfident</p>
                    <p class="text-xs text-surface-400 mt-1">Rated low, got right</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-surface-200 p-6">
                <h4 class="font-semibold text-surface-900 mb-4">Accuracy by Confidence Level</h4>
                <div class="space-y-4">
                    <div v-for="c in confidenceAnalysis.by_confidence" :key="c.level">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium capitalize text-surface-700">{{ c.level }}</span>
                            <span class="text-sm text-surface-600">{{ c.correct }}/{{ c.total }} ({{ c.accuracy_pct }}%)</span>
                        </div>
                        <div class="w-full h-2.5 bg-surface-100 rounded-full">
                            <div class="h-full bg-primary-500 rounded-full" :style="{ width: c.accuracy_pct + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7-Day Plan -->
        <div v-show="activeTab === 'plan'" class="space-y-6">
            <!-- Fix priorities -->
            <div v-if="improvementPlan.fix_priorities?.length" class="bg-white rounded-xl border border-surface-200 p-6">
                <h3 class="font-semibold text-surface-900 mb-4 flex items-center gap-2">
                    <Target class="w-5 h-5 text-danger-500" /> Priority Fix Areas
                </h3>
                <div class="space-y-3">
                    <div v-for="(p, i) in improvementPlan.fix_priorities" :key="i" class="flex items-center gap-4 p-3 rounded-lg bg-surface-50">
                        <span class="w-8 h-8 rounded-full bg-danger-100 text-danger-700 flex items-center justify-center text-sm font-bold">{{ i + 1 }}</span>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-surface-900">{{ p.domain }}</p>
                            <p class="text-xs text-surface-500">Score: {{ p.score }}% — {{ p.wrong_count }} incorrect</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily plan -->
            <div class="bg-white rounded-xl border border-surface-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50">
                    <h3 class="font-semibold text-surface-900 flex items-center gap-2">
                        <Calendar class="w-5 h-5 text-primary-500" /> Your 7-Day Improvement Plan
                    </h3>
                </div>
                <div class="divide-y divide-surface-100">
                    <div v-for="(day, i) in improvementPlan.daily_plan" :key="i" class="p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-bold">
                                {{ day.day }}
                            </span>
                            <h4 class="font-medium text-surface-900">Day {{ day.day }}</h4>
                        </div>
                        <div class="ml-11 space-y-2">
                            <div v-for="(task, j) in day.tasks" :key="j" class="flex items-start gap-2">
                                <component :is="taskIcon(task.type)" class="w-4 h-4 mt-0.5 shrink-0" :class="taskIconColor(task.type)" />
                                <span class="text-sm text-surface-700">{{ task.description }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Question Review -->
        <div v-show="activeTab === 'questions'" class="space-y-4">
            <!-- Filter -->
            <div class="flex gap-2 mb-4">
                <button v-for="f in questionFilters" :key="f.key"
                        @click="questionFilter = f.key"
                        class="px-3 py-1.5 text-xs font-medium rounded-full transition"
                        :class="questionFilter === f.key ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-600 hover:bg-surface-200'"
                >
                    {{ f.label }} ({{ f.count }})
                </button>
            </div>

            <div v-for="(item, i) in filteredReview" :key="i" class="bg-white rounded-xl border border-surface-200 p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                         :class="item.is_correct ? 'bg-success-100' : 'bg-danger-100'">
                        <Check v-if="item.is_correct" class="w-4 h-4 text-success-600" />
                        <X v-else class="w-4 h-4 text-danger-600" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-surface-900">{{ item.stem }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span class="px-2 py-0.5 rounded bg-surface-100 text-surface-600">{{ item.domain }}</span>
                            <span v-if="item.confidence_level" class="px-2 py-0.5 rounded bg-accent-50 text-accent-700 capitalize">{{ item.confidence_level }}</span>
                            <span class="px-2 py-0.5 rounded bg-surface-50 text-surface-500">{{ item.time_spent_seconds }}s</span>
                        </div>
                        <p v-if="item.explanation" class="mt-2 text-sm text-surface-600 bg-surface-50 p-3 rounded-lg">{{ item.explanation }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-10 flex items-center justify-center gap-4">
            <Link :href="route('mock-exams.show', mockExam.id)" class="px-6 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">
                Retake Exam
            </Link>
            <Link :href="route('practice.dashboard')" class="px-6 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700">
                Start Practice
            </Link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, markRaw } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Trophy, RotateCcw, Check, X, Zap, Clock, Target, Calendar, BookOpen, Brain, Dumbbell } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    attempt: Object,
    mockExam: Object,
    review: Array,
    improvementPlan: Object,
    timeAnalysis: Object,
    confidenceAnalysis: Object,
})

const activeTab = ref('domains')
const questionFilter = ref('all')

const tabs = [
    { key: 'domains', label: 'Domain Analysis' },
    { key: 'time', label: 'Time Analysis' },
    { key: 'confidence', label: 'Confidence' },
    { key: 'plan', label: '7-Day Plan' },
    { key: 'questions', label: 'Questions' },
]

const correctCount = computed(() => props.review.filter(r => r.is_correct).length)
const incorrectCount = computed(() => props.review.filter(r => !r.is_correct).length)

const questionFilters = computed(() => [
    { key: 'all', label: 'All', count: props.review.length },
    { key: 'correct', label: 'Correct', count: correctCount.value },
    { key: 'incorrect', label: 'Incorrect', count: incorrectCount.value },
])

const filteredReview = computed(() => {
    if (questionFilter.value === 'correct') return props.review.filter(r => r.is_correct)
    if (questionFilter.value === 'incorrect') return props.review.filter(r => !r.is_correct)
    return props.review
})

function formatTime(seconds) {
    if (!seconds) return '-'
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return `${m}m ${s}s`
}

function taskIcon(type) {
    const map = { review_lesson: markRaw(BookOpen), practice_quiz: markRaw(Dumbbell), flashcard_review: markRaw(Brain), mixed_quiz: markRaw(Target), mini_mock: markRaw(Trophy) }
    return map[type] || markRaw(BookOpen)
}

function taskIconColor(type) {
    const map = { review_lesson: 'text-primary-500', practice_quiz: 'text-accent-500', flashcard_review: 'text-xp-500', mixed_quiz: 'text-warning-500', mini_mock: 'text-success-500' }
    return map[type] || 'text-surface-500'
}
</script>

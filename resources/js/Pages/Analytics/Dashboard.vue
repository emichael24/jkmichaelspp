<template>
    <Head title="Analytics" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Analytics Dashboard</h1>
                <p class="text-surface-500 text-sm mt-1">Track your mastery, spot patterns, and plan your growth.</p>
            </div>
            <div class="flex items-center gap-3">
                <select
                    v-model="selectedCourse"
                    @change="filterByCourse"
                    class="rounded-xl border-surface-200 text-sm focus:ring-primary-500 focus:border-primary-500"
                >
                    <option :value="null">All Courses</option>
                    <option v-for="c in enrolledCourses" :key="c.id" :value="c.id">{{ c.title }}</option>
                </select>
                <Link href="/analytics/weekly-report" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-50 text-primary-700 text-sm font-medium hover:bg-primary-100 transition-colors">
                    <FileText class="w-4 h-4" />
                    Weekly Report
                </Link>
            </div>
        </div>

        <!-- Readiness overview -->
        <div v-if="readiness" class="grid sm:grid-cols-4 gap-4">
            <div class="sm:col-span-1 bg-gradient-to-br from-primary-600 to-primary-700 rounded-2xl p-5 text-white">
                <p class="text-white/60 text-xs font-medium mb-2">Overall Readiness</p>
                <div class="flex items-end gap-2">
                    <span class="text-4xl font-display font-bold">{{ readiness.score }}%</span>
                </div>
                <p class="mt-2 text-white/70 text-sm">{{ passLikelihoodLabel }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <div :class="['w-2 h-2 rounded-full', confidenceColor]" />
                    <span class="text-white/80 text-xs capitalize">{{ readiness.confidence_band }} confidence</span>
                </div>
            </div>
            <div class="sm:col-span-3 bg-white rounded-2xl border border-surface-200 p-5">
                <p class="text-sm font-semibold text-surface-700 mb-3">Weak Areas</p>
                <div v-if="readiness.weak_areas?.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="area in readiness.weak_areas"
                        :key="area"
                        class="px-3 py-1.5 bg-danger-50 text-danger-700 text-sm rounded-lg font-medium"
                    >
                        {{ area }}
                    </span>
                </div>
                <p v-else class="text-sm text-surface-400">No weak areas detected — great work!</p>
                <p v-if="readiness.calculated_at" class="text-xs text-surface-400 mt-3">Updated {{ readiness.calculated_at }}</p>
            </div>
        </div>

        <!-- Coach insight -->
        <div v-if="coachInsight" class="flex items-start gap-3 p-4 rounded-xl bg-accent-50 border border-accent-200">
            <div class="w-8 h-8 rounded-lg bg-accent-100 flex items-center justify-center shrink-0 mt-0.5">
                <Lightbulb class="w-4 h-4 text-accent-600" />
            </div>
            <div>
                <p class="text-sm font-semibold text-accent-800">Coach Insight</p>
                <p class="text-sm text-accent-700 mt-0.5">{{ coachInsight }}</p>
            </div>
        </div>

        <!-- Mastery map + trends -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Mastery map -->
            <div class="bg-white rounded-2xl border border-surface-200 p-5">
                <h3 class="text-sm font-semibold text-surface-700 mb-4 flex items-center gap-2">
                    <Target class="w-4 h-4 text-primary-500" />
                    Mastery Map
                </h3>
                <div v-if="masteryDomains.length" class="space-y-3">
                    <div v-for="domain in masteryDomains" :key="domain.domain" class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 font-medium">{{ domain.domain }}</span>
                            <div class="flex items-center gap-2">
                                <TrendingUp v-if="domain.trend === 'improving'" class="w-3.5 h-3.5 text-success-500" />
                                <TrendingDown v-else-if="domain.trend === 'declining'" class="w-3.5 h-3.5 text-danger-500" />
                                <Minus v-else class="w-3.5 h-3.5 text-surface-400" />
                                <span :class="['text-sm font-semibold', masteryColor(domain.composite_score)]">
                                    {{ domain.composite_score }}%
                                </span>
                            </div>
                        </div>
                        <div class="bg-surface-100 rounded-full h-2.5">
                            <div
                                :class="['h-full rounded-full transition-all duration-500', masteryBarColor(domain.composite_score)]"
                                :style="{ width: `${domain.composite_score}%` }"
                            />
                        </div>
                        <div class="flex items-center gap-2 text-xs text-surface-400">
                            <span class="capitalize">{{ domain.mastery_level }}</span>
                            <span>·</span>
                            <span>{{ domain.total_attempts }} attempts</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <Target class="w-10 h-10 text-surface-200 mx-auto mb-2" />
                    <p class="text-sm text-surface-400">Complete quizzes to see your mastery map.</p>
                </div>
            </div>

            <!-- Performance trends -->
            <div class="bg-white rounded-2xl border border-surface-200 p-5">
                <h3 class="text-sm font-semibold text-surface-700 mb-4 flex items-center gap-2">
                    <TrendingUp class="w-4 h-4 text-primary-500" />
                    Performance Trends
                </h3>
                <div v-if="trends.length" class="space-y-2">
                    <!-- Simple visual trend -->
                    <div class="flex items-end gap-1 h-32">
                        <div
                            v-for="(point, i) in trends"
                            :key="i"
                            class="flex-1 flex flex-col items-center gap-1"
                        >
                            <div
                                :class="[
                                    'w-full rounded-t transition-all',
                                    point.type === 'mock' ? 'bg-accent-400' : 'bg-primary-400',
                                ]"
                                :style="{ height: `${point.score}%` }"
                                :title="`${point.date}: ${point.score}%`"
                            />
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-surface-400 pt-1 border-t border-surface-100">
                        <span>{{ trends[0]?.date }}</span>
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-primary-400"></span> Quiz</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-accent-400"></span> Mock</span>
                        </div>
                        <span>{{ trends[trends.length - 1]?.date }}</span>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <TrendingUp class="w-10 h-10 text-surface-200 mx-auto mb-2" />
                    <p class="text-sm text-surface-400">Complete assessments to see trends.</p>
                </div>
            </div>
        </div>

        <!-- Error patterns -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <h3 class="text-sm font-semibold text-surface-700 mb-4 flex items-center gap-2">
                <AlertTriangle class="w-4 h-4 text-warning-500" />
                Error Patterns (Last 30 Days)
            </h3>
            <div v-if="errorPatterns.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="pattern in errorPatterns"
                    :key="pattern.type"
                    class="p-4 rounded-xl border border-surface-200 bg-surface-50"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', patternIconBg(pattern.type)]">
                            <component :is="patternIcon(pattern.type)" class="w-4 h-4 text-white" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-surface-800">{{ patternLabel(pattern.type) }}</p>
                            <p class="text-xs text-surface-400">{{ pattern.domain }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-surface-600 mb-2">{{ pattern.suggested_fix }}</p>
                    <div class="flex items-center justify-between text-xs text-surface-400">
                        <span>{{ pattern.occurrence_count }} occurrences</span>
                        <span>{{ pattern.detected_at }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-6">
                <CheckCircle class="w-10 h-10 text-success-300 mx-auto mb-2" />
                <p class="text-sm text-surface-400">No error patterns detected — excellent!</p>
            </div>
        </div>

        <!-- Mock exam history -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <h3 class="text-sm font-semibold text-surface-700 mb-4 flex items-center gap-2">
                <ScrollText class="w-4 h-4 text-primary-500" />
                Mock Exam History
            </h3>
            <div v-if="mockHistory.length" class="overflow-x-auto -mx-5">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left py-2 px-5 text-xs font-medium text-surface-400 uppercase">Exam</th>
                            <th class="text-center py-2 px-3 text-xs font-medium text-surface-400 uppercase">Score</th>
                            <th class="text-center py-2 px-3 text-xs font-medium text-surface-400 uppercase">Result</th>
                            <th class="text-center py-2 px-3 text-xs font-medium text-surface-400 uppercase">Time</th>
                            <th class="text-right py-2 px-5 text-xs font-medium text-surface-400 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="mock in mockHistory" :key="mock.id" class="border-b border-surface-50 hover:bg-surface-50">
                            <td class="py-3 px-5 font-medium text-surface-700">{{ mock.title }}</td>
                            <td class="py-3 px-3 text-center">
                                <span :class="['font-semibold', mock.passed ? 'text-success-600' : 'text-danger-600']">
                                    {{ mock.score }}%
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium', mock.passed ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700']">
                                    {{ mock.passed ? 'Passed' : 'Failed' }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center text-surface-500">{{ formatTime(mock.time_spent) }}</td>
                            <td class="py-3 px-5 text-right text-surface-400">{{ mock.completed_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-center py-6">
                <ScrollText class="w-10 h-10 text-surface-200 mx-auto mb-2" />
                <p class="text-sm text-surface-400">No mock exams completed yet.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Target, TrendingUp, TrendingDown, Minus, Lightbulb, AlertTriangle,
    FileText, ScrollText, CheckCircle, Zap, Clock, Eye, Brain,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    masteryMap: { type: Object, default: () => ({}) },
    readiness: { type: Object, default: null },
    trends: { type: Array, default: () => [] },
    mockHistory: { type: Array, default: () => [] },
    errorPatterns: { type: Array, default: () => [] },
    coachInsight: { type: String, default: null },
    enrolledCourses: { type: Array, default: () => [] },
    selectedCourse: { type: Number, default: null },
});

const selectedCourse = computed({
    get: () => props.selectedCourse,
    set: () => {},
});

function filterByCourse(e) {
    const val = e.target.value;
    router.get('/analytics', val ? { course_id: val } : {}, { preserveState: true });
}

// Flatten mastery map across courses
const masteryDomains = computed(() => {
    return Object.values(props.masteryMap).flat();
});

const passLikelihoodLabel = computed(() => {
    const map = { very_likely: 'Very likely to pass', likely: 'Likely to pass', possible: 'Possible to pass', unlikely: 'Unlikely to pass' };
    return map[props.readiness?.pass_likelihood] || '';
});

const confidenceColor = computed(() => {
    const map = { high: 'bg-success-400', medium: 'bg-warning-400', low: 'bg-danger-400' };
    return map[props.readiness?.confidence_band] || 'bg-surface-400';
});

function masteryColor(score) {
    if (score >= 75) return 'text-success-600';
    if (score >= 50) return 'text-warning-600';
    return 'text-danger-600';
}

function masteryBarColor(score) {
    if (score >= 75) return 'bg-success-400';
    if (score >= 50) return 'bg-warning-400';
    return 'bg-danger-400';
}

const patternLabels = {
    careless_fast: 'Careless Mistakes',
    overthinking_slow: 'Overthinking',
    confidence_mismatch: 'Overconfidence',
    concept_confusion: 'Concept Confusion',
    qualifier_misread: 'Qualifier Misread',
    elimination_failure: 'Elimination Failure',
};
function patternLabel(type) { return patternLabels[type] || type; }

function patternIcon(type) {
    const map = { careless_fast: Zap, overthinking_slow: Clock, confidence_mismatch: Eye, concept_confusion: Brain, qualifier_misread: AlertTriangle, elimination_failure: Target };
    return map[type] || AlertTriangle;
}

function patternIconBg(type) {
    const map = { careless_fast: 'bg-warning-500', overthinking_slow: 'bg-primary-500', confidence_mismatch: 'bg-danger-500', concept_confusion: 'bg-accent-500', qualifier_misread: 'bg-orange-500', elimination_failure: 'bg-surface-500' };
    return map[type] || 'bg-surface-500';
}

function formatTime(seconds) {
    if (!seconds) return '—';
    const m = Math.floor(seconds / 60);
    return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`;
}
</script>

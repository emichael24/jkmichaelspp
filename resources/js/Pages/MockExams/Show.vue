<template>
    <div class="max-w-4xl mx-auto">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-surface-500 mb-6">
            <Link :href="route('mock-exams.index')" class="hover:text-primary-600">Mock Exams</Link>
            <span>/</span>
            <span class="text-surface-700">{{ mockExam.title }}</span>
        </nav>

        <!-- Header card -->
        <div class="bg-gradient-to-br from-primary-600 to-primary-800 rounded-2xl p-8 text-white mb-8">
            <div class="flex items-start gap-2 mb-3">
                <span :class="typeBadgeClass" class="text-xs font-medium px-2.5 py-0.5 rounded-full">
                    {{ typeLabel }}
                </span>
                <span v-if="mockExam.is_exam_simulation" class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-white/20 text-white">
                    Exam Simulation
                </span>
            </div>
            <h1 class="text-3xl font-display font-bold">{{ mockExam.title }}</h1>
            <p class="text-primary-100 mt-2">{{ mockExam.course?.title }}</p>
            <p v-if="mockExam.description" class="text-primary-200 mt-3 text-sm leading-relaxed">{{ mockExam.description }}</p>
        </div>

        <!-- Stats grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <FileText class="w-5 h-5 text-primary-500 mx-auto mb-1" />
                <p class="text-2xl font-bold text-surface-900">{{ mockExam.question_count }}</p>
                <p class="text-xs text-surface-500">Questions</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <Clock class="w-5 h-5 text-accent-500 mx-auto mb-1" />
                <p class="text-2xl font-bold text-surface-900">{{ mockExam.time_limit_minutes }}</p>
                <p class="text-xs text-surface-500">Minutes</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <Target class="w-5 h-5 text-success-500 mx-auto mb-1" />
                <p class="text-2xl font-bold text-surface-900">{{ mockExam.passing_score_pct }}%</p>
                <p class="text-xs text-surface-500">Passing Score</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 text-center">
                <Pause class="w-5 h-5 mx-auto mb-1" :class="mockExam.allow_pause ? 'text-success-500' : 'text-surface-300'" />
                <p class="text-2xl font-bold text-surface-900">{{ mockExam.allow_pause ? 'Yes' : 'No' }}</p>
                <p class="text-xs text-surface-500">Pause Allowed</p>
            </div>
        </div>

        <!-- Paused attempt alert -->
        <div v-if="pausedAttempt" class="mb-6 bg-warning-50 border border-warning-200 rounded-xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <PlayCircle class="w-5 h-5 text-warning-600" />
                <div>
                    <p class="text-sm font-semibold text-warning-800">You have a paused attempt</p>
                    <p class="text-xs text-warning-600">Started {{ new Date(pausedAttempt.started_at).toLocaleDateString() }}</p>
                </div>
            </div>
            <button
                @click="resumeExam"
                :disabled="loading"
                class="px-4 py-2 bg-warning-600 text-white text-sm font-semibold rounded-xl hover:bg-warning-700 transition"
            >
                Resume
            </button>
        </div>

        <!-- Start button -->
        <div class="bg-white rounded-2xl border border-surface-200 p-8 text-center mb-8">
            <Shield class="w-12 h-12 text-primary-500 mx-auto mb-4" />
            <h2 class="text-xl font-bold text-surface-900 mb-2">Ready to begin?</h2>
            <p class="text-sm text-surface-500 mb-6 max-w-md mx-auto">
                Once started, the timer will begin counting down.
                <span v-if="!mockExam.allow_pause">This exam <strong>cannot be paused</strong>.</span>
                <span v-else>You can pause and resume this exam.</span>
            </p>
            <button
                @click="startExam"
                :disabled="loading"
                class="inline-flex items-center gap-2 px-8 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition text-lg shadow-lg shadow-primary-500/25"
            >
                <Play class="w-5 h-5" />
                {{ loading ? 'Starting...' : 'Start Mock Exam' }}
            </button>
        </div>

        <!-- Previous attempts -->
        <div v-if="previousAttempts.length" class="bg-white rounded-2xl border border-surface-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-surface-100">
                <h3 class="font-semibold text-surface-900">Previous Attempts</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-surface-50 text-surface-600 text-left">
                    <tr>
                        <th class="px-6 py-3 font-medium">Date</th>
                        <th class="px-6 py-3 font-medium">Score</th>
                        <th class="px-6 py-3 font-medium">Correct</th>
                        <th class="px-6 py-3 font-medium">Time</th>
                        <th class="px-6 py-3 font-medium">Result</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    <tr v-for="att in previousAttempts" :key="att.id" class="hover:bg-surface-50">
                        <td class="px-6 py-3 text-surface-700">{{ new Date(att.completed_at).toLocaleDateString() }}</td>
                        <td class="px-6 py-3">
                            <span class="font-bold" :class="att.score_pct >= mockExam.passing_score_pct ? 'text-success-600' : 'text-danger-600'">
                                {{ att.score_pct }}%
                            </span>
                        </td>
                        <td class="px-6 py-3 text-surface-700">{{ att.correct_count }}/{{ att.total_count }}</td>
                        <td class="px-6 py-3 text-surface-700">{{ formatTime(att.time_spent_seconds) }}</td>
                        <td class="px-6 py-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                                  :class="att.score_pct >= mockExam.passing_score_pct ? 'bg-success-100 text-success-700' : 'bg-danger-100 text-danger-700'">
                                {{ att.score_pct >= mockExam.passing_score_pct ? 'Pass' : 'Fail' }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <Link :href="route('mock-exams.report', att.id)" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                View Report
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Play, PlayCircle, FileText, Clock, Target, Pause, Shield } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    mockExam: Object,
    previousAttempts: Array,
    pausedAttempt: Object,
})

const loading = ref(false)

function startExam() {
    loading.value = true
    router.post(route('mock-exams.start', props.mockExam.id))
}

function resumeExam() {
    loading.value = true
    router.post(route('mock-exams.resume', props.pausedAttempt.id))
}

const typeLabel = { full_length: 'Full Length', mini_mock: 'Mini Mock', domain_focused: 'Domain Focused' }[props.mockExam.type] || props.mockExam.type
const typeBadgeClass = { full_length: 'bg-white/20', mini_mock: 'bg-white/20', domain_focused: 'bg-white/20' }[props.mockExam.type]

function formatTime(seconds) {
    if (!seconds) return '-'
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return `${m}m ${s}s`
}
</script>

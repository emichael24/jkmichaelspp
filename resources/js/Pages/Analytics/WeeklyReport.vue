<template>
    <Head title="Weekly Report" />

    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Weekly Report</h1>
                <p class="text-surface-500 text-sm mt-1">{{ report.period }}</p>
            </div>
            <Link href="/analytics" class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium">
                <ArrowLeft class="w-4 h-4" />
                Back to Analytics
            </Link>
        </div>

        <!-- Summary stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-surface-200 p-4">
                <p class="text-xs text-surface-400 font-medium">Hours Studied</p>
                <p class="text-2xl font-bold text-surface-900 mt-1">{{ report.hours_studied }}</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4">
                <p class="text-xs text-surface-400 font-medium">Questions</p>
                <p class="text-2xl font-bold text-surface-900 mt-1">{{ report.questions_answered }}</p>
                <p class="text-xs text-surface-400 mt-0.5">{{ report.accuracy_pct }}% accuracy</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4">
                <p class="text-xs text-surface-400 font-medium">Days Active</p>
                <p class="text-2xl font-bold text-surface-900 mt-1">{{ report.days_active }}/7</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4">
                <p class="text-xs text-surface-400 font-medium">XP Earned</p>
                <p class="text-2xl font-bold text-xp-600 mt-1">{{ report.xp_earned }}</p>
            </div>
        </div>

        <!-- Activity breakdown -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Activity Breakdown</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-primary-50 flex items-center justify-center">
                        <BookOpen class="w-5 h-5 text-primary-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-surface-900">{{ report.lessons_completed }}</p>
                        <p class="text-xs text-surface-400">Lessons completed</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-accent-50 flex items-center justify-center">
                        <ClipboardCheck class="w-5 h-5 text-accent-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-surface-900">{{ report.quizzes_completed }}</p>
                        <p class="text-xs text-surface-400">Quizzes completed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Improvement areas -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Focus Areas</h3>
            <div v-if="report.improvement_areas?.length" class="space-y-3">
                <div
                    v-for="area in report.improvement_areas"
                    :key="area.domain"
                    class="flex items-center justify-between p-3 rounded-xl bg-surface-50"
                >
                    <div class="flex items-center gap-3">
                        <div :class="['w-2 h-2 rounded-full', area.trend === 'improving' ? 'bg-success-400' : 'bg-danger-400']" />
                        <span class="text-sm font-medium text-surface-700">{{ area.domain }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="['text-sm font-semibold', area.accuracy < 50 ? 'text-danger-600' : 'text-warning-600']">
                            {{ area.accuracy }}%
                        </span>
                        <TrendingUp v-if="area.trend === 'improving'" class="w-3.5 h-3.5 text-success-500" />
                        <TrendingDown v-else class="w-3.5 h-3.5 text-danger-500" />
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400">No specific improvement areas this week.</p>
        </div>

        <!-- Recommendation -->
        <div class="bg-gradient-to-r from-primary-50 to-accent-50 rounded-2xl p-5 border border-primary-100">
            <div class="flex items-start gap-3">
                <Lightbulb class="w-5 h-5 text-primary-600 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-primary-800">Next Week's Focus</p>
                    <p class="text-sm text-primary-700 mt-1">{{ report.focus_recommendation }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft, BookOpen, ClipboardCheck, TrendingUp, TrendingDown, Lightbulb,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

defineProps({
    report: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head title="Internship Health" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-display font-bold text-surface-900">Internship Health Overview</h1>
            <p class="text-sm text-surface-500">Monitor learner progress and identify at-risk enrollments</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-primary-600">{{ metrics.length }}</p>
                <p class="text-xs text-surface-500">Active Enrollments</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-warning-600">{{ atRiskCount }}</p>
                <p class="text-xs text-surface-500">At Risk</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-success-600">{{ avgProgress }}%</p>
                <p class="text-xs text-surface-500">Avg Progress</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-accent-600">{{ totalOverdue }}</p>
                <p class="text-xs text-surface-500">Overdue Tasks</p>
            </div>
        </div>

        <!-- Enrollments Table -->
        <div class="bg-white rounded-xl border border-surface-200/60 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface-50 text-left">
                        <th class="px-4 py-3 font-medium text-surface-600">Learner</th>
                        <th class="px-4 py-3 font-medium text-surface-600">Track</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Week</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Progress</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Overdue</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Score</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in metrics" :key="m.enrollment_id" class="border-t border-surface-100 hover:bg-surface-50">
                        <td class="px-4 py-3 font-medium text-surface-900">{{ m.learner }}</td>
                        <td class="px-4 py-3 text-surface-600">{{ m.track }}</td>
                        <td class="px-4 py-3 text-center text-surface-600">{{ m.current_week }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-surface-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" :class="m.progress_pct >= 70 ? 'bg-success-500' : m.progress_pct >= 40 ? 'bg-warning-500' : 'bg-danger-500'"
                                         :style="{ width: m.progress_pct + '%' }"></div>
                                </div>
                                <span class="text-xs text-surface-500 w-8 text-right">{{ m.progress_pct }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="m.overdue_tasks > 0" class="text-danger-600 font-medium">{{ m.overdue_tasks }}</span>
                            <span v-else class="text-surface-400">0</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="m.overall_score != null" class="font-medium" :class="m.overall_score >= 3.5 ? 'text-success-600' : m.overall_score >= 2.5 ? 'text-warning-600' : 'text-danger-600'">
                                {{ m.overall_score }}
                            </span>
                            <span v-else class="text-surface-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(m)]">
                                {{ statusLabel(m) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!metrics.length" class="p-12 text-center text-surface-400">
                No active internship enrollments.
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    enrollments: { type: Object, default: () => ({}) },
    metrics: { type: Array, default: () => [] },
});

const atRiskCount = computed(() => props.metrics.filter(m => m.overdue_tasks > 0 || (m.overall_score != null && m.overall_score < 2.5)).length);
const avgProgress = computed(() => {
    if (!props.metrics.length) return 0;
    return Math.round(props.metrics.reduce((s, m) => s + (m.progress_pct || 0), 0) / props.metrics.length);
});
const totalOverdue = computed(() => props.metrics.reduce((s, m) => s + (m.overdue_tasks || 0), 0));

function statusLabel(m) {
    if (m.overdue_tasks > 0) return 'At Risk';
    if (m.progress_pct >= 80) return 'On Track';
    if (m.progress_pct >= 40) return 'In Progress';
    return 'Starting';
}

function statusBadge(m) {
    if (m.overdue_tasks > 0) return 'bg-danger-50 text-danger-700';
    if (m.progress_pct >= 80) return 'bg-success-50 text-success-700';
    if (m.progress_pct >= 40) return 'bg-primary-50 text-primary-700';
    return 'bg-surface-100 text-surface-600';
}
</script>

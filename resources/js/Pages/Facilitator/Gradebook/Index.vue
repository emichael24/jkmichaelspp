<template>
    <Head :title="`${cohort.name} Gradebook`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Link :href="`/facilitator/cohorts/${cohort.id}`" class="rounded-lg p-2 transition-colors hover:bg-surface-100">
                    <ArrowLeft class="h-5 w-5 text-surface-500" />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-surface-900">Gradebook</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }} - {{ cohort.course?.title }}</p>
                </div>
            </div>

            <Link
                :href="`/facilitator/cohorts/${cohort.id}/assignments`"
                class="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-3 py-1.5 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-50"
            >
                <BookOpen class="h-4 w-4" />
                Assignments
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Learners</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ summary.learners }}</p>
                <p class="mt-1 text-sm text-surface-500">{{ summary.assignments }} tracked assignments</p>
            </div>
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Average Progress</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ summary.average_progress }}%</p>
                <p class="mt-1 text-sm text-surface-500">Course completion across the cohort</p>
            </div>
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Average Grade</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ formatGrade(summary.average_grade) }}</p>
                <p class="mt-1 text-sm text-surface-500">Graded assignment average</p>
            </div>
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Quiz Average</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ formatGrade(summary.average_quiz_score) }}</p>
                <p class="mt-1 text-sm text-surface-500">Completed quizzes only</p>
            </div>
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Mock Average</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ formatGrade(summary.average_mock_score) }}</p>
                <p class="mt-1 text-sm text-surface-500">Completed mock exams</p>
            </div>
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Attendance</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ formatPercent(summary.attendance_rate) }}</p>
                <p class="mt-1 text-sm text-surface-500">{{ summary.meetings }} completed meetings</p>
            </div>
            <div class="rounded-xl border border-surface-200/60 bg-white p-5">
                <p class="text-xs uppercase tracking-wider text-surface-400">Review Queue</p>
                <p class="mt-2 text-3xl font-bold text-surface-900">{{ summary.pending_reviews }}</p>
                <p class="mt-1 text-sm text-surface-500">{{ summary.missing_submissions }} overdue missing submissions</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-surface-200/60 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-surface-200">
                    <thead class="bg-surface-50">
                        <tr>
                            <th class="sticky left-0 z-10 min-w-[260px] bg-surface-50 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">
                                Learner
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">Progress</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">Average</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">Quiz Avg</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">Mock Avg</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500">Attendance</th>
                            <th
                                v-for="assignment in assignments"
                                :key="assignment.id"
                                class="min-w-[180px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-surface-500"
                            >
                                <div>{{ assignment.title }}</div>
                                <div class="mt-1 text-[11px] font-normal normal-case text-surface-400">
                                    Due {{ formatDate(assignment.due_date) }}
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="learner in learners" :key="learner.id" class="align-top">
                            <td class="sticky left-0 z-10 bg-white px-4 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-surface-900">{{ learner.name }}</p>
                                    <p class="text-xs text-surface-500">{{ learner.email }}</p>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                    <span class="rounded-full bg-primary-50 px-2 py-1 font-medium text-primary-700">
                                        {{ learner.submitted_count }} submitted
                                    </span>
                                    <span class="rounded-full bg-success-50 px-2 py-1 font-medium text-success-700">
                                        {{ learner.graded_count }} graded
                                    </span>
                                    <span class="rounded-full bg-warning-50 px-2 py-1 font-medium text-warning-700">
                                        {{ learner.pending_count }} pending
                                    </span>
                                    <span v-if="learner.missing_count" class="rounded-full bg-danger-50 px-2 py-1 font-medium text-danger-700">
                                        {{ learner.missing_count }} missing
                                    </span>
                                </div>
                                <p v-if="learner.last_submitted_at" class="mt-3 text-xs text-surface-400">
                                    Last submitted {{ formatDateTime(learner.last_submitted_at) }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <div class="min-w-[100px]">
                                    <p class="text-sm font-semibold text-surface-900">{{ learner.progress_pct }}%</p>
                                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-surface-100">
                                        <div class="h-full rounded-full bg-primary-500" :style="{ width: `${learner.progress_pct}%` }" />
                                    </div>
                                    <p class="mt-2 text-xs capitalize text-surface-400">{{ learner.enrollment_status.replace('_', ' ') }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-semibold text-surface-900">{{ formatGrade(learner.average_score_pct) }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-semibold text-surface-900">{{ formatGrade(learner.quiz_avg_pct) }}</p>
                                <p class="mt-1 text-xs text-surface-400">{{ learner.quiz_attempts || 0 }} attempts</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-semibold text-surface-900">{{ formatGrade(learner.mock_avg_pct) }}</p>
                                <p class="mt-1 text-xs text-surface-400">{{ learner.mock_attempts || 0 }} attempts</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-semibold text-surface-900">{{ formatPercent(learner.attendance_rate) }}</p>
                                <p class="mt-1 text-xs text-surface-400">{{ learner.attendance_attended || 0 }} / {{ learner.attendance_total || 0 }}</p>
                            </td>
                            <td v-for="cell in learner.assignments" :key="`${learner.id}-${cell.assignment_id}`" class="px-4 py-4">
                                <div :class="['rounded-xl border px-3 py-3 text-sm', statusClasses(cell.status)]">
                                    <p class="font-semibold capitalize">{{ statusLabel(cell.status) }}</p>
                                    <p v-if="cell.score !== null" class="mt-1">
                                        {{ cell.score }} <span class="text-xs opacity-80">({{ cell.score_pct }}%)</span>
                                    </p>
                                    <p v-if="cell.submitted_at" class="mt-2 text-xs opacity-80">
                                        Submitted {{ formatDateTime(cell.submitted_at) }}
                                    </p>
                                    <p v-if="cell.graded_at" class="mt-1 text-xs opacity-80">
                                        Graded {{ formatDateTime(cell.graded_at) }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

defineProps({
    cohort: { type: Object, required: true },
    summary: { type: Object, required: true },
    assignments: { type: Array, default: () => [] },
    learners: { type: Array, default: () => [] },
});

function formatGrade(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return 'No grades yet';
    }

    return `${Number(value).toFixed(1)}%`;
}

function formatPercent(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }

    return `${Number(value).toFixed(1)}%`;
}

function formatDate(value) {
    if (!value) {
        return 'TBD';
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function statusLabel(status) {
    switch (status) {
        case 'graded':
            return 'Graded';
        case 'pending':
            return 'Pending review';
        case 'returned':
            return 'Returned';
        case 'missing':
            return 'Missing';
        default:
            return 'Not submitted';
    }
}

function statusClasses(status) {
    switch (status) {
        case 'graded':
            return 'border-success-200 bg-success-50 text-success-800';
        case 'pending':
            return 'border-warning-200 bg-warning-50 text-warning-800';
        case 'returned':
            return 'border-accent-200 bg-accent-50 text-accent-800';
        case 'missing':
            return 'border-danger-200 bg-danger-50 text-danger-800';
        default:
            return 'border-surface-200 bg-surface-50 text-surface-600';
    }
}
</script>

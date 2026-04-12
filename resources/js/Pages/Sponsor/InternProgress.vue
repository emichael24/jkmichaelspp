<template>
    <Head :title="`Intern Progress: ${enrollment.user?.first_name}`" />

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="/sponsor/review-queue" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <div>
                <h1 class="text-xl font-display font-bold text-surface-900">{{ enrollment.user?.first_name }} {{ enrollment.user?.last_name }}</h1>
                <p class="text-sm text-surface-500">{{ enrollment.track?.title }} · {{ enrollment.project_title || 'No project assigned' }}</p>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-primary-600">{{ taskStats.total }}</p>
                <p class="text-xs text-surface-500">Total Tasks</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-success-600">{{ taskStats.completed }}</p>
                <p class="text-xs text-surface-500">Completed</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-warning-600">{{ taskStats.inReview }}</p>
                <p class="text-xs text-surface-500">In Review</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-accent-600">{{ enrollment.overall_score ?? '—' }}</p>
                <p class="text-xs text-surface-500">Overall Score</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-surface-700">Overall Progress</span>
                <span class="text-sm font-bold text-primary-600">{{ progressPct }}%</span>
            </div>
            <div class="w-full bg-surface-100 rounded-full h-3">
                <div class="bg-gradient-to-r from-primary-500 to-accent-500 h-3 rounded-full transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>
        </div>

        <!-- Milestones -->
        <div v-if="enrollment.track?.milestones?.length" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="font-semibold text-surface-900 mb-4">Milestones</h2>
            <div class="space-y-3">
                <div v-for="ms in enrollment.track.milestones" :key="ms.id" class="flex items-start gap-3 p-3 bg-surface-50 rounded-lg">
                    <span class="text-xs font-bold text-primary-600 bg-primary-50 px-2 py-1 rounded shrink-0">W{{ ms.week_number }}</span>
                    <div>
                        <p class="text-sm font-medium text-surface-900">{{ ms.title }}</p>
                        <p v-if="ms.description" class="text-xs text-surface-500 mt-0.5">{{ ms.description }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task List -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="font-semibold text-surface-900 mb-4">Tasks ({{ enrollment.tasks?.length || 0 }})</h2>
            <div v-if="enrollment.tasks?.length" class="space-y-2">
                <div v-for="task in enrollment.tasks" :key="task.id" class="flex items-center justify-between p-3 bg-surface-50 rounded-lg">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-surface-900">{{ task.title }}</p>
                        <div class="flex gap-2 mt-1">
                            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', typeColor(task.type)]">{{ task.type }}</span>
                            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', statusColor(task.status)]">{{ formatStatus(task.status) }}</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-4">
                        <span v-if="task.submissions?.length" class="text-xs text-surface-400">
                            {{ task.submissions.length }} submission(s)
                        </span>
                        <p v-if="task.submissions?.[0]?.latest_review?.overall_score != null" class="text-sm font-bold text-accent-600">
                            {{ task.submissions[0].latest_review.overall_score }}/5
                        </p>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm italic text-surface-400">No tasks assigned yet.</p>
        </div>

        <!-- Scorecards -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-surface-900">Scorecards</h2>
                <a :href="`/sponsor/interns/${enrollment.id}/scorecard`" class="text-xs text-primary-600 font-medium hover:underline">+ New Scorecard</a>
            </div>
            <div v-if="scorecards.length" class="space-y-2">
                <div v-for="sc in scorecards" :key="sc.id" class="flex items-center justify-between p-3 bg-surface-50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-surface-900 capitalize">{{ sc.period_type }} #{{ sc.period_number }}</p>
                        <p v-if="sc.comments" class="text-xs text-surface-500 mt-0.5 line-clamp-1">{{ sc.comments }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-accent-600">{{ sc.overall_score }}/5</p>
                        <span v-if="sc.ready_for_next" class="text-[10px] text-success-600 font-medium">Ready</span>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm italic text-surface-400">No scorecards yet.</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

const props = defineProps({
    enrollment: { type: Object, required: true },
    scorecards: { type: Array, default: () => [] },
});

const taskStats = computed(() => {
    const tasks = props.enrollment.tasks || [];
    return {
        total: tasks.length,
        completed: tasks.filter(t => ['done', 'approved'].includes(t.status)).length,
        inReview: tasks.filter(t => t.status === 'in_review').length,
    };
});

const progressPct = computed(() => {
    if (!taskStats.value.total) return 0;
    return Math.round((taskStats.value.completed / taskStats.value.total) * 100);
});

function typeColor(type) {
    const map = { daily: 'bg-primary-50 text-primary-700', weekly: 'bg-accent-50 text-accent-700', monthly: 'bg-warning-50 text-warning-700' };
    return map[type] || 'bg-surface-100 text-surface-500';
}

function statusColor(status) {
    const map = {
        backlog: 'bg-surface-100 text-surface-500', this_week: 'bg-primary-50 text-primary-700',
        today: 'bg-warning-50 text-warning-700', in_progress: 'bg-accent-50 text-accent-700',
        in_review: 'bg-primary-50 text-primary-600', needs_revision: 'bg-danger-50 text-danger-700',
        done: 'bg-success-50 text-success-700', approved: 'bg-success-50 text-success-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatStatus(s) {
    return s?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
}
</script>

<template>
    <Head title="Internship Dashboard" />

    <div class="space-y-6">
        <!-- No Active Enrollment -->
        <div v-if="!enrollment" class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Briefcase class="w-14 h-14 text-surface-300 mx-auto mb-4" />
            <h2 class="text-lg font-semibold text-surface-700 mb-2">No Active Internship</h2>
            <p class="text-sm text-surface-500">You aren't enrolled in any internship track yet.</p>
        </div>

        <template v-else>
            <!-- Header -->
            <div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-2xl p-6 text-white">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-primary-200 text-xs font-medium uppercase tracking-wider">{{ enrollment.track?.title }}</p>
                        <h1 class="text-xl font-display font-bold mt-1">{{ enrollment.project_title || 'My Internship' }}</h1>
                        <p class="text-primary-200 text-sm mt-1">
                            Week {{ current_week }} of {{ enrollment.track?.duration_weeks || '—' }}
                            &middot; {{ formatDate(enrollment.start_date) }} – {{ formatDate(enrollment.end_date) }}
                        </p>
                    </div>
                    <a :href="`/internship/${enrollment.id}/tasks`" class="px-4 py-2 bg-white/20 text-white rounded-lg text-sm font-medium hover:bg-white/30 transition-colors">
                        Open Task Board
                    </a>
                </div>

                <!-- Progress bar -->
                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs text-primary-200 mb-1">
                        <span>Overall Progress</span>
                        <span>{{ stats.progress_pct }}%</span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-2">
                        <div class="bg-white rounded-full h-2 transition-all" :style="{ width: stats.progress_pct + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                    <p class="text-2xl font-bold text-surface-900">{{ stats.total }}</p>
                    <p class="text-xs text-surface-500">Total Tasks</p>
                </div>
                <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                    <p class="text-2xl font-bold text-success-600">{{ stats.done }}</p>
                    <p class="text-xs text-surface-500">Completed</p>
                </div>
                <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                    <p class="text-2xl font-bold text-warning-600">{{ stats.due_today }}</p>
                    <p class="text-xs text-surface-500">Due Today</p>
                </div>
                <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                    <p class="text-2xl font-bold text-danger-600">{{ stats.overdue }}</p>
                    <p class="text-xs text-surface-500">Overdue</p>
                </div>
            </div>

            <!-- Milestones Timeline -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <h2 class="font-semibold text-surface-900 mb-4">Milestones</h2>
                <div v-if="milestones.length" class="space-y-3">
                    <div v-for="ms in milestones" :key="ms.id" class="flex items-start gap-3">
                        <div :class="[
                            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                            ms.week_number <= current_week ? 'bg-primary-100 text-primary-700' : 'bg-surface-100 text-surface-400',
                        ]">
                            W{{ ms.week_number }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-surface-900">{{ ms.title }}</p>
                            <p v-if="ms.description" class="text-xs text-surface-500 mt-0.5 line-clamp-2">{{ ms.description }}</p>
                            <div v-if="ms.deliverables?.length" class="flex flex-wrap gap-1 mt-1">
                                <span v-for="(d, di) in ms.deliverables" :key="di" class="px-2 py-0.5 bg-surface-50 rounded text-xs text-surface-500">{{ d }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-400 italic">No milestones defined yet.</p>
            </div>

            <!-- Quick Kanban Preview -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-surface-900">Task Board</h2>
                    <a :href="`/internship/${enrollment.id}/tasks`" class="text-xs text-primary-600 font-medium hover:underline">
                        View Full Board →
                    </a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div v-for="col in previewColumns" :key="col.key">
                        <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider mb-2">{{ col.label }} ({{ (kanban[col.key] || []).length }})</p>
                        <div class="space-y-2">
                            <div v-for="task in (kanban[col.key] || []).slice(0, 3)" :key="task.id"
                                 class="p-2 bg-surface-50 rounded-lg border border-surface-100">
                                <p class="text-xs font-medium text-surface-700 line-clamp-2">{{ task.title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', typeBadge(task.type)]">{{ task.type }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', priorityBadge(task.priority)]">{{ task.priority }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Info -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <h2 class="font-semibold text-surface-900 mb-3">Team</h2>
                <div class="flex flex-wrap gap-4">
                    <div v-if="enrollment.sponsor" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-accent-100 rounded-full flex items-center justify-center text-xs font-bold text-accent-700">
                            {{ enrollment.sponsor.first_name?.[0] }}{{ enrollment.sponsor.last_name?.[0] }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-900">{{ enrollment.sponsor.first_name }} {{ enrollment.sponsor.last_name }}</p>
                            <p class="text-xs text-surface-400">Sponsor</p>
                        </div>
                    </div>
                    <div v-if="enrollment.team_lead" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center text-xs font-bold text-primary-700">
                            {{ enrollment.team_lead.first_name?.[0] }}{{ enrollment.team_lead.last_name?.[0] }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-900">{{ enrollment.team_lead.first_name }} {{ enrollment.team_lead.last_name }}</p>
                            <p class="text-xs text-surface-400">Team Lead</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { Briefcase } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    enrollment: { type: Object, default: null },
    kanban: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, done: 0, overdue: 0, due_today: 0, progress_pct: 0 }) },
    milestones: { type: Array, default: () => [] },
    current_week: { type: Number, default: 1 },
});

const previewColumns = [
    { key: 'today', label: 'Today' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'in_review', label: 'In Review' },
    { key: 'done', label: 'Done' },
];

function typeBadge(type) {
    const map = { daily: 'bg-primary-50 text-primary-700', weekly: 'bg-accent-50 text-accent-700', monthly: 'bg-warning-50 text-warning-700' };
    return map[type] || 'bg-surface-100 text-surface-600';
}

function priorityBadge(priority) {
    const map = { low: 'bg-surface-100 text-surface-600', medium: 'bg-primary-50 text-primary-600', high: 'bg-warning-50 text-warning-700', urgent: 'bg-danger-50 text-danger-700' };
    return map[priority] || map.medium;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

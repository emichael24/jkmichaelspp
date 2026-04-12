<template>
    <Head title="My Interns" />

    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-xl font-display font-bold text-surface-900">My Interns</h1>
            <p class="text-sm text-surface-500 mt-1">All interns assigned to you as sponsor</p>
        </div>

        <!-- Interns list -->
        <div v-if="enrollments.data?.length" class="space-y-4">
            <div
                v-for="enr in enrollments.data"
                :key="enr.id"
                class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-sm shrink-0">
                            {{ getInitials(enr.user) }}
                        </div>
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ enr.user?.first_name }} {{ enr.user?.last_name }}</h3>
                            <p class="text-sm text-surface-500 mt-0.5">{{ enr.track?.title || 'Internship Track' }}</p>
                            <div class="flex items-center gap-3 mt-2 text-xs text-surface-400">
                                <span>{{ formatDate(enr.start_date) }} – {{ formatDate(enr.end_date) }}</span>
                                <span :class="statusClass(enr.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ enr.status }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a :href="`/sponsor/interns/${enr.id}/progress`" class="px-3 py-1.5 bg-primary-50 text-primary-700 rounded-lg text-xs font-medium hover:bg-primary-100 transition-colors">
                            View Progress
                        </a>
                        <a :href="`/sponsor/interns/${enr.id}/scorecard`" class="px-3 py-1.5 bg-accent-50 text-accent-700 rounded-lg text-xs font-medium hover:bg-accent-100 transition-colors">
                            Scorecard
                        </a>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex-1 bg-surface-100 rounded-full h-2">
                        <div
                            class="bg-primary-500 h-2 rounded-full transition-all"
                            :style="{ width: taskProgress(enr) + '%' }"
                        />
                    </div>
                    <span class="text-xs text-surface-500 font-medium whitespace-nowrap">
                        {{ enr.completed_tasks_count || 0 }}/{{ enr.tasks_count || 0 }} tasks
                    </span>
                    <span v-if="enr.pending_submissions_count > 0" class="text-xs text-warning-600 font-medium whitespace-nowrap">
                        · {{ enr.pending_submissions_count }} pending review
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Users class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <h3 class="font-semibold text-surface-900 mb-1">No interns assigned</h3>
            <p class="text-sm text-surface-500">You don't have any interns assigned to you yet.</p>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

defineProps({
    enrollments: { type: Object, default: () => ({ data: [] }) },
});

function getInitials(user) {
    if (!user) return '?';
    return ((user.first_name?.[0] || '') + (user.last_name?.[0] || '')).toUpperCase() || '?';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function taskProgress(enr) {
    const total = enr.tasks_count || 0;
    if (total === 0) return 0;
    return Math.round(((enr.completed_tasks_count || 0) / total) * 100);
}

function statusClass(status) {
    const map = {
        enrolled: 'bg-primary-50 text-primary-700',
        active: 'bg-success-50 text-success-700',
        completed: 'bg-accent-50 text-accent-700',
        withdrawn: 'bg-surface-100 text-surface-500',
        failed: 'bg-danger-50 text-danger-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}
</script>

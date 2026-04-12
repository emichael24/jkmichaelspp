<template>
    <Head title="My Cohorts" />

    <div class="space-y-6">
        <h1 class="text-xl font-display font-bold text-surface-900">My Cohorts</h1>

        <div v-if="cohorts.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="cohort in cohorts"
                :key="cohort.id"
                class="bg-white rounded-xl border border-surface-200/60 p-5"
            >
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-surface-900">{{ cohort.name }}</h3>
                        <p class="text-sm text-surface-500">{{ cohort.course?.title }}</p>
                    </div>
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium capitalize', statusBadge(cohort.status)]">
                        {{ cohort.status }}
                    </span>
                </div>

                <div class="space-y-2 text-sm text-surface-500">
                    <div v-if="cohort.facilitator" class="flex items-center gap-2">
                        <User class="w-4 h-4 text-surface-400" />
                        <span>{{ cohort.facilitator.first_name }} {{ cohort.facilitator.last_name }}</span>
                    </div>
                    <div v-if="cohort.start_date" class="flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-surface-400" />
                        <span>{{ formatDate(cohort.start_date) }} – {{ formatDate(cohort.end_date) }}</span>
                    </div>
                </div>

                <p v-if="cohort.description" class="text-xs text-surface-400 mt-3 line-clamp-2">{{ cohort.description }}</p>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Users class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <h2 class="text-lg font-semibold text-surface-700 mb-2">No Cohorts</h2>
            <p class="text-sm text-surface-500">You aren't enrolled in any cohorts yet.</p>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { User, Calendar, Users } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    cohorts: { type: Array, default: () => [] },
});

function statusBadge(status) {
    const map = {
        active: 'bg-success-50 text-success-700',
        draft: 'bg-surface-100 text-surface-500',
        archived: 'bg-surface-100 text-surface-400',
        completed: 'bg-accent-50 text-accent-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

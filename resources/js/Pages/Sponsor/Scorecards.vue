<template>
    <Head title="Scorecards" />

    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-xl font-display font-bold text-surface-900">Scorecards</h1>
            <p class="text-sm text-surface-500 mt-1">All scorecards you've submitted for your interns</p>
        </div>

        <!-- Scorecards list -->
        <div v-if="scorecards.data?.length" class="space-y-3">
            <div
                v-for="sc in scorecards.data"
                :key="sc.id"
                class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-accent-100 flex items-center justify-center text-accent-700 font-semibold text-sm shrink-0">
                            {{ getInitials(sc.enrollment?.user) }}
                        </div>
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ sc.enrollment?.user?.first_name }} {{ sc.enrollment?.user?.last_name }}</h3>
                            <p class="text-sm text-surface-500">{{ sc.enrollment?.track?.title || 'Internship' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <p class="text-xs text-surface-400 uppercase font-medium">{{ sc.period_type }} {{ sc.period_number }}</p>
                            <div class="flex items-center gap-1 mt-0.5">
                                <span class="text-lg font-bold" :class="scoreColor(sc.overall_score)">{{ sc.overall_score?.toFixed(1) }}</span>
                                <span class="text-xs text-surface-400">/5</span>
                            </div>
                        </div>
                        <a
                            :href="`/sponsor/interns/${sc.enrollment?.id}/scorecard`"
                            class="px-3 py-1.5 bg-primary-50 text-primary-700 rounded-lg text-xs font-medium hover:bg-primary-100 transition-colors"
                        >
                            View
                        </a>
                    </div>
                </div>

                <p v-if="sc.comments" class="text-sm text-surface-500 mt-3 line-clamp-2">{{ sc.comments }}</p>

                <div class="flex items-center gap-3 mt-3">
                    <div class="flex-1 bg-surface-100 rounded-full h-1.5">
                        <div
                            class="h-1.5 rounded-full transition-all"
                            :class="sc.overall_score >= 3.5 ? 'bg-success-500' : sc.overall_score >= 2.5 ? 'bg-warning-500' : 'bg-danger-500'"
                            :style="{ width: (sc.overall_score / 5 * 100) + '%' }"
                        />
                    </div>
                    <span v-if="sc.ready_for_next" class="text-[10px] text-success-600 font-semibold uppercase">Ready to advance</span>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <FileText class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <h3 class="font-semibold text-surface-900 mb-1">No scorecards yet</h3>
            <p class="text-sm text-surface-500">You haven't submitted any scorecards for your interns yet.</p>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

defineProps({
    scorecards: { type: Object, default: () => ({ data: [] }) },
});

function getInitials(user) {
    if (!user) return '?';
    return ((user.first_name?.[0] || '') + (user.last_name?.[0] || '')).toUpperCase() || '?';
}

function scoreColor(score) {
    if (score >= 4) return 'text-success-600';
    if (score >= 3) return 'text-warning-600';
    return 'text-danger-600';
}
</script>

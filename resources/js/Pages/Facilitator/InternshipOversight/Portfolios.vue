<template>
    <Head title="Portfolio Audit" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-display font-bold text-surface-900">Portfolio Completeness</h1>
            <p class="text-sm text-surface-500">Audit learner portfolio progress across all internship enrollments</p>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-primary-600">{{ audit.length }}</p>
                <p class="text-xs text-surface-500">Total Enrollments</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-success-600">{{ completedCount }}</p>
                <p class="text-xs text-surface-500">Have Portfolio Items</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-warning-600">{{ emptyCount }}</p>
                <p class="text-xs text-surface-500">No Items Yet</p>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-surface-200/60 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface-50 text-left">
                        <th class="px-4 py-3 font-medium text-surface-600">Learner</th>
                        <th class="px-4 py-3 font-medium text-surface-600">Track</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Portfolio Items</th>
                        <th class="px-4 py-3 font-medium text-surface-600 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in audit" :key="row.enrollment_id" class="border-t border-surface-100 hover:bg-surface-50">
                        <td class="px-4 py-3 font-medium text-surface-900">{{ row.learner }}</td>
                        <td class="px-4 py-3 text-surface-600">{{ row.track }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-bold" :class="row.portfolio_items_count > 0 ? 'text-primary-600' : 'text-surface-400'">
                                {{ row.portfolio_items_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', portfolioStatus(row)]">
                                {{ portfolioLabel(row) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!audit.length" class="p-12 text-center text-surface-400">
                No enrollments to audit.
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
    audit: { type: Array, default: () => [] },
});

const completedCount = computed(() => props.audit.filter(r => r.portfolio_items_count > 0).length);
const emptyCount = computed(() => props.audit.filter(r => r.portfolio_items_count === 0).length);

function portfolioLabel(row) {
    if (row.portfolio_items_count >= 5) return 'Strong';
    if (row.portfolio_items_count >= 3) return 'Building';
    if (row.portfolio_items_count > 0) return 'Started';
    return 'Empty';
}

function portfolioStatus(row) {
    if (row.portfolio_items_count >= 5) return 'bg-success-50 text-success-700';
    if (row.portfolio_items_count >= 3) return 'bg-primary-50 text-primary-700';
    if (row.portfolio_items_count > 0) return 'bg-warning-50 text-warning-700';
    return 'bg-surface-100 text-surface-500';
}
</script>

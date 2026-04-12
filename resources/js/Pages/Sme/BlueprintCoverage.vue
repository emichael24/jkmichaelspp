<template>
    <Head title="Blueprint Coverage" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Blueprint Coverage</h1>
            <p class="text-surface-500 mt-1 text-sm">Ensure question bank alignment with exam domain distribution.</p>
        </div>

        <!-- Course selector -->
        <div class="flex gap-3">
            <select v-model="form.course_id" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
        </div>

        <!-- Coverage table -->
        <Card v-if="coverage.length" title="Domain Coverage">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-200 text-left">
                            <th class="py-3 px-2 font-semibold text-surface-600">Domain</th>
                            <th class="py-3 px-2 font-semibold text-surface-600 text-right">Target %</th>
                            <th class="py-3 px-2 font-semibold text-surface-600 text-right">Actual %</th>
                            <th class="py-3 px-2 font-semibold text-surface-600 text-right">Questions</th>
                            <th class="py-3 px-2 font-semibold text-surface-600">Difficulty Dist.</th>
                            <th class="py-3 px-2 font-semibold text-surface-600 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in coverage" :key="row.domain" class="border-b border-surface-100">
                            <td class="py-3 px-2 font-medium text-surface-900">{{ row.domain }}</td>
                            <td class="py-3 px-2 text-right text-surface-600">{{ row.target_pct }}%</td>
                            <td class="py-3 px-2 text-right" :class="coverageColor(row)">{{ row.actual_pct }}%</td>
                            <td class="py-3 px-2 text-right text-surface-600">{{ row.question_count }}</td>
                            <td class="py-3 px-2">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-success-600">E:{{ row.difficulty_dist?.easy || 0 }}</span>
                                    <span class="text-xs text-warning-600">M:{{ row.difficulty_dist?.medium || 0 }}</span>
                                    <span class="text-xs text-error-600">H:{{ row.difficulty_dist?.hard || 0 }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <span :class="['w-2.5 h-2.5 rounded-full inline-block', coverageDot(row)]" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <EmptyState v-if="!coverage.length" title="No Blueprint Data" description="The selected course has no exam blueprint defined." />
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import Card from '@/Components/UI/Card.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    courses: { type: Array, default: () => [] },
    coverage: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ course_id: props.filters.course_id || '' });

function applyFilters() {
    router.get('/sme/questions/blueprint-coverage', { course_id: form.course_id || undefined }, { preserveState: true });
}

function coverageColor(row) {
    const diff = Math.abs(row.actual_pct - row.target_pct);
    if (diff <= 5) return 'text-success-600 font-semibold';
    if (diff <= 10) return 'text-warning-600 font-semibold';
    return 'text-error-600 font-semibold';
}

function coverageDot(row) {
    const diff = Math.abs(row.actual_pct - row.target_pct);
    if (diff <= 5) return 'bg-success-500';
    if (diff <= 10) return 'bg-warning-500';
    return 'bg-error-500';
}
</script>

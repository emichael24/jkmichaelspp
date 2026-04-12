<template>
    <Head title="Analytics Dashboard" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Item Analytics Dashboard</h1>
            <p class="text-surface-500 mt-1 text-sm">Aggregate quality metrics across the question bank.</p>
        </div>

        <!-- Course filter -->
        <div class="flex gap-3">
            <select v-model="form.course_id" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Courses</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Difficulty Distribution -->
            <Card title="Difficulty Distribution" subtitle="Based on actual correct rates">
                <div class="space-y-3">
                    <BarRow label="Easy (≥80%)" :value="difficultyDist.easy" :total="difficultyTotal" color="bg-success-500" />
                    <BarRow label="Medium (40-79%)" :value="difficultyDist.medium" :total="difficultyTotal" color="bg-warning-500" />
                    <BarRow label="Hard (<40%)" :value="difficultyDist.hard" :total="difficultyTotal" color="bg-error-500" />
                    <BarRow label="No Data" :value="difficultyDist.no_data" :total="difficultyTotal" color="bg-surface-300" />
                </div>
            </Card>

            <!-- Discrimination Distribution -->
            <Card title="Discrimination Index" subtitle="Item quality categorization">
                <div class="space-y-3">
                    <BarRow label="Excellent (≥0.4)" :value="discriminationDist.excellent" :total="discriminationTotal" color="bg-success-500" />
                    <BarRow label="Good (0.3-0.39)" :value="discriminationDist.good" :total="discriminationTotal" color="bg-primary-500" />
                    <BarRow label="Fair (0.2-0.29)" :value="discriminationDist.fair" :total="discriminationTotal" color="bg-warning-500" />
                    <BarRow label="Poor (<0.2)" :value="discriminationDist.poor" :total="discriminationTotal" color="bg-error-500" />
                    <BarRow label="No Data" :value="discriminationDist.no_data" :total="discriminationTotal" color="bg-surface-300" />
                </div>
            </Card>

            <!-- Avg Time by Difficulty -->
            <Card title="Average Time by Difficulty" subtitle="Seconds per question">
                <div class="space-y-3">
                    <div v-for="(time, difficulty) in avgTimeByDifficulty" :key="difficulty"
                         class="flex items-center justify-between">
                        <span class="text-sm text-surface-700 capitalize">{{ difficulty }}</span>
                        <span class="text-sm font-semibold text-surface-900">{{ Math.round(time) }}s</span>
                    </div>
                    <p v-if="!Object.keys(avgTimeByDifficulty).length" class="text-sm text-surface-400">No time data available.</p>
                </div>
            </Card>

            <!-- Quick Actions -->
            <Card title="Quick Actions">
                <div class="space-y-2">
                    <a href="/sme/flags" class="block p-3 rounded-lg border border-surface-200/60 hover:bg-surface-50 transition-colors">
                        <p class="text-sm font-medium text-surface-900">Flag Queue</p>
                        <p class="text-xs text-surface-500 mt-0.5">Review flagged questions from learners</p>
                    </a>
                    <a href="/sme/questions/blueprint-coverage" class="block p-3 rounded-lg border border-surface-200/60 hover:bg-surface-50 transition-colors">
                        <p class="text-sm font-medium text-surface-900">Blueprint Coverage</p>
                        <p class="text-xs text-surface-500 mt-0.5">Check domain coverage against exam blueprints</p>
                    </a>
                    <a href="/sme/questions" class="block p-3 rounded-lg border border-surface-200/60 hover:bg-surface-50 transition-colors">
                        <p class="text-sm font-medium text-surface-900">Full Question Bank</p>
                        <p class="text-xs text-surface-500 mt-0.5">Browse and filter all questions with analytics</p>
                    </a>
                </div>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    difficultyDist: { type: Object, default: () => ({}) },
    discriminationDist: { type: Object, default: () => ({}) },
    avgTimeByDifficulty: { type: Object, default: () => ({}) },
    courses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ course_id: props.filters.course_id || '' });

const difficultyTotal = computed(() => Object.values(props.difficultyDist).reduce((a, b) => a + b, 0) || 1);
const discriminationTotal = computed(() => Object.values(props.discriminationDist).reduce((a, b) => a + b, 0) || 1);

function applyFilters() {
    router.get('/sme/questions/analytics-dashboard', { course_id: form.course_id || undefined }, { preserveState: true });
}

// BarRow helper component inlined
const BarRow = {
    props: ['label', 'value', 'total', 'color'],
    template: `
        <div class="flex items-center gap-3">
            <span class="text-sm text-surface-600 w-40 shrink-0">{{ label }}</span>
            <div class="flex-1 h-3 bg-surface-100 rounded-full overflow-hidden">
                <div :class="[color, 'h-full rounded-full transition-all']" :style="{ width: pct + '%' }" />
            </div>
            <span class="text-xs text-surface-500 w-16 text-right">{{ value }} ({{ pct }}%)</span>
        </div>
    `,
    computed: {
        pct() { return this.total ? Math.round((this.value / this.total) * 100) : 0; },
    },
};
</script>

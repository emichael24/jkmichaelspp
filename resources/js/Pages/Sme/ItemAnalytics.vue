<template>
    <Head title="Item Analytics" />

    <div class="space-y-6">
        <a href="/sme/questions" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to question bank
        </a>

        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Item Analytics</h1>
            <p class="text-surface-500 text-sm mt-1">{{ question.course?.title }}</p>
        </div>

        <!-- Question preview -->
        <Card title="Question">
            <p class="font-medium text-surface-900 leading-snug">{{ question.stem }}</p>
            <div class="flex items-center gap-3 mt-3 text-xs text-surface-400">
                <span v-if="question.domain" class="px-2 py-0.5 bg-surface-100 rounded text-surface-600">{{ question.domain }}</span>
                <span class="px-2 py-0.5 rounded" :class="difficultyClass(question.difficulty)">{{ question.difficulty }}</span>
                <span>Type: {{ question.type }}</span>
                <span v-if="question.bloom_level">Bloom: {{ question.bloom_level }}</span>
            </div>
        </Card>

        <!-- Key Metrics -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Correct Rate" :value="`${((analytics.correct_rate || 0) * 100).toFixed(1)}%`"
                      :icon="Target" :color="analytics.correct_rate >= 0.7 ? 'success' : analytics.correct_rate >= 0.4 ? 'warning' : 'error'" />
            <StatCard label="Discrimination" :value="analytics.discrimination_index || '—'"
                      :icon="TrendingUp" color="primary" />
            <StatCard label="Exposure" :value="analytics.exposure_count || 0"
                      :icon="Eye" color="accent" />
            <StatCard label="Avg Time" :value="`${(analytics.avg_time_seconds || 0).toFixed(0)}s`"
                      :icon="Clock" color="surface" />
        </div>

        <!-- Difficulty Classification -->
        <Card title="Difficulty Assessment">
            <div class="flex items-center gap-3">
                <span :class="['px-3 py-1.5 rounded-lg font-semibold text-sm', difficultyBadge(analytics.difficulty)]">
                    {{ analytics.difficulty || 'No Data' }}
                </span>
                <span class="text-sm text-surface-500">
                    Based on {{ ((analytics.correct_rate || 0) * 100).toFixed(1) }}% correct rate across {{ analytics.exposure_count || 0 }} attempts
                </span>
            </div>
        </Card>

        <!-- Distractor Analysis -->
        <Card title="Distractor Analysis">
            <div v-if="analytics.distractor_analysis?.length" class="space-y-3">
                <div v-for="opt in analytics.distractor_analysis" :key="opt.option_id"
                     class="flex items-center gap-3">
                    <div class="flex-1 min-w-0 flex items-center gap-2">
                        <span v-if="opt.is_correct" class="w-5 h-5 rounded-full bg-success-500 flex items-center justify-center shrink-0">
                            <Check class="w-3 h-3 text-white" />
                        </span>
                        <span v-else class="w-5 h-5 rounded-full bg-surface-200 shrink-0" />
                        <span class="text-sm text-surface-700 truncate">{{ opt.label }}</span>
                    </div>
                    <div class="w-48 flex items-center gap-2 shrink-0">
                        <div class="flex-1 h-2 bg-surface-100 rounded-full overflow-hidden">
                            <div :class="['h-full rounded-full', opt.is_correct ? 'bg-success-500' : opt.is_effective ? 'bg-primary-500' : 'bg-error-400']"
                                 :style="{ width: `${opt.selection_pct}%` }" />
                        </div>
                        <span class="text-xs text-surface-500 w-12 text-right">{{ opt.selection_pct }}%</span>
                    </div>
                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', opt.is_correct ? 'bg-success-50 text-success-700' : opt.is_effective ? 'bg-primary-50 text-primary-700' : 'bg-error-50 text-error-700']">
                        {{ opt.is_correct ? 'Correct' : opt.is_effective ? 'Effective' : 'Ineffective' }}
                    </span>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400">No response data available yet.</p>
        </Card>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Target, TrendingUp, Eye, Clock, Check } from 'lucide-vue-next';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: SmeLayout });

defineProps({
    question: { type: Object, required: true },
    analytics: { type: Object, default: () => ({}) },
});

function difficultyClass(d) {
    const map = {
        easy: 'text-success-600 bg-success-50 px-2 py-0.5 rounded',
        medium: 'text-warning-600 bg-warning-50 px-2 py-0.5 rounded',
        hard: 'text-error-600 bg-error-50 px-2 py-0.5 rounded',
        expert: 'text-purple-600 bg-purple-50 px-2 py-0.5 rounded',
    };
    return map[d] || '';
}

function difficultyBadge(d) {
    const map = {
        easy: 'bg-success-100 text-success-800',
        medium: 'bg-warning-100 text-warning-800',
        hard: 'bg-error-100 text-error-800',
    };
    return map[d] || 'bg-surface-100 text-surface-600';
}
</script>

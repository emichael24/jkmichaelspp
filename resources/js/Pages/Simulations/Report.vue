<template>
    <Head :title="`${simulation.title} — Report`" />

    <div class="max-w-4xl mx-auto space-y-8">
        <a href="/simulations" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to simulations
        </a>

        <!-- Score hero -->
        <div class="bg-gradient-to-br from-primary-600 to-primary-800 rounded-2xl p-8 text-white text-center">
            <h1 class="text-lg font-display font-semibold text-white/80">Simulation Complete</h1>
            <p class="text-5xl font-display font-bold mt-2">{{ totalScorePct }}%</p>
            <p class="text-white/60 text-sm mt-1">{{ simulation.title }}</p>
            <p class="text-white/50 text-xs mt-2">{{ formatDuration(attempt.time_spent_seconds) }}</p>
        </div>

        <!-- Score dimensions -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="dim in dimensions" :key="dim.key"
                 class="bg-white rounded-xl border border-surface-200/60 p-5 text-center">
                <component :is="dim.icon" :class="['w-6 h-6 mx-auto mb-2', dim.color]" />
                <p class="text-2xl font-bold text-surface-900">{{ scores[dim.key] || 0 }}</p>
                <p class="text-xs text-surface-500 mt-1">{{ dim.label }}</p>
                <!-- Score bar -->
                <div class="mt-3 h-2 bg-surface-100 rounded-full overflow-hidden">
                    <div :class="['h-full rounded-full', dim.barColor]"
                         :style="{ width: `${scores[dim.key] || 0}%` }" />
                </div>
            </div>
        </div>

        <!-- Path Comparison -->
        <Card title="Your Path vs Best Practice" subtitle="Side-by-side comparison of your decisions">
            <div class="space-y-4">
                <div v-for="step in pathComparison" :key="step.step"
                     :class="['p-4 rounded-lg border', step.diverged ? 'border-warning-200 bg-warning-50/50' : 'border-surface-100 bg-surface-50/50']">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-6 h-6 rounded-full bg-surface-200 flex items-center justify-center text-xs font-bold text-surface-600">
                            {{ step.step }}
                        </span>
                        <span v-if="step.diverged" class="px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700">
                            Diverged
                        </span>
                        <span v-else class="px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">
                            Aligned
                        </span>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Your path -->
                        <div>
                            <p class="text-xs font-semibold text-surface-400 uppercase tracking-wider mb-1">Your Path</p>
                            <div v-if="step.your_node" class="text-sm text-surface-700">
                                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-primary-50 text-primary-700 mr-1">
                                    {{ step.your_node.type }}
                                </span>
                                {{ truncate(step.your_node.content, 120) }}
                                <p v-if="step.choice_made" class="text-xs text-primary-600 mt-1 italic">
                                    Choice: "{{ step.choice_made }}"
                                </p>
                            </div>
                            <p v-else class="text-sm text-surface-400 italic">—</p>
                        </div>

                        <!-- Best practice -->
                        <div>
                            <p class="text-xs font-semibold text-surface-400 uppercase tracking-wider mb-1">Best Practice</p>
                            <div v-if="step.best_node" class="text-sm text-surface-700">
                                <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-success-50 text-success-700 mr-1">
                                    {{ step.best_node.type }}
                                </span>
                                {{ truncate(step.best_node.content, 120) }}
                            </div>
                            <p v-else class="text-sm text-surface-400 italic">—</p>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Actions -->
        <div class="flex gap-3">
            <a :href="`/simulations/${simulation.id}`"
               class="flex-1 text-center py-3 rounded-xl bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-colors">
                Try Again
            </a>
            <a href="/simulations"
               class="flex-1 text-center py-3 rounded-xl bg-surface-200 text-surface-700 font-semibold hover:bg-surface-300 transition-colors">
                Back to Simulations
            </a>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Brain, Shield, Users, AlertTriangle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    attempt: { type: Object, required: true },
    simulation: { type: Object, required: true },
    scores: { type: Object, default: () => ({}) },
    totalScorePct: { type: [Number, String], default: 0 },
    bestPracticePath: { type: Array, default: () => [] },
    yourPath: { type: Array, default: () => [] },
    pathComparison: { type: Array, default: () => [] },
    nodesVisited: { type: Array, default: () => [] },
});

const dimensions = [
    { key: 'judgment', label: 'Judgment', icon: Brain, color: 'text-primary-600', barColor: 'bg-primary-500' },
    { key: 'ethics', label: 'Ethics', icon: Shield, color: 'text-success-600', barColor: 'bg-success-500' },
    { key: 'stakeholder_handling', label: 'Stakeholder', icon: Users, color: 'text-purple-600', barColor: 'bg-purple-500' },
    { key: 'risk_thinking', label: 'Risk Thinking', icon: AlertTriangle, color: 'text-warning-600', barColor: 'bg-warning-500' },
];

function formatDuration(s) {
    if (!s) return '';
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}m ${sec}s`;
}

function truncate(text, len = 100) {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '…' : text;
}
</script>

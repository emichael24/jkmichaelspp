<template>
    <Head :title="simulation.title" />

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <a href="/admin/simulations" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
                <ArrowLeft class="w-4 h-4" /> Back to simulations
            </a>
            <div class="flex gap-2">
                <a :href="`/admin/simulations/${simulation.id}/edit`"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                    <Pencil class="w-4 h-4" /> Edit
                </a>
            </div>
        </div>

        <!-- Header card -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(simulation.status)]">
                            {{ simulation.status }}
                        </span>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', typeBadge(simulation.type)]">
                            {{ formatType(simulation.type) }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-600 capitalize">
                            {{ simulation.difficulty }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">{{ simulation.title }}</h1>
                    <p v-if="simulation.description" class="text-surface-500 mt-1">{{ simulation.description }}</p>
                    <p v-if="simulation.course" class="text-sm text-surface-400 mt-2">
                        Course: <span class="font-medium text-surface-600">{{ simulation.course.title }}</span>
                    </p>
                </div>
                <div class="text-right text-sm text-surface-500">
                    <p>{{ simulation.estimated_minutes }} min</p>
                    <p>{{ simulation.xp_reward }} XP</p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4">
            <StatCard label="Total Nodes" :value="stats.total_nodes" />
            <StatCard label="Decision Points" :value="stats.decision_points" />
            <StatCard label="Total Attempts" :value="stats.total_attempts" />
            <StatCard label="Avg Score" :value="stats.avg_score ? stats.avg_score + '%' : '—'" />
        </div>

        <!-- Context narrative -->
        <Card title="Context Narrative">
            <p class="text-surface-700 text-sm leading-relaxed whitespace-pre-line">{{ simulation.context_narrative }}</p>
        </Card>

        <!-- Node flow visualization -->
        <Card title="Scenario Flow" :subtitle="`${nodes.length} nodes`">
            <div class="space-y-2">
                <div v-for="node in nodes" :key="node.id"
                     :class="['flex items-start gap-3 p-3 rounded-lg', node.is_start ? 'bg-success-50 border border-success-200' : node.is_end ? 'bg-error-50 border border-error-200' : 'bg-surface-50']">
                    <!-- Order & type -->
                    <div class="flex flex-col items-center gap-1 shrink-0 w-14">
                        <span class="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center text-xs font-bold text-surface-600 border border-surface-200">
                            {{ node.order_index }}
                        </span>
                        <span :class="['text-[10px] font-bold uppercase', nodeTypeColor(node.type)]">{{ node.type }}</span>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-surface-700 line-clamp-2">{{ node.content }}</p>

                        <!-- Choices -->
                        <div v-if="node.choices?.length" class="mt-2 flex flex-wrap gap-1.5">
                            <span v-for="choice in node.choices" :key="choice.id"
                                  :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs', choice.is_best_practice ? 'bg-success-100 text-success-700 font-semibold' : 'bg-surface-200 text-surface-600']">
                                {{ choice.label }}
                                <span v-if="choice.next_node_id" class="text-surface-400">→ {{ choice.next_node_id }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div class="flex gap-1 shrink-0">
                        <span v-if="node.is_start" class="text-[10px] font-bold text-success-600">START</span>
                        <span v-if="node.is_end" class="text-[10px] font-bold text-error-600">END</span>
                    </div>
                </div>
            </div>

            <EmptyState v-if="!nodes.length" title="No Nodes"
                        description="This simulation has no nodes yet."
                        class="mt-4" />
        </Card>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    simulation: { type: Object, required: true },
    nodes: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

function statusBadge(s) {
    return { draft: 'bg-surface-100 text-surface-600', published: 'bg-success-50 text-success-700', archived: 'bg-warning-50 text-warning-700' }[s] || 'bg-surface-100 text-surface-600';
}

function typeBadge(type) {
    const map = {
        branching_scenario: 'bg-primary-50 text-primary-700',
        role_play: 'bg-purple-50 text-purple-700',
        case_study_lab: 'bg-accent-50 text-accent-700',
        decision_room: 'bg-warning-50 text-warning-700',
    };
    return map[type] || 'bg-surface-100 text-surface-600';
}

function formatType(t) {
    return (t || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function nodeTypeColor(type) {
    return { story: 'text-primary-600', decision: 'text-warning-600', consequence: 'text-error-600', feedback: 'text-success-600', end: 'text-surface-500' }[type] || 'text-surface-400';
}
</script>

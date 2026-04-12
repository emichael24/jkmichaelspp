<template>
    <Head :title="simulation.title" />

    <div class="max-w-3xl mx-auto space-y-6">
        <a href="/simulations" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to simulations
        </a>

        <!-- Header -->
        <div :class="['rounded-2xl p-8 text-white', typeGradient(simulation.type)]">
            <div class="flex items-center gap-2 mb-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-white/20 backdrop-blur-sm">
                    {{ formatType(simulation.type) }}
                </span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-white/20 backdrop-blur-sm capitalize">
                    {{ simulation.difficulty }}
                </span>
            </div>
            <h1 class="text-3xl font-display font-bold">{{ simulation.title }}</h1>
            <p v-if="simulation.description" class="text-white/80 mt-2">{{ simulation.description }}</p>
            <div class="flex items-center gap-4 mt-4 text-white/70 text-sm">
                <span class="flex items-center gap-1"><Clock class="w-4 h-4" /> {{ simulation.estimated_minutes }} minutes</span>
                <span class="flex items-center gap-1"><GitBranch class="w-4 h-4" /> {{ decisionPointCount }} decision points</span>
                <span v-if="simulation.course" class="flex items-center gap-1"><BookOpen class="w-4 h-4" /> {{ simulation.course.title }}</span>
            </div>
        </div>

        <!-- Context Narrative -->
        <Card title="Scenario Context">
            <p class="text-surface-700 leading-relaxed whitespace-pre-line">{{ simulation.context_narrative }}</p>
        </Card>

        <!-- Previous Attempts -->
        <Card v-if="previousAttempts.length" title="Your History">
            <div class="space-y-2">
                <div v-for="attempt in previousAttempts" :key="attempt.id"
                     class="flex items-center justify-between py-2 border-b border-surface-100 last:border-0">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-surface-900">{{ attempt.total_score_pct }}%</span>
                        <span class="text-xs text-surface-400">{{ formatDate(attempt.completed_at) }}</span>
                        <span class="text-xs text-surface-400">{{ formatDuration(attempt.time_spent_seconds) }}</span>
                    </div>
                    <a :href="`/simulation-attempts/${attempt.id}/report`"
                       class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        View Report →
                    </a>
                </div>
            </div>
        </Card>

        <!-- Action buttons -->
        <div class="flex gap-3">
            <form v-if="inProgressAttempt" @submit.prevent="resumeAttempt" class="flex-1">
                <button type="submit" class="w-full py-3 rounded-xl bg-warning-600 text-white font-semibold text-lg hover:bg-warning-700 transition-colors">
                    Resume In-Progress Attempt
                </button>
            </form>
            <form @submit.prevent="startSimulation" :class="inProgressAttempt ? 'flex-1' : 'w-full'">
                <button type="submit" :class="['w-full py-3 rounded-xl font-semibold text-lg transition-colors', inProgressAttempt ? 'bg-surface-200 text-surface-700 hover:bg-surface-300' : 'bg-primary-600 text-white hover:bg-primary-700']">
                    {{ previousAttempts.length ? 'Start New Attempt' : 'Begin Simulation' }}
                </button>
            </form>
        </div>

        <!-- Scoring info -->
        <Card title="How You're Scored">
            <div class="grid grid-cols-2 gap-4">
                <div v-for="dim in scoreDimensions" :key="dim.key" class="flex items-start gap-3">
                    <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', dim.bg]">
                        <component :is="dim.icon" :class="['w-4 h-4', dim.text]" />
                    </div>
                    <div>
                        <p class="font-medium text-sm text-surface-900">{{ dim.label }}</p>
                        <p class="text-xs text-surface-500">{{ dim.desc }}</p>
                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Clock, GitBranch, BookOpen, Brain, Shield, Users, AlertTriangle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    simulation: { type: Object, required: true },
    previousAttempts: { type: Array, default: () => [] },
    inProgressAttempt: { type: Object, default: null },
    decisionPointCount: { type: Number, default: 0 },
});

const scoreDimensions = [
    { key: 'judgment', label: 'Judgment', desc: 'Quality of your decisions', icon: Brain, bg: 'bg-primary-50', text: 'text-primary-600' },
    { key: 'ethics', label: 'Ethics', desc: 'Ethical consideration', icon: Shield, bg: 'bg-success-50', text: 'text-success-600' },
    { key: 'stakeholder', label: 'Stakeholder Handling', desc: 'Communication & relationships', icon: Users, bg: 'bg-purple-50', text: 'text-purple-600' },
    { key: 'risk', label: 'Risk Thinking', desc: 'How well you manage risk', icon: AlertTriangle, bg: 'bg-warning-50', text: 'text-warning-600' },
];

function startSimulation() {
    router.post(`/simulations/${props.simulation.id}/start`);
}

function resumeAttempt() {
    router.post(`/simulation-attempts/${props.inProgressAttempt.id}/resume`);
}

function typeGradient(type) {
    const map = {
        branching_scenario: 'bg-gradient-to-br from-primary-600 to-primary-800',
        role_play: 'bg-gradient-to-br from-purple-600 to-purple-800',
        case_study_lab: 'bg-gradient-to-br from-accent-600 to-accent-800',
        decision_room: 'bg-gradient-to-br from-warning-600 to-warning-800',
    };
    return map[type] || 'bg-gradient-to-br from-surface-500 to-surface-700';
}

function formatType(t) {
    return (t || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatDuration(s) {
    if (!s) return '';
    const m = Math.floor(s / 60);
    return `${m}m`;
}
</script>

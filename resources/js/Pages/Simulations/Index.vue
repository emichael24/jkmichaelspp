<template>
    <Head title="Simulations" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Simulations</h1>
            <p class="text-surface-500 mt-1 text-sm">Practice workplace judgment through interactive branching scenarios.</p>
        </div>

        <!-- Filters -->
        <div class="flex gap-3 flex-wrap">
            <input v-model="form.search" type="text" placeholder="Search simulations..."
                   @input="debouncedFilter"
                   class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500 w-64" />
            <select v-model="form.type" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Types</option>
                <option value="branching_scenario">Branching Scenario</option>
                <option value="role_play">Role Play</option>
                <option value="case_study_lab">Case Study Lab</option>
                <option value="decision_room">Decision Room</option>
            </select>
        </div>

        <!-- Simulation grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="sim in simulations.data" :key="sim.id"
                 class="bg-white rounded-xl border border-surface-200/60 overflow-hidden hover:shadow-lg transition-shadow group">
                <!-- Gradient header -->
                <div :class="['h-32 flex items-center justify-center', typeGradient(sim.type)]">
                    <component :is="typeIcon(sim.type)" class="w-12 h-12 text-white/80" />
                </div>

                <div class="p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', typeBadge(sim.type)]">
                            {{ formatType(sim.type) }}
                        </span>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', difficultyBadge(sim.difficulty)]">
                            {{ sim.difficulty }}
                        </span>
                    </div>

                    <h3 class="font-semibold text-surface-900 line-clamp-2 group-hover:text-primary-600 transition-colors">
                        {{ sim.title }}
                    </h3>
                    <p v-if="sim.description" class="text-sm text-surface-500 mt-1 line-clamp-2">{{ sim.description }}</p>

                    <div class="flex items-center gap-3 text-xs text-surface-400 mt-3">
                        <span class="flex items-center gap-1"><Clock class="w-3.5 h-3.5" /> {{ sim.estimated_minutes }} min</span>
                        <span v-if="sim.best_score != null" class="flex items-center gap-1 text-success-600 font-medium">
                            <Trophy class="w-3.5 h-3.5" /> {{ sim.best_score }}%
                        </span>
                        <span v-if="sim.user_attempts_count" class="flex items-center gap-1">
                            <RotateCcw class="w-3.5 h-3.5" /> {{ sim.user_attempts_count }}x
                        </span>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <a v-if="sim.has_in_progress"
                           :href="`/simulations/${sim.id}`"
                           class="flex-1 text-center py-2 rounded-lg bg-warning-600 text-white text-sm font-medium hover:bg-warning-700 transition-colors">
                            Resume
                        </a>
                        <a :href="`/simulations/${sim.id}`"
                           :class="['flex-1 text-center py-2 rounded-lg text-sm font-medium transition-colors', sim.has_in_progress ? 'bg-surface-100 text-surface-700 hover:bg-surface-200' : 'bg-primary-600 text-white hover:bg-primary-700']">
                            {{ sim.user_attempts_count ? 'Replay' : 'Start' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState v-if="!simulations.data?.length" title="No Simulations" description="No simulations available yet." />

        <!-- Pagination -->
        <div v-if="simulations.links && simulations.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in simulations.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Clock, Trophy, RotateCcw, GitBranch, Users, FileText, Scale } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    simulations: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ ...props.filters });

let debounceTimer = null;
function debouncedFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}

function applyFilters() {
    const params = {};
    Object.entries(form).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/simulations', params, { preserveState: true });
}

function typeIcon(type) {
    const map = { branching_scenario: GitBranch, role_play: Users, case_study_lab: FileText, decision_room: Scale };
    return map[type] || GitBranch;
}

function typeGradient(type) {
    const map = {
        branching_scenario: 'bg-gradient-to-br from-primary-500 to-primary-700',
        role_play: 'bg-gradient-to-br from-purple-500 to-purple-700',
        case_study_lab: 'bg-gradient-to-br from-accent-500 to-accent-700',
        decision_room: 'bg-gradient-to-br from-warning-500 to-warning-700',
    };
    return map[type] || 'bg-gradient-to-br from-surface-400 to-surface-600';
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

function difficultyBadge(d) {
    const map = {
        easy: 'bg-success-50 text-success-700',
        medium: 'bg-warning-50 text-warning-700',
        hard: 'bg-error-50 text-error-700',
    };
    return map[d] || 'bg-surface-100 text-surface-600';
}

function formatType(t) {
    return (t || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
</script>

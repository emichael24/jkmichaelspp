<template>
    <Head title="Manage Simulations" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Simulations</h1>
                <p class="text-surface-500 text-sm mt-1">Manage branching scenario simulations.</p>
            </div>
            <a href="/admin/simulations/create"
               class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                <Plus class="w-4 h-4" /> New Simulation
            </a>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4">
            <StatCard label="Total" :value="stats.total" />
            <StatCard label="Published" :value="stats.published" />
            <StatCard label="Draft" :value="stats.draft" />
            <StatCard label="Total Attempts" :value="stats.total_attempts" />
        </div>

        <!-- Filters -->
        <div class="flex gap-3 flex-wrap">
            <input v-model="form.search" type="text" placeholder="Search..."
                   @input="debouncedFilter"
                   class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500 w-64" />
            <select v-model="form.status" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
            </select>
            <select v-model="form.type" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Types</option>
                <option value="branching_scenario">Branching Scenario</option>
                <option value="role_play">Role Play</option>
                <option value="case_study_lab">Case Study Lab</option>
                <option value="decision_room">Decision Room</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-surface-200/60 overflow-hidden">
            <table class="w-full">
                <thead class="bg-surface-50 border-b border-surface-200">
                    <tr>
                        <th class="text-left text-xs font-semibold text-surface-500 uppercase tracking-wider px-4 py-3">Simulation</th>
                        <th class="text-left text-xs font-semibold text-surface-500 uppercase tracking-wider px-4 py-3">Type</th>
                        <th class="text-left text-xs font-semibold text-surface-500 uppercase tracking-wider px-4 py-3">Nodes</th>
                        <th class="text-left text-xs font-semibold text-surface-500 uppercase tracking-wider px-4 py-3">Attempts</th>
                        <th class="text-left text-xs font-semibold text-surface-500 uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-right text-xs font-semibold text-surface-500 uppercase tracking-wider px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    <tr v-for="sim in simulations.data" :key="sim.id" class="hover:bg-surface-50/50">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-sm text-surface-900">{{ sim.title }}</p>
                            <p v-if="sim.course" class="text-xs text-surface-400">{{ sim.course.title }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', typeBadge(sim.type)]">
                                {{ formatType(sim.type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-surface-600">{{ sim.nodes_count || 0 }}</td>
                        <td class="px-4 py-3 text-sm text-surface-600">{{ sim.attempts_count || 0 }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(sim.status)]">
                                {{ sim.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <a :href="`/admin/simulations/${sim.id}`"
                                   class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-surface-700">
                                    <Eye class="w-4 h-4" />
                                </a>
                                <a :href="`/admin/simulations/${sim.id}/edit`"
                                   class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-surface-700">
                                    <Pencil class="w-4 h-4" />
                                </a>
                                <button @click="deleteSimulation(sim)"
                                        class="p-1.5 rounded-lg hover:bg-error-50 text-surface-500 hover:text-error-600">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState v-if="!simulations.data?.length" title="No Simulations" description="Create your first simulation." />
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Plus, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    simulations: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
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
    router.get('/admin/simulations', params, { preserveState: true });
}

function deleteSimulation(sim) {
    if (confirm(`Delete "${sim.title}"?`)) {
        router.delete(`/admin/simulations/${sim.id}`);
    }
}

function formatType(t) {
    return (t || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
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

function statusBadge(s) {
    const map = {
        draft: 'bg-surface-100 text-surface-600',
        published: 'bg-success-50 text-success-700',
        archived: 'bg-warning-50 text-warning-700',
    };
    return map[s] || 'bg-surface-100 text-surface-600';
}
</script>

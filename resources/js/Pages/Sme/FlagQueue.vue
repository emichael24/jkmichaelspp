<template>
    <Head title="Flag Queue" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Flag Queue</h1>
            <p class="text-surface-500 mt-1 text-sm">Review and resolve flagged questions from learners.</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Open" :value="stats.open" :icon="AlertCircle" color="error" />
            <StatCard label="In Review" :value="stats.in_review" :icon="Eye" color="warning" />
            <StatCard label="Resolved" :value="stats.resolved" :icon="CheckCircle" color="success" />
            <StatCard label="Dismissed" :value="stats.dismissed" :icon="XCircle" color="surface" />
        </div>

        <!-- Filters -->
        <div class="flex gap-3">
            <select v-model="form.status" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">Unresolved</option>
                <option value="open">Open</option>
                <option value="in_review">In Review</option>
                <option value="resolved">Resolved</option>
                <option value="dismissed">Dismissed</option>
            </select>
            <select v-model="form.reason" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Reasons</option>
                <option value="incorrect_answer">Incorrect Answer</option>
                <option value="unclear_stem">Unclear Stem</option>
                <option value="outdated">Outdated</option>
                <option value="typo">Typo</option>
                <option value="duplicate">Duplicate</option>
                <option value="other">Other</option>
            </select>
        </div>

        <!-- Flags list -->
        <div class="space-y-4">
            <div v-for="flag in flags.data" :key="flag.id"
                 class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', reasonBadge(flag.reason)]">
                                {{ formatReason(flag.reason) }}
                            </span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(flag.status)]">
                                {{ formatStatus(flag.status) }}
                            </span>
                        </div>
                        <p class="font-medium text-surface-900 line-clamp-2">{{ flag.question?.stem }}</p>
                        <p class="text-sm text-surface-500 mt-1">{{ flag.question?.course?.title }}</p>
                        <p v-if="flag.description" class="text-sm text-surface-400 mt-2 italic">"{{ flag.description }}"</p>
                        <p class="text-xs text-surface-400 mt-2">
                            Flagged by {{ flag.flagger?.first_name }} {{ flag.flagger?.last_name }}
                            · {{ formatDate(flag.created_at) }}
                        </p>
                    </div>
                </div>

                <!-- Resolve form -->
                <div v-if="flag.status === 'open' || flag.status === 'in_review'" class="mt-4 pt-4 border-t border-surface-100">
                    <textarea v-model="resolutionNotes[flag.id]" rows="2" placeholder="Resolution notes..."
                        class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500 mb-3" />
                    <div class="flex gap-2">
                        <button @click="resolve(flag, 'resolved')"
                                class="px-3 py-1.5 rounded-lg bg-success-600 text-white text-sm font-medium hover:bg-success-700 transition-colors">
                            Resolve
                        </button>
                        <button @click="resolve(flag, 'dismissed')"
                                class="px-3 py-1.5 rounded-lg bg-surface-200 text-surface-700 text-sm font-medium hover:bg-surface-300 transition-colors">
                            Dismiss
                        </button>
                        <a :href="`/sme/questions/${flag.question_id}/analytics`"
                           class="px-3 py-1.5 rounded-lg text-primary-600 bg-primary-50 text-sm font-medium hover:bg-primary-100 transition-colors">
                            View Analytics
                        </a>
                    </div>
                </div>

                <!-- Resolution info -->
                <div v-if="flag.status === 'resolved' || flag.status === 'dismissed'" class="mt-3 p-3 bg-surface-50 rounded-lg text-sm">
                    <p v-if="flag.resolution_notes" class="text-surface-600">{{ flag.resolution_notes }}</p>
                    <p v-if="flag.resolver" class="text-xs text-surface-400 mt-1">
                        By {{ flag.resolver.first_name }} {{ flag.resolver.last_name }}
                    </p>
                </div>
            </div>
        </div>

        <EmptyState v-if="!flags.data?.length" title="No Flags" description="No flagged questions match your filters." />

        <!-- Pagination -->
        <div v-if="flags.links && flags.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in flags.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { AlertCircle, Eye, CheckCircle, XCircle } from 'lucide-vue-next';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    flags: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ ...props.filters });
const resolutionNotes = ref({});

function applyFilters() {
    const params = {};
    Object.entries(form).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/sme/flags', params, { preserveState: true });
}

function resolve(flag, status) {
    const notes = resolutionNotes.value[flag.id];
    if (!notes) { alert('Please add resolution notes.'); return; }
    router.put(`/sme/flags/${flag.id}`, { status, resolution_notes: notes });
}

function reasonBadge(reason) {
    const map = {
        incorrect_answer: 'bg-error-50 text-error-700',
        unclear_stem: 'bg-warning-50 text-warning-700',
        outdated: 'bg-purple-50 text-purple-700',
        typo: 'bg-primary-50 text-primary-700',
        duplicate: 'bg-accent-50 text-accent-700',
        other: 'bg-surface-100 text-surface-600',
    };
    return map[reason] || 'bg-surface-100 text-surface-600';
}

function statusBadge(status) {
    const map = {
        open: 'bg-error-50 text-error-700',
        in_review: 'bg-warning-50 text-warning-700',
        resolved: 'bg-success-50 text-success-700',
        dismissed: 'bg-surface-100 text-surface-600',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatReason(r) {
    return (r || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatStatus(s) {
    return (s || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

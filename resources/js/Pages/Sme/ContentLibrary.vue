<template>
    <Head title="Content Library" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Content Library</h1>
                <p class="text-surface-500 mt-1 text-sm">Manage content workflow: draft → review → publish.</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Pending" :value="stats.pending" :icon="Clock" color="warning" />
            <StatCard label="Approved" :value="stats.approved" :icon="CheckCircle" color="success" />
            <StatCard label="Rejected" :value="stats.rejected" :icon="XCircle" color="error" />
            <StatCard label="Changes Requested" :value="stats.changes_requested" :icon="MessageSquare" color="primary" />
        </div>

        <!-- Filters -->
        <div class="flex gap-3 flex-wrap">
            <select v-model="form.type" @change="applyFilters" class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500">
                <option value="all">All Types</option>
                <option value="lesson">Lessons</option>
                <option value="question">Questions</option>
                <option value="course">Courses</option>
            </select>
            <select v-model="form.status" @change="applyFilters" class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="changes_requested">Changes Requested</option>
            </select>
        </div>

        <!-- Content list -->
        <div class="space-y-3">
            <div v-for="approval in approvals.data" :key="approval.id"
                 class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-600 capitalize">
                                {{ getContentType(approval.approvable_type) }}
                            </span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(approval.status)]">
                                {{ formatStatus(approval.status) }}
                            </span>
                        </div>
                        <h3 class="font-semibold text-surface-900">
                            {{ approval.approvable?.title || approval.approvable?.stem || 'Content' }}
                        </h3>
                        <p class="text-sm text-surface-500 mt-1">
                            Submitted by <span class="font-medium text-surface-700">{{ approval.submitter?.first_name }} {{ approval.submitter?.last_name }}</span>
                            · {{ formatDate(approval.submitted_at) }}
                        </p>
                    </div>
                    <div class="ml-4 flex gap-2 shrink-0">
                        <a :href="`/sme/review/${approval.id}`"
                           class="px-3 py-1.5 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition-colors">
                            Review
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState v-if="!approvals.data?.length" title="No content found" description="No content matches your filters." />

        <!-- Pagination -->
        <div v-if="approvals.links && approvals.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in approvals.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Clock, CheckCircle, XCircle, MessageSquare } from 'lucide-vue-next';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    approvals: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    type: props.filters.type || 'all',
    status: props.filters.status || '',
});

function applyFilters() {
    router.get('/sme', {
        type: form.type !== 'all' ? form.type : undefined,
        status: form.status || undefined,
    }, { preserveState: true });
}

function getContentType(type) {
    if (!type) return 'unknown';
    const parts = type.split('\\');
    return parts[parts.length - 1].toLowerCase();
}

function statusBadge(status) {
    const map = {
        pending: 'bg-warning-50 text-warning-700',
        approved: 'bg-success-50 text-success-700',
        rejected: 'bg-error-50 text-error-700',
        changes_requested: 'bg-primary-50 text-primary-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatStatus(status) {
    return (status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

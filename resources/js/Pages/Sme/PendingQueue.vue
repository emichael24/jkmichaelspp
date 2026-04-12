<template>
    <Head title="Pending Review" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Pending Review</h1>
            <p class="text-surface-500 mt-1 text-sm">Content awaiting your review and approval.</p>
        </div>

        <!-- Type filter -->
        <div class="flex gap-3">
            <select v-model="form.type" @change="applyFilters" class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500">
                <option value="">All Types</option>
                <option value="lesson">Lessons</option>
                <option value="question">Questions</option>
                <option value="course">Courses</option>
            </select>
        </div>

        <!-- Queue -->
        <div class="space-y-3">
            <a v-for="approval in approvals.data" :key="approval.id" :href="`/sme/review/${approval.id}`"
               class="block bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 capitalize">
                                {{ getContentType(approval.approvable_type) }}
                            </span>
                        </div>
                        <h3 class="font-semibold text-surface-900">
                            {{ approval.approvable?.title || approval.approvable?.stem || 'Content' }}
                        </h3>
                        <p class="text-sm text-surface-400 mt-1">
                            By {{ approval.submitter?.first_name }} {{ approval.submitter?.last_name }}
                            · {{ formatDate(approval.submitted_at) }}
                        </p>
                    </div>
                    <span class="text-primary-600 text-sm font-medium">Review →</span>
                </div>
            </a>
        </div>

        <EmptyState v-if="!approvals.data?.length" title="All Caught Up" description="No content pending your review." />

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
import SmeLayout from '@/Layouts/SmeLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    approvals: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ type: props.filters.type || '' });

function applyFilters() {
    router.get('/sme/pending', { type: form.type || undefined }, { preserveState: true });
}

function getContentType(type) {
    if (!type) return 'unknown';
    const parts = type.split('\\');
    return parts[parts.length - 1].toLowerCase();
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

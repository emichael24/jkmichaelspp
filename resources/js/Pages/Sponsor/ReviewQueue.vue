<template>
    <Head title="Review Queue" />

    <div class="space-y-6">
        <h1 class="text-xl font-display font-bold text-surface-900">Review Queue</h1>

        <div v-if="submissions.data && submissions.data.length" class="space-y-3">
            <a
                v-for="sub in submissions.data"
                :key="sub.id"
                :href="`/sponsor/submissions/${sub.id}`"
                class="block bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-surface-900 mb-1">{{ sub.task?.title || 'Task' }}</h3>
                        <p class="text-sm text-surface-500">
                            <span class="font-medium text-surface-700">{{ sub.task?.enrollment?.user?.first_name }} {{ sub.task?.enrollment?.user?.last_name }}</span>
                            · {{ sub.task?.enrollment?.track?.title }}
                        </p>
                        <p v-if="sub.content" class="text-sm text-surface-400 mt-1 line-clamp-2">{{ sub.content }}</p>
                    </div>
                    <div class="ml-4 flex flex-col items-end gap-2 shrink-0">
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(sub.status)]">
                            {{ formatStatus(sub.status) }}
                        </span>
                        <span class="text-xs text-surface-400">
                            {{ formatDate(sub.submitted_at) }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-3 text-xs text-surface-400">
                    <span v-if="sub.file_urls?.length" class="flex items-center gap-1">
                        <Paperclip class="w-3.5 h-3.5" /> {{ sub.file_urls.length }} file(s)
                    </span>
                    <span v-if="sub.evidence?.length" class="flex items-center gap-1">
                        <Camera class="w-3.5 h-3.5" /> {{ sub.evidence.length }} evidence
                    </span>
                    <span class="flex items-center gap-1">
                        <Hash class="w-3.5 h-3.5" /> Submission #{{ sub.submission_number }}
                    </span>
                </div>
            </a>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <CheckCircle class="w-12 h-12 text-success-300 mx-auto mb-3" />
            <h2 class="text-lg font-semibold text-surface-700 mb-2">All Caught Up</h2>
            <p class="text-sm text-surface-500">No submissions awaiting your review.</p>
        </div>

        <!-- Pagination -->
        <div v-if="submissions.links && submissions.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in submissions.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { Paperclip, Camera, Hash, CheckCircle } from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

defineProps({
    submissions: { type: Object, default: () => ({ data: [] }) },
});

function statusBadge(status) {
    const map = {
        submitted: 'bg-warning-50 text-warning-700',
        in_review: 'bg-primary-50 text-primary-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatStatus(s) {
    return s?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

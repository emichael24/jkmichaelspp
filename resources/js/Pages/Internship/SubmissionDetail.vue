<template>
    <Head :title="submission.task?.title || 'Submission Detail'" />

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="/internship/submissions" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <div>
                <h1 class="text-xl font-display font-bold text-surface-900">{{ submission.task?.title }}</h1>
                <p class="text-sm text-surface-500">Submission #{{ submission.submission_number }} · {{ formatDate(submission.submitted_at) }}</p>
            </div>
        </div>

        <!-- Status -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5">
            <div class="flex items-center justify-between">
                <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusBadge(submission.status)]">
                    {{ formatStatus(submission.status) }}
                </span>
                <span v-if="submission.time_log_minutes" class="text-sm text-surface-500">
                    <Clock class="w-4 h-4 inline" /> {{ submission.time_log_minutes }} min logged
                </span>
            </div>
        </div>

        <!-- Content -->
        <div v-if="submission.content" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="text-sm font-semibold text-surface-700 mb-2">Submission Notes</h2>
            <p class="text-sm text-surface-600 whitespace-pre-wrap">{{ submission.content }}</p>
        </div>

        <!-- Files -->
        <div v-if="submission.file_urls?.length" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="text-sm font-semibold text-surface-700 mb-2">Files</h2>
            <div class="space-y-2">
                <a v-for="(url, i) in submission.file_urls" :key="i" :href="url" target="_blank"
                   class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                    <FileText class="w-4 h-4" />
                    <span>File {{ i + 1 }}</span>
                </a>
            </div>
        </div>

        <!-- Evidence -->
        <div v-if="submission.evidence?.length" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="text-sm font-semibold text-surface-700 mb-2">Evidence ({{ submission.evidence.length }})</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div v-for="ev in submission.evidence" :key="ev.id" class="bg-surface-50 rounded-lg p-3">
                    <div class="flex items-center gap-2 mb-2">
                        <Camera class="w-4 h-4 text-surface-400" />
                        <span class="text-xs font-medium text-surface-600 capitalize">{{ ev.type.replace(/_/g, ' ') }}</span>
                    </div>
                    <a :href="ev.file_url" target="_blank" class="text-xs text-primary-600 hover:underline">View file</a>
                    <p v-if="ev.caption" class="text-xs text-surface-400 mt-1">{{ ev.caption }}</p>
                    <p v-if="ev.file_size_bytes" class="text-[10px] text-surface-300 mt-0.5">{{ formatSize(ev.file_size_bytes) }}</p>
                </div>
            </div>
        </div>

        <!-- Sponsor Reviews -->
        <div v-if="submission.reviews?.length" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="text-sm font-semibold text-surface-700 mb-3">Sponsor Feedback</h2>
            <div class="space-y-3">
                <div v-for="rev in submission.reviews" :key="rev.id" class="p-4 bg-surface-50 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-surface-900">{{ rev.sponsor?.first_name }} {{ rev.sponsor?.last_name }}</span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', rev.decision === 'approved' ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700']">
                                {{ rev.decision === 'approved' ? 'Approved' : 'Revision Requested' }}
                            </span>
                        </div>
                        <span v-if="rev.overall_score != null" class="text-lg font-bold text-accent-600">{{ rev.overall_score }}/5</span>
                    </div>
                    <p v-if="rev.feedback" class="text-sm text-surface-600">{{ rev.feedback }}</p>
                    <div v-if="rev.risk_flags?.length" class="flex gap-1 mt-2">
                        <span v-for="flag in rev.risk_flags" :key="flag" class="px-1.5 py-0.5 bg-warning-50 text-warning-700 rounded text-[10px] font-medium capitalize">{{ flag.replace(/_/g, ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resubmit -->
        <div v-if="submission.status === 'revision_requested'" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="text-sm font-semibold text-surface-700 mb-3">Resubmit</h2>
            <form @submit.prevent="submitResubmission" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Updated Notes</label>
                    <textarea v-model="resubmitForm.content" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"
                              placeholder="Describe what you changed..."></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="resubmitForm.processing" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                        Resubmit
                    </button>
                </div>
            </form>
        </div>

        <!-- Milestone info -->
        <div v-if="submission.task?.milestone" class="text-xs text-surface-400 text-center">
            Milestone: {{ submission.task.milestone.title }} (Week {{ submission.task.milestone.week_number }})
        </div>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Camera, Clock } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submission: { type: Object, required: true },
});

const resubmitForm = useForm({
    content: '',
    file_urls: [],
    time_log_minutes: null,
});

function submitResubmission() {
    resubmitForm.post(`/internship/submissions/${props.submission.id}/resubmit`);
}

function statusBadge(status) {
    const map = {
        submitted: 'bg-warning-50 text-warning-700',
        in_review: 'bg-primary-50 text-primary-700',
        approved: 'bg-success-50 text-success-700',
        revision_requested: 'bg-danger-50 text-danger-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatStatus(s) {
    return s?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>

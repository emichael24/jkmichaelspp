<template>
    <Head :title="`Review: ${submission.task?.title}`" />

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="/sponsor/review-queue" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <div>
                <h1 class="text-xl font-display font-bold text-surface-900">{{ submission.task?.title }}</h1>
                <p class="text-sm text-surface-500">
                    {{ submission.user?.first_name }} {{ submission.user?.last_name }}
                    · Submission #{{ submission.submission_number }}
                </p>
            </div>
        </div>

        <!-- Submission Content -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5 space-y-4">
            <h2 class="font-semibold text-surface-900">Submission Content</h2>

            <div v-if="submission.content" class="prose prose-sm max-w-none text-surface-600">
                <p class="whitespace-pre-wrap">{{ submission.content }}</p>
            </div>

            <!-- Files -->
            <div v-if="submission.file_urls?.length">
                <h3 class="text-sm font-medium text-surface-700 mb-2">Attached Files</h3>
                <div class="space-y-1">
                    <a v-for="(url, i) in submission.file_urls" :key="i" :href="url" target="_blank"
                       class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                        <FileText class="w-4 h-4" />
                        File {{ i + 1 }}
                    </a>
                </div>
            </div>

            <!-- Evidence -->
            <div v-if="submission.evidence?.length">
                <h3 class="text-sm font-medium text-surface-700 mb-2">Evidence</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div v-for="ev in submission.evidence" :key="ev.id" class="bg-surface-50 rounded-lg p-3">
                        <div class="flex items-center gap-2 mb-1">
                            <Camera class="w-4 h-4 text-surface-400" />
                            <span class="text-xs font-medium text-surface-600 capitalize">{{ ev.type.replace(/_/g, ' ') }}</span>
                        </div>
                        <a :href="ev.file_url" target="_blank" class="text-xs text-primary-600 hover:underline">View</a>
                        <p v-if="ev.caption" class="text-xs text-surface-400 mt-1">{{ ev.caption }}</p>
                    </div>
                </div>
            </div>

            <div v-if="submission.task?.milestone" class="text-xs text-surface-400">
                <span class="font-medium">Milestone:</span> {{ submission.task.milestone.title }} (Week {{ submission.task.milestone.week_number }})
            </div>
        </div>

        <!-- Previous Reviews -->
        <div v-if="submission.reviews?.length" class="bg-white rounded-xl border border-surface-200/60 p-5 space-y-3">
            <h2 class="font-semibold text-surface-900">Previous Reviews</h2>
            <div v-for="rev in submission.reviews" :key="rev.id" class="p-3 bg-surface-50 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', rev.decision === 'approved' ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700']">
                        {{ rev.decision === 'approved' ? 'Approved' : 'Revision Requested' }}
                    </span>
                    <span class="text-xs text-surface-400">{{ formatDate(rev.reviewed_at) }}</span>
                </div>
                <p v-if="rev.feedback" class="text-sm text-surface-600">{{ rev.feedback }}</p>
                <p v-if="rev.overall_score != null" class="text-xs text-surface-500 mt-1">Score: {{ rev.overall_score }}/5</p>
            </div>
        </div>

        <!-- Review Form -->
        <div v-if="submission.status !== 'approved'" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="font-semibold text-surface-900 mb-4">Submit Review</h2>

            <form @submit.prevent="submitReview" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Decision</label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.decision" type="radio" value="approved" class="text-success-600" />
                            <span class="text-sm">Approve</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.decision" type="radio" value="revision_requested" class="text-warning-600" />
                            <span class="text-sm">Request Revision</span>
                        </label>
                    </div>
                    <p v-if="form.errors.decision" class="text-xs text-danger-600 mt-1">{{ form.errors.decision }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Overall Score (0-5)</label>
                    <input v-model.number="form.overall_score" type="number" min="0" max="5" step="0.5"
                           class="w-32 px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Feedback</label>
                    <textarea v-model="form.feedback" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"
                              placeholder="Provide constructive feedback..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Risk Flags (optional)</label>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="flag in ['late', 'quality', 'team_issue', 'communication']" :key="flag"
                               class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" :value="flag" v-model="form.risk_flags" class="rounded border-surface-300 text-warning-600" />
                            <span class="text-sm capitalize">{{ flag.replace(/_/g, ' ') }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-surface-100">
                    <button type="submit" :disabled="form.processing || !form.decision"
                            :class="[
                                'px-6 py-2 rounded-lg text-sm font-medium transition-colors',
                                form.decision === 'approved' ? 'bg-success-600 text-white hover:bg-success-700' : 'bg-warning-600 text-white hover:bg-warning-700',
                                (!form.decision || form.processing) ? 'opacity-50 cursor-not-allowed' : '',
                            ]">
                        {{ form.decision === 'approved' ? 'Approve Submission' : 'Request Revision' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Camera } from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

const props = defineProps({
    submission: { type: Object, required: true },
});

const form = useForm({
    decision: '',
    rubric_scores: null,
    overall_score: null,
    feedback: '',
    feedback_audio_url: null,
    risk_flags: [],
});

function submitReview() {
    form.post(`/sponsor/submissions/${props.submission.id}/review`);
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <Head title="Content Review" />

    <div class="space-y-6">
        <!-- Back -->
        <a href="/sme/pending" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to queue
        </a>

        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Review Content</h1>
                <p class="text-surface-500 text-sm mt-1">
                    {{ getContentType(approval.approvable_type) }} ·
                    Submitted by {{ approval.submitter?.first_name }} {{ approval.submitter?.last_name }}
                    · {{ formatDate(approval.submitted_at) }}
                </p>
            </div>
            <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusBadge(approval.status)]">
                {{ formatStatus(approval.status) }}
            </span>
        </div>

        <!-- Content Preview -->
        <Card title="Content Preview">
            <div class="prose prose-sm max-w-none">
                <h3>{{ content.title || content.stem }}</h3>

                <!-- For lessons -->
                <SafeHtml v-if="content.content_html" :content="content.content_html" />

                <!-- For questions -->
                <div v-if="content.stem">
                    <p class="font-semibold">{{ content.stem }}</p>
                    <ul v-if="content.options" class="mt-2">
                        <li v-for="opt in content.options" :key="opt.id"
                            :class="{'text-success-700 font-medium': content.correct_answer_ids?.includes(opt.id)}">
                            {{ opt.text }}
                            <span v-if="content.correct_answer_ids?.includes(opt.id)" class="text-xs">(correct)</span>
                        </li>
                    </ul>
                    <div v-if="content.explanation_correct" class="mt-3 p-3 bg-success-50 rounded-lg text-sm">
                        <strong>Explanation:</strong> {{ content.explanation_correct }}
                    </div>
                </div>

                <!-- For courses -->
                <div v-if="content.description && !content.stem && !content.content_html">
                    <p>{{ content.description }}</p>
                </div>
            </div>
        </Card>

        <!-- Diff with Previous Version -->
        <Card v-if="previousVersion" title="Changes from Previous Version">
            <div class="text-sm text-surface-500 mb-2">
                Version {{ previousVersion.version_number }} ·
                {{ formatDate(previousVersion.created_at) }}
            </div>
            <div class="bg-surface-50 rounded-lg p-4 font-mono text-xs space-y-1 max-h-96 overflow-y-auto">
                <div v-for="(value, key) in diffFields" :key="key" class="flex gap-2">
                    <span class="text-surface-400 w-32 shrink-0 text-right">{{ key }}:</span>
                    <span v-if="value.changed" class="flex-1">
                        <span class="bg-error-100 text-error-700 line-through px-1 rounded">{{ truncate(value.old) }}</span>
                        →
                        <span class="bg-success-100 text-success-700 px-1 rounded">{{ truncate(value.new) }}</span>
                    </span>
                    <span v-else class="text-surface-400 flex-1">{{ truncate(value.old) }}</span>
                </div>
            </div>
        </Card>

        <!-- Approval History -->
        <Card v-if="history?.length" title="Approval History">
            <div class="space-y-3">
                <div v-for="h in history" :key="h.id"
                     class="flex items-start gap-3 text-sm border-b border-surface-100 pb-3 last:border-0 last:pb-0">
                    <span :class="['w-2 h-2 rounded-full mt-1.5 shrink-0', statusDot(h.status)]" />
                    <div class="flex-1">
                        <p class="text-surface-700">
                            <span class="font-semibold capitalize">{{ formatStatus(h.status) }}</span>
                            <span v-if="h.reviewer"> by {{ h.reviewer.first_name }} {{ h.reviewer.last_name }}</span>
                        </p>
                        <p v-if="h.review_notes" class="text-surface-500 mt-0.5">{{ h.review_notes }}</p>
                        <p class="text-surface-400 text-xs mt-0.5">{{ formatDate(h.reviewed_at || h.submitted_at) }}</p>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Action Buttons (only for pending) -->
        <div v-if="approval.status === 'pending'" class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-4">
            <h3 class="font-semibold text-surface-900">Review Actions</h3>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Notes (optional for approval, required for rejection)</label>
                <textarea v-model="notes" rows="3" placeholder="Add your review notes..."
                    class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
            </div>

            <div class="flex flex-wrap gap-3">
                <Button @click="handleApprove(false)" :loading="processing" variant="success" icon-left>
                    <CheckCircle class="w-4 h-4" /> Approve
                </Button>
                <Button @click="handleApprove(true)" :loading="processing" variant="primary" icon-left>
                    <CheckCircle class="w-4 h-4" /> Approve & Publish
                </Button>
                <Button @click="handleRequestChanges" :loading="processing" variant="warning" icon-left>
                    <MessageSquare class="w-4 h-4" /> Request Changes
                </Button>
                <Button @click="handleReject" :loading="processing" variant="danger" icon-left>
                    <XCircle class="w-4 h-4" /> Reject
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import SafeHtml from '@/Components/SafeHtml.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeft, CheckCircle, XCircle, MessageSquare } from 'lucide-vue-next';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    approval: { type: Object, required: true },
    content: { type: Object, required: true },
    previousVersion: { type: Object, default: null },
    history: { type: Array, default: () => [] },
});

const notes = ref('');
const processing = ref(false);

const diffFields = computed(() => {
    if (!props.previousVersion?.data) return {};
    const oldData = props.previousVersion.data;
    const newData = props.content;
    const fields = {};
    const keys = new Set([...Object.keys(oldData), ...Object.keys(newData)]);
    const skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

    keys.forEach(key => {
        if (skip.includes(key)) return;
        const oldVal = JSON.stringify(oldData[key] ?? '');
        const newVal = JSON.stringify(newData[key] ?? '');
        fields[key] = { old: oldData[key], new: newData[key], changed: oldVal !== newVal };
    });
    return fields;
});

function handleApprove(publish) {
    processing.value = true;
    router.post(`/sme/review/${props.approval.id}/approve`, {
        notes: notes.value || null,
        publish,
    }, {
        onFinish: () => { processing.value = false; },
    });
}

function handleReject() {
    if (!notes.value) { alert('Please add review notes for rejection.'); return; }
    processing.value = true;
    router.post(`/sme/review/${props.approval.id}/reject`, { notes: notes.value }, {
        onFinish: () => { processing.value = false; },
    });
}

function handleRequestChanges() {
    if (!notes.value) { alert('Please add notes describing the changes needed.'); return; }
    processing.value = true;
    router.post(`/sme/review/${props.approval.id}/request-changes`, { notes: notes.value }, {
        onFinish: () => { processing.value = false; },
    });
}

function getContentType(type) {
    if (!type) return 'Unknown';
    const parts = type.split('\\');
    return parts[parts.length - 1];
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

function statusDot(status) {
    const map = {
        pending: 'bg-warning-400',
        approved: 'bg-success-400',
        rejected: 'bg-error-400',
        changes_requested: 'bg-primary-400',
    };
    return map[status] || 'bg-surface-300';
}

function formatStatus(s) {
    return (s || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function truncate(val, len = 80) {
    const s = typeof val === 'object' ? JSON.stringify(val) : String(val ?? '');
    return s.length > len ? s.substring(0, len) + '…' : s;
}
</script>

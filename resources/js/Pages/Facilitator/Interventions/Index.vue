<template>
    <Head :title="`Interventions – ${cohort.name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Interventions</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }}</p>
                </div>
            </div>
            <button @click="showCreate = true" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                Log Intervention
            </button>
        </div>

        <!-- Interventions List -->
        <div v-if="interventions.data && interventions.data.length" class="space-y-3">
            <div
                v-for="intervention in interventions.data"
                :key="intervention.id"
                class="bg-white rounded-xl border border-surface-200/60 p-5"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-start gap-3">
                        <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', typeIconBg(intervention.type)]">
                            <component :is="typeIcon(intervention.type)" class="w-4 h-4 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-surface-900 text-sm capitalize">{{ intervention.type.replace(/_/g, ' ') }}</span>
                                <span class="text-xs text-surface-400">→</span>
                                <span class="text-sm font-medium text-primary-600">
                                    {{ intervention.learner?.first_name }} {{ intervention.learner?.last_name }}
                                </span>
                            </div>
                            <p class="text-xs text-surface-400 mt-0.5">
                                By {{ intervention.facilitator?.first_name }} {{ intervention.facilitator?.last_name }}
                                · {{ formatDate(intervention.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            :value="intervention.status"
                            @change="updateStatus(intervention, $event.target.value)"
                            :class="['px-2 py-1 rounded-lg text-xs font-semibold border-0', statusSelectClass(intervention.status)]"
                        >
                            <option value="pending">Pending</option>
                            <option value="sent">Sent</option>
                            <option value="acknowledged">Acknowledged</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <div class="ml-12 space-y-1">
                    <p class="text-sm text-surface-700"><span class="font-medium text-surface-500">Reason:</span> {{ intervention.reason }}</p>
                    <p class="text-sm text-surface-700"><span class="font-medium text-surface-500">Action:</span> {{ intervention.action_taken }}</p>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <ShieldAlert class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500">No interventions logged yet.</p>
        </div>

        <!-- Pagination -->
        <div v-if="interventions.links && interventions.last_page > 1" class="flex justify-center gap-1">
            <a
                v-for="link in interventions.links"
                :key="link.label"
                :href="link.url"
                :class="[
                    'px-3 py-1.5 rounded-lg text-sm',
                    link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100',
                    !link.url ? 'opacity-50 pointer-events-none' : '',
                ]"
                v-html="link.label"
            />
        </div>

        <!-- Create Intervention Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showCreate = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Log Intervention</h3>
                <form @submit.prevent="submitIntervention">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Learner</label>
                            <select v-model="form.learner_id" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                <option value="">Select learner...</option>
                                <option v-for="member in learnerMembers" :key="member.user_id" :value="member.user_id">
                                    {{ member.learner?.first_name || 'Learner' }} {{ member.learner?.last_name || '' }}
                                </option>
                            </select>
                            <p v-if="form.errors.learner_id" class="text-xs text-danger-600 mt-1">{{ form.errors.learner_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                            <select v-model="form.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                <option value="nudge">Nudge</option>
                                <option value="coaching">Coaching</option>
                                <option value="escalation">Escalation</option>
                                <option value="plan_adjustment">Plan Adjustment</option>
                                <option value="rest_day">Rest Day</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Reason</label>
                            <textarea v-model="form.reason" rows="2" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Why is this intervention needed?"></textarea>
                            <p v-if="form.errors.reason" class="text-xs text-danger-600 mt-1">{{ form.errors.reason }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Action Taken</label>
                            <textarea v-model="form.action_taken" rows="2" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="What action was taken or message was sent?"></textarea>
                            <p v-if="form.errors.action_taken" class="text-xs text-danger-600 mt-1">{{ form.errors.action_taken }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            Log Intervention
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, ShieldAlert,
    MessageSquare, Heart, ArrowUp, Zap, Coffee,
} from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    interventions: { type: Object, default: () => ({ data: [] }) },
});

const showCreate = ref(false);

const form = useForm({
    learner_id: '',
    type: 'nudge',
    reason: '',
    action_taken: '',
});

// We'd ideally get members from the cohort show page. For now we use the intervention data.
const learnerMembers = computed(() => {
    // Deduplicate learners from intervention data
    const seen = new Set();
    const members = [];
    (props.interventions.data || []).forEach(i => {
        if (i.learner && !seen.has(i.learner.id)) {
            seen.add(i.learner.id);
            members.push({ user_id: i.learner.id, learner: i.learner });
        }
    });
    return members;
});

function submitIntervention() {
    form.post(`/facilitator/cohorts/${props.cohort.id}/interventions`, {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}

function updateStatus(intervention, status) {
    router.put(`/facilitator/cohorts/${props.cohort.id}/interventions/${intervention.id}/status`, {
        status,
    });
}

function typeIconBg(type) {
    const map = {
        nudge: 'bg-primary-500',
        coaching: 'bg-accent-500',
        escalation: 'bg-danger-500',
        plan_adjustment: 'bg-warning-500',
        rest_day: 'bg-success-500',
    };
    return map[type] || 'bg-surface-400';
}

function typeIcon(type) {
    const map = {
        nudge: MessageSquare,
        coaching: Heart,
        escalation: ArrowUp,
        plan_adjustment: Zap,
        rest_day: Coffee,
    };
    return map[type] || MessageSquare;
}

function statusSelectClass(status) {
    const map = {
        pending: 'bg-warning-50 text-warning-700',
        sent: 'bg-primary-50 text-primary-700',
        acknowledged: 'bg-accent-50 text-accent-700',
        resolved: 'bg-success-50 text-success-700',
    };
    return map[status] || map.pending;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

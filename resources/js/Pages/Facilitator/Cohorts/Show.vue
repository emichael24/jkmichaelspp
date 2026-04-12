<template>
    <Head :title="cohort.name" />

    <div class="space-y-6">
        <!-- Cohort Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="/facilitator/cohorts" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">{{ cohort.name }}</h1>
                    <p class="text-sm text-surface-500">{{ cohort.course?.title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a :href="`/facilitator/cohorts/${cohort.id}/plans`" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-white border border-surface-200 rounded-lg hover:bg-surface-50 transition-colors">
                    <CalendarDays class="w-4 h-4" /> Plans
                </a>
                <a :href="`/facilitator/cohorts/${cohort.id}/announcements`" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-white border border-surface-200 rounded-lg hover:bg-surface-50 transition-colors">
                    <Megaphone class="w-4 h-4" /> Announcements
                </a>
                <a :href="`/facilitator/cohorts/${cohort.id}/interventions`" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-white border border-surface-200 rounded-lg hover:bg-surface-50 transition-colors">
                    <ShieldAlert class="w-4 h-4" /> Interventions
                </a>
                <a :href="`/facilitator/cohorts/${cohort.id}/edit`" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors">
                    <Settings class="w-4 h-4" /> Edit
                </a>
            </div>
        </div>

        <!-- Health Score Gauge -->
        <div class="grid gap-4 md:grid-cols-5">
            <div class="md:col-span-1 bg-white rounded-xl border border-surface-200/60 p-6 text-center">
                <div class="relative w-24 h-24 mx-auto mb-3">
                    <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="none" stroke-width="8" class="stroke-surface-100" />
                        <circle
                            cx="50" cy="50" r="40" fill="none" stroke-width="8"
                            stroke-linecap="round"
                            :stroke="gaugeColor(health.score)"
                            :stroke-dasharray="`${(health.score / 100) * 251.2} 251.2`"
                        />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-2xl font-bold text-surface-900">{{ Math.round(health.score) }}</span>
                    </div>
                </div>
                <p class="text-sm font-semibold text-surface-700">Health Score</p>
                <p class="text-xs text-surface-400">{{ health.learner_count }} learners</p>
            </div>

            <!-- Health Breakdown -->
            <div class="md:col-span-4 bg-white rounded-xl border border-surface-200/60 p-6">
                <h3 class="text-sm font-semibold text-surface-700 mb-4">Health Breakdown</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div v-for="metric in healthMetrics" :key="metric.label">
                        <p class="text-xs text-surface-500 mb-1">{{ metric.label }}</p>
                        <p class="text-xl font-bold" :class="metric.color">{{ metric.value }}%</p>
                        <div class="w-full h-1.5 bg-surface-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full rounded-full transition-all" :class="metric.barColor" :style="{ width: `${metric.value}%` }" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mastery Heatmap -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6 overflow-x-auto">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Mastery Heatmap (Learners × Domains)</h3>
            <div v-if="masteryHeatmap.domains && masteryHeatmap.domains.length" class="min-w-[600px]">
                <table class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="text-left py-2 px-2 font-medium text-surface-500 w-40">Learner</th>
                            <th v-for="d in masteryHeatmap.domains" :key="d" class="text-center py-2 px-1 font-medium text-surface-500 truncate max-w-[100px]" :title="d">
                                {{ d.length > 12 ? d.slice(0, 12) + '…' : d }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="learner in masteryHeatmap.learners" :key="learner.id" class="border-t border-surface-50">
                            <td class="py-1.5 px-2">
                                <a :href="`/facilitator/cohorts/${cohort.id}/learners/${learner.id}`" class="text-primary-600 hover:underline font-medium">
                                    {{ learner.name }}
                                </a>
                            </td>
                            <td v-for="d in masteryHeatmap.domains" :key="d" class="text-center py-1.5 px-1">
                                <span
                                    :class="[
                                        'inline-block w-8 h-6 rounded text-[10px] font-bold leading-6',
                                        masteryCell(learner.cells[d]),
                                    ]"
                                >{{ learner.cells[d] != null ? learner.cells[d] : '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-surface-400">No mastery data available yet.</p>
        </div>

        <!-- Engagement Heatmap -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6 overflow-x-auto">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Engagement Heatmap (Last 4 Weeks)</h3>
            <div v-if="engagementHeatmap.learners && engagementHeatmap.learners.length" class="min-w-[600px]">
                <table class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="text-left py-2 px-2 font-medium text-surface-500 w-40">Learner</th>
                            <th
                                v-for="(date, i) in visibleDates"
                                :key="date"
                                class="text-center py-2 px-0.5 font-medium text-surface-400 w-6"
                                :title="date"
                            >{{ dayLabel(date) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="learner in engagementHeatmap.learners" :key="learner.id" class="border-t border-surface-50">
                            <td class="py-1.5 px-2 font-medium text-surface-700">{{ learner.name }}</td>
                            <td v-for="cell in learner.cells" :key="cell.date" class="text-center py-1.5 px-0.5">
                                <span
                                    :class="[
                                        'inline-block w-5 h-5 rounded-sm',
                                        cell.active ? 'bg-success-500' : 'bg-surface-100',
                                    ]"
                                    :title="`${cell.date}: ${cell.minutes} min`"
                                ></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-surface-400">No engagement data available yet.</p>
        </div>

        <!-- Mock Score Distribution & At-Risk side by side -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Mock Distribution -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                <h3 class="text-sm font-semibold text-surface-700 mb-4">Mock Score Distribution</h3>
                <div v-if="mockDistribution.total > 0" class="space-y-2">
                    <div v-for="bucket in mockDistribution.buckets" :key="bucket.label" class="flex items-center gap-2">
                        <span class="text-xs text-surface-500 w-16 text-right">{{ bucket.label }}</span>
                        <div class="flex-1 h-5 bg-surface-100 rounded overflow-hidden">
                            <div
                                :class="[
                                    'h-full rounded transition-all',
                                    parseInt(bucket.label) >= mockDistribution.pass_line ? 'bg-success-500' : 'bg-warning-400',
                                ]"
                                :style="{ width: `${mockDistribution.total ? (bucket.count / mockDistribution.total) * 100 : 0}%` }"
                            ></div>
                        </div>
                        <span class="text-xs font-medium text-surface-600 w-6 text-right">{{ bucket.count }}</span>
                    </div>
                    <p class="text-xs text-surface-400 mt-2">Pass line: {{ mockDistribution.pass_line }}% | {{ mockDistribution.total }} total attempts</p>
                </div>
                <p v-else class="text-sm text-surface-400">No mock exam data yet.</p>
            </div>

            <!-- At-Risk Learners -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                <h3 class="text-sm font-semibold text-surface-700 mb-4 flex items-center gap-2">
                    <AlertTriangle class="w-4 h-4 text-warning-500" />
                    At-Risk Learners ({{ atRisk.length }})
                </h3>
                <div v-if="atRisk.length" class="space-y-3 max-h-64 overflow-y-auto">
                    <div v-for="learner in atRisk" :key="learner.id" class="p-3 rounded-lg bg-warning-50/50 border border-warning-100">
                        <div class="flex items-center justify-between mb-1">
                            <a :href="`/facilitator/cohorts/${cohort.id}/learners/${learner.id}`" class="text-sm font-semibold text-surface-900 hover:text-primary-600">
                                {{ learner.name }}
                            </a>
                            <span class="text-[10px] text-surface-400">{{ learner.last_active }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-2">
                            <span v-for="reason in learner.reasons" :key="reason" class="px-1.5 py-0.5 bg-warning-100 text-warning-800 text-[10px] rounded-full font-medium">
                                {{ reason }}
                            </span>
                        </div>
                        <p class="text-xs text-surface-600">{{ learner.suggestion }}</p>
                        <button
                            @click="openNudge(learner)"
                            class="mt-2 text-xs font-medium text-primary-600 hover:text-primary-700"
                        >Send Nudge →</button>
                    </div>
                </div>
                <p v-else class="text-sm text-success-600 flex items-center gap-2">
                    <CheckCircle class="w-4 h-4" />
                    All learners on track!
                </p>
            </div>
        </div>

        <!-- Members Table -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-surface-700">Members ({{ members.length }})</h3>
                <button @click="showAddMember = true" class="text-sm font-medium text-primary-600 hover:text-primary-700">+ Add Member</button>
            </div>

            <div class="space-y-2">
                <div v-for="member in members" :key="member.id" class="flex items-center justify-between p-3 rounded-lg bg-surface-50 hover:bg-surface-100 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 text-xs font-bold">
                            {{ (member.user?.first_name?.[0] || '') + (member.user?.last_name?.[0] || '') }}
                        </div>
                        <div>
                            <a :href="`/facilitator/cohorts/${cohort.id}/learners/${member.user_id}`" class="text-sm font-medium text-surface-900 hover:text-primary-600">
                                {{ member.user?.first_name }} {{ member.user?.last_name }}
                            </a>
                            <p class="text-xs text-surface-400">{{ member.user?.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', member.role === 'co_facilitator' ? 'bg-accent-50 text-accent-700' : 'bg-surface-100 text-surface-600']">
                            {{ member.role === 'co_facilitator' ? 'Co-facilitator' : 'Learner' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Member Modal -->
        <div v-if="showAddMember" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAddMember = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Add Member</h3>
                <form @submit.prevent="addMember">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Email Address</label>
                        <input v-model="addForm.email" type="email" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500" placeholder="learner@example.com" />
                        <p v-if="addForm.errors.email" class="text-xs text-danger-600 mt-1">{{ addForm.errors.email }}</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Role</label>
                        <select v-model="addForm.role" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                            <option value="learner">Learner</option>
                            <option value="co_facilitator">Co-facilitator</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showAddMember = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="addForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            Add
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Nudge Modal -->
        <div v-if="nudgeTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="nudgeTarget = null">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Send Nudge to {{ nudgeTarget.name }}</h3>
                <form @submit.prevent="sendNudge">
                    <div class="mb-4 space-y-2">
                        <button
                            v-for="(text, key) in nudgeTemplates"
                            :key="key"
                            type="button"
                            @click="nudgeForm.message = text"
                            :class="[
                                'w-full text-left p-3 rounded-lg border text-sm transition-colors',
                                nudgeForm.message === text ? 'border-primary-500 bg-primary-50' : 'border-surface-200 hover:bg-surface-50',
                            ]"
                        >{{ text }}</button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Custom Message</label>
                        <textarea v-model="nudgeForm.message" rows="2" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="nudgeTarget = null" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="nudgeForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            Send Nudge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, CalendarDays, Megaphone, ShieldAlert,
    Settings, AlertTriangle, CheckCircle,
} from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    health: { type: Object, default: () => ({}) },
    atRisk: { type: Array, default: () => [] },
    masteryHeatmap: { type: Object, default: () => ({ domains: [], learners: [] }) },
    engagementHeatmap: { type: Object, default: () => ({ dates: [], learners: [] }) },
    mockDistribution: { type: Object, default: () => ({ buckets: [], total: 0 }) },
    members: { type: Array, default: () => [] },
});

const showAddMember = ref(false);
const nudgeTarget = ref(null);

const addForm = useForm({ email: '', role: 'learner' });
const nudgeForm = useForm({ learner_id: null, message: '' });

const nudgeTemplates = {
    miss_you: 'We miss you! Come back and continue your streak.',
    mock_prep: 'Your mock exam is tomorrow. Review your weak areas tonight.',
    great_week: 'Great progress this week! Keep it up.',
    behind: "You're falling behind schedule. Let's get back on track.",
    streak_risk: 'Your 7-day streak is at risk! Log in today to keep it alive.',
};

const healthMetrics = computed(() => [
    { label: 'Engagement', value: Math.round(props.health.engagement_rate || 0), color: metricColor(props.health.engagement_rate), barColor: metricBarColor(props.health.engagement_rate) },
    { label: 'Avg Readiness', value: Math.round(props.health.avg_readiness || 0), color: metricColor(props.health.avg_readiness), barColor: metricBarColor(props.health.avg_readiness) },
    { label: 'On-Time Rate', value: Math.round(props.health.on_time_rate || 0), color: metricColor(props.health.on_time_rate), barColor: metricBarColor(props.health.on_time_rate) },
    { label: 'Intervention Rate', value: Math.round(props.health.intervention_rate || 0), color: 'text-surface-700', barColor: 'bg-surface-400' },
]);

const visibleDates = computed(() => props.engagementHeatmap.dates || []);

function metricColor(val) {
    if (val >= 70) return 'text-success-600';
    if (val >= 40) return 'text-warning-600';
    return 'text-danger-600';
}

function metricBarColor(val) {
    if (val >= 70) return 'bg-success-500';
    if (val >= 40) return 'bg-warning-500';
    return 'bg-danger-500';
}

function gaugeColor(score) {
    if (score >= 70) return '#22c55e';
    if (score >= 40) return '#eab308';
    return '#ef4444';
}

function masteryCell(value) {
    if (value == null) return 'bg-surface-100 text-surface-400';
    if (value >= 75) return 'bg-success-100 text-success-800';
    if (value >= 50) return 'bg-warning-100 text-warning-800';
    return 'bg-danger-100 text-danger-800';
}

function dayLabel(dateStr) {
    const day = new Date(dateStr).getDate();
    return day;
}

function openNudge(learner) {
    nudgeTarget.value = learner;
    nudgeForm.learner_id = learner.id;
    nudgeForm.message = nudgeTemplates.miss_you;
}

function addMember() {
    addForm.post(`/facilitator/cohorts/${props.cohort.id}/members`, {
        onSuccess: () => {
            showAddMember.value = false;
            addForm.reset();
        },
    });
}

function sendNudge() {
    nudgeForm.post(`/facilitator/cohorts/${props.cohort.id}/nudge`, {
        onSuccess: () => {
            nudgeTarget.value = null;
            nudgeForm.reset();
        },
    });
}
</script>

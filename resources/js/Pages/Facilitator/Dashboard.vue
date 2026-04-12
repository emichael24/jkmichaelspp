<template>
    <Head title="Facilitator Dashboard" />

    <div class="space-y-8">
        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-700 to-accent-600 p-6 sm:p-8">
            <div class="absolute top-0 right-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -translate-y-1/3 translate-x-1/3" />
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-display font-bold text-white">
                        Welcome back, {{ $page.props.auth?.user?.name || 'Facilitator' }}
                    </h1>
                    <p class="mt-1 text-white/70">Here's how your cohorts are doing today.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center">
                        <p class="text-white font-bold text-xl leading-none">{{ totalLearners }}</p>
                        <p class="text-white/60 text-[10px] font-medium">learners</p>
                    </div>
                    <div :class="['backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center', totalAtRisk > 0 ? 'bg-warning-500/20' : 'bg-white/10']">
                        <p :class="['font-bold text-xl leading-none', totalAtRisk > 0 ? 'text-warning-300' : 'text-white']">{{ totalAtRisk }}</p>
                        <p class="text-white/60 text-[10px] font-medium">at risk</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center">
                        <p class="text-white font-bold text-xl leading-none">{{ avgHealth }}%</p>
                        <p class="text-white/60 text-[10px] font-medium">avg health</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center">
                        <Users class="w-5 h-5 text-primary-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-surface-900">{{ totalLearners }}</p>
                        <p class="text-xs text-surface-500">Active Learners</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-accent-50 flex items-center justify-center">
                        <Layers class="w-5 h-5 text-accent-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-surface-900">{{ activeCohortCount }}</p>
                        <p class="text-xs text-surface-500">Active Cohorts</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-warning-50 flex items-center justify-center">
                        <AlertTriangle class="w-5 h-5 text-warning-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold" :class="totalAtRisk > 0 ? 'text-warning-600' : 'text-surface-900'">{{ totalAtRisk }}</p>
                        <p class="text-xs text-surface-500">At-Risk Learners</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-xp-50 flex items-center justify-center">
                        <FileText class="w-5 h-5 text-xp-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-surface-900">{{ pendingSubmissions.length }}</p>
                        <p class="text-xs text-surface-500">Pending Grading</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-success-50 flex items-center justify-center">
                        <Activity class="w-5 h-5 text-success-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-surface-900">{{ avgHealth }}%</p>
                        <p class="text-xs text-surface-500">Avg Health</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left: Cohorts + Grading (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Weekly Engagement Summary -->
                <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                    <h2 class="text-lg font-display font-semibold text-surface-900 mb-4">This Week's Engagement</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div class="text-center p-3 rounded-xl bg-primary-50">
                            <p class="text-2xl font-bold text-primary-700">{{ engagementSummary.activeLearners }}</p>
                            <p class="text-[11px] text-primary-600/70 font-medium">Active Learners</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-accent-50">
                            <p class="text-2xl font-bold text-accent-700">{{ Math.round((engagementSummary.totalMinutes || 0) / 60) }}h</p>
                            <p class="text-[11px] text-accent-600/70 font-medium">Total Time</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-success-50">
                            <p class="text-2xl font-bold text-success-700">{{ engagementSummary.lessonsCompleted }}</p>
                            <p class="text-[11px] text-success-600/70 font-medium">Lessons Done</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-xp-50">
                            <p class="text-2xl font-bold text-xp-700">{{ engagementSummary.quizzesTaken }}</p>
                            <p class="text-[11px] text-xp-600/70 font-medium">Quizzes Taken</p>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-warning-50">
                            <p class="text-2xl font-bold text-warning-700">{{ engagementSummary.avgAccuracy }}%</p>
                            <p class="text-[11px] text-warning-600/70 font-medium">Avg Accuracy</p>
                        </div>
                    </div>
                </div>

                <!-- Cohort Cards -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-display font-semibold text-surface-900">Your Cohorts</h2>
                        <a href="/facilitator/cohorts/create" class="text-sm font-medium text-primary-600 hover:text-primary-700">+ New Cohort</a>
                    </div>

                    <div v-if="cohorts.length === 0" class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
                        <Users class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                        <p class="text-surface-500">No cohorts yet. Create your first cohort to get started.</p>
                    </div>

                    <div v-else class="grid gap-4 md:grid-cols-2">
                        <a
                            v-for="cohort in cohorts"
                            :key="cohort.id"
                            :href="`/facilitator/cohorts/${cohort.id}`"
                            class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md hover:border-primary-200 transition-all group"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-surface-900 truncate group-hover:text-primary-700 transition-colors">
                                        {{ cohort.name }}
                                    </h3>
                                    <p class="text-xs text-surface-500 mt-0.5">{{ cohort.course }}</p>
                                </div>
                                <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', statusClasses(cohort.status)]">
                                    {{ cohort.status }}
                                </span>
                            </div>

                            <div class="mb-3">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="text-surface-500">Health Score</span>
                                    <span class="font-bold" :class="healthColor(cohort.health_score)">{{ cohort.health_score }}%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="healthBarColor(cohort.health_score)"
                                        :style="{ width: `${cohort.health_score}%` }"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-surface-500">
                                <span class="flex items-center gap-1">
                                    <Users class="w-3.5 h-3.5" />
                                    {{ cohort.learner_count }} learners
                                </span>
                                <span v-if="cohort.at_risk_count > 0" class="flex items-center gap-1 text-warning-600">
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    {{ cohort.at_risk_count }} at risk
                                </span>
                                <span class="text-surface-400">{{ cohort.start_date }} – {{ cohort.end_date }}</span>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Grading Queue -->
                <div v-if="pendingSubmissions.length > 0" class="bg-white rounded-xl border border-surface-200/60 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-display font-semibold text-surface-900">Grading Queue</h2>
                        <a href="/facilitator/assignments" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</a>
                    </div>
                    <div class="space-y-3">
                        <div v-for="sub in pendingSubmissions" :key="sub.id" class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-surface-100 transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-xp-50 flex items-center justify-center shrink-0">
                                <FileText class="w-4 h-4 text-xp-600" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-surface-800 truncate">{{ sub.assignment }}</p>
                                <p class="text-xs text-surface-500">{{ sub.learner }} · {{ sub.submitted }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', sub.status === 'resubmitted' ? 'bg-warning-50 text-warning-700' : 'bg-primary-50 text-primary-700']">
                                {{ sub.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar (1/3) -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                    <h3 class="text-sm font-semibold text-surface-700 mb-3">Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="/facilitator/cohorts/create" class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-primary-50 transition-colors group">
                            <Users class="w-4 h-4 text-primary-600" />
                            <span class="text-sm font-medium text-surface-700 group-hover:text-primary-700">Create Cohort</span>
                        </a>
                        <a href="/facilitator/assignments" class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-accent-50 transition-colors group">
                            <FileText class="w-4 h-4 text-accent-600" />
                            <span class="text-sm font-medium text-surface-700 group-hover:text-accent-700">Grade Assignments</span>
                        </a>
                        <a href="/facilitator/sessions" class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-success-50 transition-colors group">
                            <Video class="w-4 h-4 text-success-600" />
                            <span class="text-sm font-medium text-surface-700 group-hover:text-success-700">Schedule Session</span>
                        </a>
                        <a href="/facilitator/interventions" class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-warning-50 transition-colors group">
                            <Zap class="w-4 h-4 text-warning-600" />
                            <span class="text-sm font-medium text-surface-700 group-hover:text-warning-700">Interventions</span>
                        </a>
                    </div>
                </div>

                <!-- Upcoming Sessions -->
                <div class="bg-white rounded-xl border border-surface-200/60 p-5">
                    <h3 class="text-sm font-semibold text-surface-700 mb-3">Upcoming Sessions</h3>
                    <div v-if="upcomingSessions.length" class="space-y-3">
                        <div v-for="session in upcomingSessions" :key="session.id" class="flex items-start gap-3">
                            <div class="text-center shrink-0">
                                <div class="w-12 h-14 rounded-lg bg-primary-50 flex flex-col items-center justify-center">
                                    <span class="text-[10px] font-medium text-primary-500 uppercase">{{ session.date.split(' ')[0] }}</span>
                                    <span class="text-lg font-bold text-primary-700 leading-none">{{ session.date.split(' ')[1] }}</span>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-surface-800 truncate">{{ session.title }}</p>
                                <p class="text-xs text-surface-500">{{ session.time }} · {{ session.duration }}min</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-surface-100 text-surface-500 capitalize">{{ session.platform || 'TBD' }}</span>
                                    <span class="text-[10px] text-surface-400">{{ session.attendees }} attendees</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-4">
                        <Video class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                        <p class="text-xs text-surface-400">No upcoming sessions</p>
                    </div>
                </div>

                <!-- Recent Interventions -->
                <div v-if="recentInterventions.length > 0" class="bg-white rounded-xl border border-surface-200/60 p-5">
                    <h3 class="text-sm font-semibold text-surface-700 mb-3">Recent Interventions</h3>
                    <div class="space-y-3">
                        <div
                            v-for="intervention in recentInterventions"
                            :key="intervention.id"
                            class="flex items-center gap-3"
                        >
                            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', typeIconBg(intervention.type)]">
                                <component :is="typeIcon(intervention.type)" class="w-4 h-4 text-white" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-surface-900 truncate">
                                    {{ intervention.learner?.first_name }} {{ intervention.learner?.last_name }}
                                </p>
                                <p class="text-xs text-surface-500 truncate">{{ intervention.action_taken }}</p>
                            </div>
                            <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', interventionStatusClass(intervention.status)]">
                                {{ intervention.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    Users, Layers, AlertTriangle, Activity, MessageSquare, Heart,
    ArrowUp, Zap, Coffee, Video, FileText, Calendar,
} from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohorts: { type: Array, default: () => [] },
    totalLearners: { type: Number, default: 0 },
    totalAtRisk: { type: Number, default: 0 },
    activeCohortCount: { type: Number, default: 0 },
    recentInterventions: { type: Array, default: () => [] },
    pendingSubmissions: { type: Array, default: () => [] },
    upcomingSessions: { type: Array, default: () => [] },
    engagementSummary: { type: Object, default: () => ({ activeLearners: 0, totalMinutes: 0, lessonsCompleted: 0, quizzesTaken: 0, avgAccuracy: 0 }) },
});

const avgHealth = computed(() => {
    if (!props.cohorts.length) return 0;
    const sum = props.cohorts.reduce((a, c) => a + c.health_score, 0);
    return Math.round(sum / props.cohorts.length);
});

function statusClasses(status) {
    const map = {
        draft: 'bg-surface-100 text-surface-600',
        active: 'bg-success-50 text-success-700',
        completed: 'bg-primary-50 text-primary-700',
        archived: 'bg-surface-100 text-surface-400',
    };
    return map[status] || map.draft;
}

function healthColor(score) {
    if (score >= 70) return 'text-success-600';
    if (score >= 40) return 'text-warning-600';
    return 'text-danger-600';
}

function healthBarColor(score) {
    if (score >= 70) return 'bg-success-500';
    if (score >= 40) return 'bg-warning-500';
    return 'bg-danger-500';
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

function interventionStatusClass(status) {
    const map = {
        pending: 'bg-warning-50 text-warning-700',
        sent: 'bg-primary-50 text-primary-700',
        acknowledged: 'bg-accent-50 text-accent-700',
        resolved: 'bg-success-50 text-success-700',
    };
    return map[status] || map.pending;
}
</script>

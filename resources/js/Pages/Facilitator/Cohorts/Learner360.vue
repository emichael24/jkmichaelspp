<template>
    <Head :title="`${profile.user.name} – Learner 360`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold">
                    {{ initials(profile.user.name) }}
                </div>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">{{ profile.user.name }}</h1>
                    <p class="text-sm text-surface-500">{{ profile.user.email }} · Last active: {{ profile.user.last_active }}</p>
                </div>
            </div>
        </div>

        <!-- Progress & Readiness -->
        <div class="grid gap-4 md:grid-cols-3">
            <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                <p class="text-xs text-surface-500 mb-2">Course Progress</p>
                <p class="text-3xl font-bold text-surface-900">{{ profile.progress.pct }}%</p>
                <div class="w-full h-2 bg-surface-100 rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full bg-primary-500" :style="{ width: `${profile.progress.pct}%` }" />
                </div>
                <p class="text-xs text-surface-400 mt-1 capitalize">{{ profile.progress.status }}</p>
            </div>

            <div v-if="profile.readiness" class="bg-white rounded-xl border border-surface-200/60 p-6">
                <p class="text-xs text-surface-500 mb-2">Readiness Score</p>
                <p class="text-3xl font-bold" :class="readinessColor(profile.readiness.score)">{{ Math.round(profile.readiness.score) }}%</p>
                <p class="text-xs text-surface-500 mt-1">
                    Confidence: <span class="font-semibold capitalize">{{ profile.readiness.confidence_band }}</span>
                </p>
                <p class="text-xs text-surface-500">
                    Pass likelihood: <span class="font-semibold capitalize">{{ profile.readiness.pass_likelihood?.replace('_', ' ') }}</span>
                </p>
            </div>
            <div v-else class="bg-white rounded-xl border border-surface-200/60 p-6">
                <p class="text-xs text-surface-500 mb-2">Readiness Score</p>
                <p class="text-sm text-surface-400">Not enough data yet.</p>
            </div>

            <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                <p class="text-xs text-surface-500 mb-2">Weak Areas</p>
                <div v-if="profile.readiness?.weak_areas?.length" class="flex flex-wrap gap-1.5">
                    <span v-for="area in profile.readiness.weak_areas" :key="area" class="px-2 py-0.5 bg-danger-50 text-danger-700 text-xs rounded-full font-medium">
                        {{ area }}
                    </span>
                </div>
                <p v-else class="text-sm text-surface-400">None identified.</p>
            </div>
        </div>

        <!-- Mastery Map -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Mastery Map</h3>
            <div v-if="profile.mastery.length" class="space-y-3">
                <div v-for="domain in profile.mastery" :key="domain.domain" class="flex items-center gap-3">
                    <span class="text-xs text-surface-600 w-32 truncate" :title="domain.domain">{{ domain.domain }}</span>
                    <div class="flex-1 h-5 bg-surface-100 rounded-full overflow-hidden">
                        <div
                            :class="[
                                'h-full rounded-full transition-all',
                                domain.accuracy_pct >= 75 ? 'bg-success-500' : domain.accuracy_pct >= 50 ? 'bg-warning-500' : 'bg-danger-500',
                            ]"
                            :style="{ width: `${domain.accuracy_pct}%` }"
                        />
                    </div>
                    <span class="text-xs font-bold w-12 text-right" :class="domain.accuracy_pct >= 75 ? 'text-success-600' : domain.accuracy_pct >= 50 ? 'text-warning-600' : 'text-danger-600'">
                        {{ Math.round(domain.accuracy_pct) }}%
                    </span>
                    <span class="text-[10px] text-surface-400 w-20 capitalize">{{ domain.mastery_level }}</span>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400">No mastery data available.</p>
        </div>

        <!-- Mock History & Error Patterns -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Mock History -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                <h3 class="text-sm font-semibold text-surface-700 mb-4">Mock Exam History</h3>
                <div v-if="profile.mock_history.length" class="space-y-2">
                    <div v-for="(mock, i) in profile.mock_history" :key="i" class="flex items-center justify-between p-2 rounded-lg bg-surface-50">
                        <div>
                            <p class="text-sm font-medium text-surface-900">{{ Math.round(mock.score_pct) }}%</p>
                            <p class="text-[10px] text-surface-400">{{ formatDate(mock.created_at) }}</p>
                        </div>
                        <span class="text-xs text-surface-500">{{ formatTime(mock.time_spent_seconds) }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-400">No mock exam attempts.</p>
            </div>

            <!-- Error Patterns -->
            <div class="bg-white rounded-xl border border-surface-200/60 p-6">
                <h3 class="text-sm font-semibold text-surface-700 mb-4">Error Patterns</h3>
                <div v-if="profile.error_patterns.length" class="space-y-2">
                    <div v-for="pattern in profile.error_patterns" :key="pattern.pattern_type" class="flex items-center justify-between p-2 rounded-lg bg-surface-50">
                        <div>
                            <p class="text-sm font-medium text-surface-900 capitalize">{{ pattern.pattern_type.replace(/_/g, ' ') }}</p>
                            <p class="text-[10px] text-surface-400">Frequency: {{ pattern.frequency }}</p>
                        </div>
                        <span :class="[
                            'px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase',
                            pattern.severity === 'high' ? 'bg-danger-50 text-danger-700' : pattern.severity === 'medium' ? 'bg-warning-50 text-warning-700' : 'bg-surface-100 text-surface-600',
                        ]">{{ pattern.severity }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-400">No error patterns detected.</p>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Activity (Last 4 Weeks)</h3>
            <div v-if="profile.activity.length" class="grid grid-cols-7 gap-1">
                <div v-for="day in profile.activity" :key="day.date" class="text-center">
                    <span
                        :class="[
                            'block w-full aspect-square rounded-sm',
                            day.time_spent_minutes > 30 ? 'bg-success-500' : day.time_spent_minutes > 0 ? 'bg-success-200' : 'bg-surface-100',
                        ]"
                        :title="`${day.date}: ${day.time_spent_minutes} min, ${day.lessons_completed} lessons, ${day.quizzes_completed} quizzes`"
                    ></span>
                    <span class="text-[8px] text-surface-400">{{ new Date(day.date).getDate() }}</span>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400">No activity data available.</p>
        </div>

        <!-- Intervention Log -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-6">
            <h3 class="text-sm font-semibold text-surface-700 mb-4">Intervention History</h3>
            <div v-if="profile.interventions.length" class="space-y-3">
                <div v-for="intervention in profile.interventions" :key="intervention.created_at" class="flex items-start gap-3 p-3 rounded-lg bg-surface-50">
                    <div class="w-2 h-2 rounded-full mt-1.5 shrink-0" :class="interventionDotColor(intervention.type)"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-surface-700 capitalize">{{ intervention.type.replace(/_/g, ' ') }}</span>
                            <span :class="['px-1.5 py-0.5 rounded-full text-[10px] font-semibold', interventionStatusClass(intervention.status)]">
                                {{ intervention.status }}
                            </span>
                        </div>
                        <p class="text-xs text-surface-600 mt-0.5">{{ intervention.reason }}</p>
                        <p class="text-xs text-surface-500 mt-0.5">→ {{ intervention.action_taken }}</p>
                        <p class="text-[10px] text-surface-400 mt-1">{{ formatDate(intervention.created_at) }}</p>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400">No interventions recorded.</p>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    profile: { type: Object, required: true },
});

function initials(name) {
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

function readinessColor(score) {
    if (score >= 70) return 'text-success-600';
    if (score >= 40) return 'text-warning-600';
    return 'text-danger-600';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(seconds) {
    if (!seconds) return '—';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${s}s`;
}

function interventionDotColor(type) {
    const map = {
        nudge: 'bg-primary-500',
        coaching: 'bg-accent-500',
        escalation: 'bg-danger-500',
        plan_adjustment: 'bg-warning-500',
        rest_day: 'bg-success-500',
    };
    return map[type] || 'bg-surface-400';
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

<template>
    <Head title="Missions" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Missions</h1>
                <p class="text-surface-500 text-sm mt-1">Complete daily and weekly challenges to earn XP.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 bg-xp-50 px-4 py-2 rounded-xl">
                    <Zap class="w-4 h-4 text-xp-600" />
                    <span class="text-sm font-semibold text-xp-700">{{ summary.total_xp?.toLocaleString() }} XP</span>
                </div>
                <div class="flex items-center gap-2 bg-warning-50 px-4 py-2 rounded-xl">
                    <Flame class="w-4 h-4 text-warning-600" />
                    <span class="text-sm font-semibold text-warning-700">{{ summary.current_streak }} day streak</span>
                </div>
            </div>
        </div>

        <!-- Daily missions -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-surface-700 flex items-center gap-2">
                    <Sun class="w-4 h-4 text-warning-500" />
                    Daily Missions
                </h3>
                <span class="text-xs text-surface-400">Resets at midnight</span>
            </div>

            <div v-if="missions.daily?.length" class="space-y-4">
                <div
                    v-for="mission in missions.daily"
                    :key="mission.id"
                    :class="[
                        'p-4 rounded-xl border transition-all',
                        mission.status === 'completed'
                            ? 'border-success-200 bg-success-50/50'
                            : 'border-surface-200 bg-surface-50',
                    ]"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <div :class="[
                                'w-8 h-8 rounded-lg flex items-center justify-center',
                                mission.status === 'completed' ? 'bg-success-100' : 'bg-primary-50',
                            ]">
                                <CheckCircle v-if="mission.status === 'completed'" class="w-4 h-4 text-success-600" />
                                <Target v-else class="w-4 h-4 text-primary-600" />
                            </div>
                            <div>
                                <p :class="['text-sm font-semibold', mission.status === 'completed' ? 'text-success-700' : 'text-surface-800']">
                                    {{ mission.title }}
                                </p>
                                <p class="text-xs text-surface-400">{{ mission.description }}</p>
                            </div>
                        </div>
                        <span :class="['text-sm font-bold', mission.status === 'completed' ? 'text-success-600' : 'text-xp-600']">
                            +{{ mission.xp_reward }} XP
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-surface-100 rounded-full h-2">
                            <div
                                :class="['h-full rounded-full transition-all', mission.status === 'completed' ? 'bg-success-400' : 'bg-primary-400']"
                                :style="{ width: `${mission.progress_pct}%` }"
                            />
                        </div>
                        <span class="text-xs text-surface-500 whitespace-nowrap">{{ mission.progress }}/{{ mission.target }}</span>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400 text-center py-4">No daily missions available.</p>
        </div>

        <!-- Weekly missions -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-surface-700 flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-primary-500" />
                    Weekly Missions
                </h3>
                <span class="text-xs text-surface-400">Resets on Monday</span>
            </div>

            <div v-if="missions.weekly?.length" class="space-y-4">
                <div
                    v-for="mission in missions.weekly"
                    :key="mission.id"
                    :class="[
                        'p-4 rounded-xl border transition-all',
                        mission.status === 'completed'
                            ? 'border-success-200 bg-success-50/50'
                            : 'border-surface-200 bg-surface-50',
                    ]"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <div :class="[
                                'w-8 h-8 rounded-lg flex items-center justify-center',
                                mission.status === 'completed' ? 'bg-success-100' : 'bg-accent-50',
                            ]">
                                <CheckCircle v-if="mission.status === 'completed'" class="w-4 h-4 text-success-600" />
                                <Star v-else class="w-4 h-4 text-accent-600" />
                            </div>
                            <div>
                                <p :class="['text-sm font-semibold', mission.status === 'completed' ? 'text-success-700' : 'text-surface-800']">
                                    {{ mission.title }}
                                </p>
                                <p class="text-xs text-surface-400">{{ mission.description }}</p>
                            </div>
                        </div>
                        <span :class="['text-sm font-bold', mission.status === 'completed' ? 'text-success-600' : 'text-xp-600']">
                            +{{ mission.xp_reward }} XP
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-surface-100 rounded-full h-2">
                            <div
                                :class="['h-full rounded-full transition-all', mission.status === 'completed' ? 'bg-success-400' : 'bg-accent-400']"
                                :style="{ width: `${mission.progress_pct}%` }"
                            />
                        </div>
                        <span class="text-xs text-surface-500 whitespace-nowrap">{{ mission.progress }}/{{ mission.target }}</span>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400 text-center py-4">No weekly missions available.</p>
        </div>

        <!-- Recently completed -->
        <div v-if="missions.completed?.length" class="bg-white rounded-2xl border border-surface-200 p-5">
            <h3 class="text-sm font-semibold text-surface-700 mb-4 flex items-center gap-2">
                <Award class="w-4 h-4 text-success-500" />
                Recently Completed
            </h3>
            <div class="space-y-3">
                <div
                    v-for="mission in missions.completed"
                    :key="mission.id"
                    class="flex items-center justify-between p-3 rounded-xl bg-success-50/50"
                >
                    <div class="flex items-center gap-3">
                        <CheckCircle class="w-5 h-5 text-success-500 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-surface-700">{{ mission.title }}</p>
                            <p class="text-xs text-surface-400">{{ mission.completed_at }}</p>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-success-600">+{{ mission.xp_reward }} XP</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Zap, Flame, Sun, Calendar, Target, Star, CheckCircle, Award,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

defineProps({
    missions: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});
</script>

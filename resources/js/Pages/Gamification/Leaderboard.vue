<template>
    <Head title="Leaderboard" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Leaderboard</h1>
                <p class="text-surface-500 text-sm mt-1">See how you rank among other learners.</p>
            </div>
            <div class="flex items-center gap-2 bg-primary-50 px-4 py-2 rounded-xl">
                <Medal class="w-4 h-4 text-primary-600" />
                <span class="text-sm font-semibold text-primary-700">Your Rank: #{{ leaderboard.user_rank }}</span>
            </div>
        </div>

        <!-- Your stats -->
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-2xl p-5 text-white">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p class="text-white/60 text-xs font-medium">Rank</p>
                    <p class="text-3xl font-display font-bold">#{{ leaderboard.user_rank }}</p>
                </div>
                <div>
                    <p class="text-white/60 text-xs font-medium">Total XP</p>
                    <p class="text-3xl font-display font-bold">{{ leaderboard.user_xp?.toLocaleString() }}</p>
                </div>
                <div>
                    <p class="text-white/60 text-xs font-medium">Level</p>
                    <p class="text-3xl font-display font-bold">{{ summary.level }}</p>
                    <p class="text-white/60 text-xs">{{ summary.level_title }}</p>
                </div>
            </div>
        </div>

        <!-- Top 10 -->
        <div class="bg-white rounded-2xl border border-surface-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h3 class="text-sm font-semibold text-surface-700 flex items-center gap-2">
                    <Trophy class="w-4 h-4 text-xp-500" />
                    Top Learners
                </h3>
            </div>

            <div class="divide-y divide-surface-100">
                <div
                    v-for="entry in leaderboard.top"
                    :key="entry.rank"
                    :class="[
                        'flex items-center gap-4 px-5 py-4 transition-colors',
                        entry.rank <= 3 ? 'bg-xp-50/30' : 'hover:bg-surface-50',
                    ]"
                >
                    <!-- Rank -->
                    <div class="w-10 shrink-0">
                        <div v-if="entry.rank === 1" class="w-9 h-9 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center shadow-sm">
                            <Trophy class="w-4 h-4 text-white" />
                        </div>
                        <div v-else-if="entry.rank === 2" class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-300 to-slate-400 flex items-center justify-center shadow-sm">
                            <Medal class="w-4 h-4 text-white" />
                        </div>
                        <div v-else-if="entry.rank === 3" class="w-9 h-9 rounded-full bg-gradient-to-br from-amber-600 to-amber-700 flex items-center justify-center shadow-sm">
                            <Medal class="w-4 h-4 text-white" />
                        </div>
                        <span v-else class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-surface-100 text-sm font-bold text-surface-500">
                            {{ entry.rank }}
                        </span>
                    </div>

                    <!-- Name -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-surface-800 truncate">{{ entry.name }}</p>
                        <p class="text-xs text-surface-400">Level {{ entry.level }}</p>
                    </div>

                    <!-- Streak -->
                    <div class="flex items-center gap-1 text-xs text-surface-400">
                        <Flame class="w-3.5 h-3.5 text-warning-400" />
                        {{ entry.streak }}d
                    </div>

                    <!-- XP -->
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold text-xp-600">{{ entry.xp?.toLocaleString() }}</p>
                        <p class="text-xs text-surface-400">XP</p>
                    </div>
                </div>
            </div>

            <div v-if="!leaderboard.top?.length" class="px-5 py-10 text-center">
                <Trophy class="w-10 h-10 text-surface-200 mx-auto mb-2" />
                <p class="text-sm text-surface-400">No leaderboard data yet. Keep learning!</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Trophy, Medal, Flame, Zap } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

defineProps({
    leaderboard: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});
</script>

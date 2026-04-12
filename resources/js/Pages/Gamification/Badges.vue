<template>
    <Head title="Badges" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Badges</h1>
                <p class="text-surface-500 text-sm mt-1">{{ totalEarned }} of {{ totalBadges }} badges earned</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-xp-50 px-4 py-2 rounded-xl">
                    <Trophy class="w-4 h-4 text-xp-600" />
                    <span class="text-sm font-semibold text-xp-700">Level {{ summary.level }} · {{ summary.level_title }}</span>
                </div>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-surface-600">Badge Collection Progress</span>
                <span class="text-sm font-semibold text-primary-600">{{ Math.round((totalEarned / totalBadges) * 100) }}%</span>
            </div>
            <div class="bg-surface-100 rounded-full h-3">
                <div
                    class="bg-gradient-to-r from-primary-400 to-primary-600 h-full rounded-full transition-all duration-500"
                    :style="{ width: `${(totalEarned / totalBadges) * 100}%` }"
                />
            </div>
        </div>

        <!-- Badge categories -->
        <div v-for="(categoryBadges, category) in badges" :key="category" class="bg-white rounded-2xl border border-surface-200 p-5">
            <h3 class="text-sm font-semibold text-surface-700 mb-4 capitalize flex items-center gap-2">
                <component :is="categoryIcon(category)" class="w-4 h-4 text-primary-500" />
                {{ category }}
                <span class="text-xs text-surface-400 font-normal">
                    ({{ categoryBadges.filter(b => b.earned).length }}/{{ categoryBadges.length }})
                </span>
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                    v-for="badge in categoryBadges"
                    :key="badge.id"
                    :class="[
                        'relative flex flex-col items-center text-center p-4 rounded-xl border-2 transition-all',
                        badge.earned
                            ? 'border-xp-200 bg-xp-50/50'
                            : 'border-surface-100 bg-surface-50 opacity-60 grayscale',
                    ]"
                >
                    <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-3', badge.earned ? 'bg-xp-100' : 'bg-surface-100']">
                        {{ badge.icon || '🏆' }}
                    </div>
                    <p class="text-sm font-semibold text-surface-800">{{ badge.name }}</p>
                    <p class="text-xs text-surface-500 mt-1">{{ badge.description }}</p>
                    <div v-if="badge.earned" class="mt-2 flex items-center gap-1 text-xs text-success-600">
                        <CheckCircle class="w-3 h-3" />
                        Earned
                    </div>
                    <div v-else class="mt-2 text-xs text-surface-400">
                        +{{ badge.xp_reward }} XP
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Trophy, CheckCircle, BookOpen, Target, Flame, Users, Award, Briefcase,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

defineProps({
    badges: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    totalEarned: { type: Number, default: 0 },
    totalBadges: { type: Number, default: 0 },
});

function categoryIcon(category) {
    const map = { learning: BookOpen, mastery: Target, streak: Flame, social: Users, completion: Award, internship: Briefcase };
    return map[category] || Award;
}
</script>

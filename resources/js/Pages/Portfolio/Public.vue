<template>
    <Head :title="`${portfolioUser.first_name}'s Portfolio`" />

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-xl mx-auto mb-3">
                {{ initials }}
            </div>
            <h1 class="text-xl font-display font-bold text-surface-900">{{ portfolioUser.first_name }} {{ portfolioUser.last_name }}</h1>
            <p class="text-sm text-surface-500">Professional Portfolio</p>
        </div>

        <div v-if="items.length" class="space-y-4">
            <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="font-semibold text-surface-900">{{ item.title }}</h3>
                        <span class="text-xs text-surface-400 capitalize">{{ item.type.replace(/_/g, ' ') }}</span>
                    </div>
                    <span v-if="item.sponsor_score != null" class="text-lg font-bold text-accent-600">{{ item.sponsor_score }}/5</span>
                </div>
                <p v-if="item.description" class="text-sm text-surface-600 mb-3">{{ item.description }}</p>
                <div v-if="item.skills_demonstrated?.length" class="flex flex-wrap gap-1">
                    <span v-for="(s, i) in item.skills_demonstrated" :key="i"
                          class="px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full text-[10px] font-medium">{{ s }}</span>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <p class="text-surface-500">This portfolio has no public items.</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    portfolioUser: { type: Object, required: true },
    items: { type: Array, default: () => [] },
});

const initials = computed(() => {
    return ((props.portfolioUser.first_name?.[0] || '') + (props.portfolioUser.last_name?.[0] || '')).toUpperCase();
});
</script>

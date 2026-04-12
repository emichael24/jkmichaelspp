<template>
    <div :class="statClasses">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-surface-500">{{ label }}</p>
                <p :class="valueClasses">{{ displayValue }}</p>
            </div>
            <div v-if="icon" :class="iconWrapperClasses">
                <component :is="icon" class="w-5 h-5" />
            </div>
        </div>
        <div v-if="change !== null" class="flex items-center gap-1.5 mt-2">
            <svg v-if="change >= 0" class="w-4 h-4 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            <svg v-else class="w-4 h-4 text-danger-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
            <span :class="change >= 0 ? 'text-success-600' : 'text-danger-600'" class="text-sm font-semibold">
                {{ Math.abs(change) }}%
            </span>
            <span class="text-xs text-surface-400">vs last week</span>
        </div>
        <slot name="footer" />
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: [Object, Function], default: null },
    change: { type: Number, default: null },
    color: { type: String, default: 'primary' },
    format: { type: Function, default: null },
});

const displayValue = computed(() => props.format ? props.format(props.value) : props.value);

const colorMap = {
    primary: 'bg-primary-50 text-primary-600',
    accent: 'bg-accent-50 text-accent-600',
    success: 'bg-success-50 text-success-600',
    warning: 'bg-warning-50 text-warning-600',
    danger: 'bg-danger-50 text-danger-600',
    xp: 'bg-xp-50 text-xp-600',
};

const statClasses = 'bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5';

const valueClasses = 'text-2xl font-bold text-surface-900 mt-1 tracking-tight';

const iconWrapperClasses = computed(() => [
    'rounded-xl p-2.5',
    colorMap[props.color] || colorMap.primary,
]);
</script>

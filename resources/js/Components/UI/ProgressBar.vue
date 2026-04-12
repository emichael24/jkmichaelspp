<template>
    <div class="w-full">
        <div v-if="label || showValue" class="flex items-center justify-between mb-1.5">
            <span v-if="label" class="text-sm font-medium text-surface-600">{{ label }}</span>
            <span v-if="showValue" class="text-sm font-semibold" :class="valueColor">{{ displayValue }}</span>
        </div>
        <div :class="trackClasses">
            <div
                :class="barClasses"
                :style="{ width: `${clampedPercentage}%` }"
            >
                <div v-if="animated && clampedPercentage > 0" class="absolute inset-0 overflow-hidden rounded-full">
                    <div class="w-full h-full bg-gradient-to-r from-transparent via-white/25 to-transparent animate-[shimmer_2s_infinite]" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    percentage: { type: Number, default: 0 },
    label: { type: String, default: null },
    showValue: { type: Boolean, default: false },
    color: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    animated: { type: Boolean, default: false },
    format: { type: Function, default: null },
});

const clampedPercentage = computed(() => Math.max(0, Math.min(100, props.percentage)));

const displayValue = computed(() =>
    props.format ? props.format(clampedPercentage.value) : `${Math.round(clampedPercentage.value)}%`
);

const heights = { xs: 'h-1', sm: 'h-1.5', md: 'h-2', lg: 'h-3', xl: 'h-4' };

const trackClasses = computed(() => [
    'w-full rounded-full bg-surface-200 overflow-hidden',
    heights[props.size] || heights.md,
]);

const barColors = {
    primary: 'bg-gradient-to-r from-primary-500 to-primary-400',
    accent: 'bg-gradient-to-r from-accent-500 to-accent-400',
    success: 'bg-gradient-to-r from-success-500 to-success-400',
    warning: 'bg-gradient-to-r from-warning-500 to-warning-400',
    danger: 'bg-gradient-to-r from-danger-500 to-danger-400',
    xp: 'bg-gradient-to-r from-xp-500 to-xp-400',
    auto: '',
};

const autoColor = computed(() => {
    if (clampedPercentage.value >= 80) return 'bg-gradient-to-r from-success-500 to-success-400';
    if (clampedPercentage.value >= 50) return 'bg-gradient-to-r from-primary-500 to-primary-400';
    if (clampedPercentage.value >= 25) return 'bg-gradient-to-r from-warning-500 to-warning-400';
    return 'bg-gradient-to-r from-danger-500 to-danger-400';
});

const barClasses = computed(() => [
    'rounded-full transition-all duration-700 ease-out relative',
    heights[props.size] || heights.md,
    props.color === 'auto' ? autoColor.value : (barColors[props.color] || barColors.primary),
]);

const valueColor = computed(() => {
    if (props.color !== 'auto') return 'text-surface-700';
    if (clampedPercentage.value >= 80) return 'text-success-600';
    if (clampedPercentage.value >= 50) return 'text-primary-600';
    if (clampedPercentage.value >= 25) return 'text-warning-600';
    return 'text-danger-600';
});
</script>

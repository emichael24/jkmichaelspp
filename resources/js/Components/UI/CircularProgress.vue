<template>
    <div class="relative" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg :width="size" :height="size" class="transform -rotate-90">
            <!-- Background circle -->
            <circle
                :cx="center"
                :cy="center"
                :r="radius"
                fill="none"
                :stroke="trackColor"
                :stroke-width="strokeWidth"
            />
            <!-- Progress circle -->
            <circle
                :cx="center"
                :cy="center"
                :r="radius"
                fill="none"
                :stroke="progressColor"
                :stroke-width="strokeWidth"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                stroke-linecap="round"
                class="transition-all duration-700 ease-out"
            />
        </svg>
        <!-- Center content -->
        <div class="absolute inset-0 flex items-center justify-center">
            <slot>
                <span :class="labelClasses">{{ Math.round(percentage) }}%</span>
            </slot>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    percentage: { type: Number, default: 0 },
    size: { type: Number, default: 80 },
    strokeWidth: { type: Number, default: 6 },
    color: { type: String, default: 'primary' },
    trackColor: { type: String, default: '#e2e8f0' },
});

const colors = {
    primary: '#3b82f6',
    accent: '#06b6d4',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#f43f5e',
    xp: '#a855f7',
};

const center = computed(() => props.size / 2);
const radius = computed(() => (props.size - props.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const dashOffset = computed(() => circumference.value * (1 - Math.min(props.percentage, 100) / 100));
const progressColor = computed(() => colors[props.color] || props.color);

const labelClasses = computed(() => {
    if (props.size <= 48) return 'text-xs font-bold text-surface-700';
    if (props.size <= 80) return 'text-sm font-bold text-surface-700';
    return 'text-xl font-bold text-surface-700';
});
</script>

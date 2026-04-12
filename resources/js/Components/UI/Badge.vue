<template>
    <span :class="badgeClasses">
        <span v-if="dot" :class="dotClasses" />
        <slot />
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'md' },
    dot: { type: Boolean, default: false },
    rounded: { type: Boolean, default: true },
});

const variants = {
    default: 'bg-surface-100 text-surface-700',
    primary: 'bg-primary-50 text-primary-700 ring-1 ring-primary-200',
    accent: 'bg-accent-50 text-accent-700 ring-1 ring-accent-200',
    success: 'bg-success-50 text-success-700 ring-1 ring-success-200',
    warning: 'bg-warning-50 text-warning-700 ring-1 ring-warning-200',
    danger: 'bg-danger-50 text-danger-700 ring-1 ring-danger-200',
    xp: 'bg-xp-50 text-xp-700 ring-1 ring-xp-200',
    solid: 'bg-primary-600 text-white',
    'solid-accent': 'bg-accent-500 text-white',
    'solid-success': 'bg-success-500 text-white',
};

const sizes = {
    xs: 'text-[10px] px-1.5 py-0.5',
    sm: 'text-xs px-2 py-0.5',
    md: 'text-xs px-2.5 py-1',
    lg: 'text-sm px-3 py-1',
};

const dotVariants = {
    default: 'bg-surface-400',
    primary: 'bg-primary-500',
    accent: 'bg-accent-500',
    success: 'bg-success-500',
    warning: 'bg-warning-500',
    danger: 'bg-danger-500',
    xp: 'bg-xp-500',
};

const badgeClasses = computed(() => [
    'inline-flex items-center gap-1.5 font-semibold',
    variants[props.variant] || variants.default,
    sizes[props.size] || sizes.md,
    props.rounded ? 'rounded-full' : 'rounded-lg',
]);

const dotClasses = computed(() => [
    'w-1.5 h-1.5 rounded-full',
    dotVariants[props.variant] || dotVariants.default,
]);
</script>

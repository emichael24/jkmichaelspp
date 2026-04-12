<template>
    <button
        :type="type"
        :class="classes"
        :disabled="disabled || loading"
        @click="$emit('click', $event)"
    >
        <!-- Loading spinner -->
        <svg
            v-if="loading"
            class="animate-spin -ml-0.5 mr-2 h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>

        <!-- Leading icon -->
        <component v-if="icon && !loading" :is="icon" :class="iconClasses" />

        <span v-if="$slots.default"><slot /></span>

        <!-- Trailing icon -->
        <component v-if="trailingIcon" :is="trailingIcon" :class="iconClasses" />
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    icon: { type: [Object, Function], default: null },
    trailingIcon: { type: [Object, Function], default: null },
    rounded: { type: Boolean, default: false },
});

defineEmits(['click']);

const baseClasses = 'inline-flex items-center justify-center font-semibold transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none';

const variantClasses = {
    primary: 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800 focus-visible:ring-primary-500 shadow-sm shadow-primary-600/20 hover:shadow-md hover:shadow-primary-600/30',
    secondary: 'bg-surface-100 text-surface-700 hover:bg-surface-200 active:bg-surface-300 focus-visible:ring-surface-400 border border-surface-200',
    accent: 'bg-accent-500 text-white hover:bg-accent-600 active:bg-accent-700 focus-visible:ring-accent-500 shadow-sm shadow-accent-500/20',
    danger: 'bg-danger-500 text-white hover:bg-danger-600 active:bg-danger-700 focus-visible:ring-danger-500',
    ghost: 'text-surface-600 hover:bg-surface-100 hover:text-surface-900 focus-visible:ring-surface-400',
    outline: 'border-2 border-primary-500 text-primary-600 hover:bg-primary-50 active:bg-primary-100 focus-visible:ring-primary-500',
    success: 'bg-success-500 text-white hover:bg-success-600 active:bg-success-700 focus-visible:ring-success-500',
    glass: 'bg-white/60 backdrop-blur-lg text-surface-700 hover:bg-white/80 border border-white/30 shadow-sm',
};

const sizeClasses = {
    xs: 'text-xs px-2.5 py-1.5 gap-1',
    sm: 'text-sm px-3 py-2 gap-1.5',
    md: 'text-sm px-4 py-2.5 gap-2',
    lg: 'text-base px-5 py-3 gap-2',
    xl: 'text-lg px-6 py-3.5 gap-2.5',
};

const classes = computed(() => [
    baseClasses,
    variantClasses[props.variant] || variantClasses.primary,
    sizeClasses[props.size] || sizeClasses.md,
    props.rounded ? 'rounded-full' : 'rounded-xl',
]);

const iconClasses = computed(() => {
    const sizes = { xs: 'w-3.5 h-3.5', sm: 'w-4 h-4', md: 'w-4 h-4', lg: 'w-5 h-5', xl: 'w-5 h-5' };
    return sizes[props.size] || sizes.md;
});
</script>

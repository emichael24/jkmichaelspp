<template>
    <div :class="cardClasses" v-bind="$attrs">
        <div v-if="$slots.header || title" :class="headerClasses">
            <div v-if="title || subtitle" class="flex-1 min-w-0">
                <h3 v-if="title" class="text-lg font-semibold text-surface-900 truncate">{{ title }}</h3>
                <p v-if="subtitle" class="text-sm text-surface-500 mt-0.5">{{ subtitle }}</p>
            </div>
            <slot name="header-actions" />
            <slot name="header" />
        </div>

        <div :class="bodyClasses">
            <slot />
        </div>

        <div v-if="$slots.footer" :class="footerClasses">
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    variant: { type: String, default: 'elevated' },
    padding: { type: String, default: 'md' },
    hoverable: { type: Boolean, default: false },
    noPadding: { type: Boolean, default: false },
});

const paddings = { none: '', sm: 'p-4', md: 'p-5 sm:p-6', lg: 'p-6 sm:p-8' };

const cardClasses = computed(() => [
    'rounded-2xl transition-all duration-300',
    {
        elevated: 'bg-white border border-surface-200/60 shadow-sm',
        flat: 'bg-surface-50',
        outlined: 'bg-white border-2 border-surface-200',
        glass: 'bg-white/60 backdrop-blur-xl border border-white/30 shadow-lg',
        gradient: 'bg-gradient-to-br from-primary-600 to-primary-800 text-white border-0',
        'gradient-accent': 'bg-gradient-to-br from-accent-500 to-primary-600 text-white border-0',
    }[props.variant],
    props.hoverable ? 'hover:shadow-lg hover:shadow-primary-500/5 hover:border-primary-200 hover:-translate-y-0.5 cursor-pointer' : '',
]);

const headerClasses = computed(() => [
    'flex items-center gap-3',
    props.noPadding ? 'px-5 sm:px-6 pt-5 sm:pt-6' : paddings[props.padding],
    props.noPadding ? '' : 'pb-0',
]);

const bodyClasses = computed(() => [
    props.noPadding ? '' : paddings[props.padding],
]);

const footerClasses = computed(() => [
    'border-t border-surface-100',
    paddings[props.padding] || paddings.md,
]);
</script>

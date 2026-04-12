<template>
    <img
        v-if="src && !error"
        :src="src"
        :alt="alt"
        :class="avatarClasses"
        @error="error = true"
    />
    <div v-else :class="[avatarClasses, fallbackColor]">
        <span :class="initialsSize">{{ initials }}</span>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    name: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const error = ref(false);
watch(() => props.src, () => { error.value = false; });

const initials = computed(() => {
    if (!props.name) return '?';
    return props.name
        .split(' ')
        .map(w => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const sizes = {
    xs: 'w-6 h-6',
    sm: 'w-8 h-8',
    md: 'w-10 h-10',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16',
    '2xl': 'w-20 h-20',
};

const fontSizes = {
    xs: 'text-[10px] font-semibold',
    sm: 'text-xs font-semibold',
    md: 'text-sm font-semibold',
    lg: 'text-base font-semibold',
    xl: 'text-xl font-bold',
    '2xl': 'text-2xl font-bold',
};

const colors = [
    'bg-primary-100 text-primary-700',
    'bg-accent-100 text-accent-700',
    'bg-success-100 text-success-700',
    'bg-warning-100 text-warning-700',
    'bg-xp-100 text-xp-700',
    'bg-danger-100 text-danger-700',
];

const fallbackColor = computed(() => {
    const hash = props.name.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
    return colors[hash % colors.length];
});

const avatarClasses = computed(() => [
    'rounded-full object-cover flex items-center justify-center shrink-0 ring-2 ring-white',
    sizes[props.size] || sizes.md,
]);

const initialsSize = computed(() => fontSizes[props.size] || fontSizes.md);
</script>

<template>
    <Link
        :href="href"
        :class="[
            'flex flex-col items-center justify-center gap-0.5 rounded-xl transition-all duration-200 relative flex-1 py-1',
            active
                ? 'text-primary-600'
                : 'text-surface-400 active:text-surface-600',
        ]"
    >
        <div class="relative">
            <component :is="icon" :class="['w-5 h-5', active ? 'text-primary-600' : 'text-surface-400']" />
            <span
                v-if="badge"
                class="absolute -top-1.5 -right-2 w-4 h-4 bg-danger-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center"
            >
                {{ badge > 9 ? '9+' : badge }}
            </span>
        </div>
        <span :class="['text-[10px] font-semibold', active ? 'text-primary-600' : 'text-surface-400']">
            {{ label }}
        </span>
        <!-- Active dot -->
        <div v-if="active" class="absolute -top-0.5 left-1/2 -translate-x-1/2 w-5 h-[3px] bg-primary-600 rounded-full" />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    icon: { type: [Object, Function], required: true },
    label: { type: String, required: true },
    href: { type: String, required: true },
    active: { type: Boolean, default: false },
    badge: { type: [Number, String], default: null },
});
</script>

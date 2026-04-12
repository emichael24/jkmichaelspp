<template>
    <Link
        :href="href"
        :class="[
            'flex items-center gap-3 rounded-xl transition-all duration-200 group relative',
            collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2.5',
            active
                ? 'bg-primary-50 text-primary-700 font-semibold'
                : 'text-surface-500 hover:bg-surface-50 hover:text-surface-700',
        ]"
    >
        <component
            :is="icon"
            :class="[
                'w-5 h-5 shrink-0 transition-colors',
                active ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600',
            ]"
        />
        <transition name="fade">
            <span v-if="!collapsed" class="truncate text-sm">{{ label }}</span>
        </transition>

        <!-- Badge -->
        <span
            v-if="badge && !collapsed"
            class="ml-auto bg-primary-100 text-primary-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
        >
            {{ badge }}
        </span>
        <span
            v-if="badge && collapsed"
            class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-primary-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center"
        >
            {{ badge > 9 ? '!' : badge }}
        </span>

        <!-- Active indicator -->
        <div
            v-if="active"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5 bg-primary-600 rounded-r-full"
        />

        <!-- Tooltip for collapsed state -->
        <div
            v-if="collapsed"
            class="absolute left-full ml-2 px-2.5 py-1.5 bg-surface-800 text-white text-xs font-medium rounded-lg
                   opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 shadow-lg"
        >
            {{ label }}
            <div class="absolute right-full top-1/2 -translate-y-1/2 border-[5px] border-transparent border-r-surface-800" />
        </div>
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
    collapsed: { type: Boolean, default: false },
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>

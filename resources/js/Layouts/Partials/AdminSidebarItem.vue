<template>
    <Link
        :href="href"
        :class="[
            'flex items-center gap-3 rounded-xl transition-all duration-200 group relative',
            collapsed ? 'justify-center px-2 py-2.5' : 'px-3 py-2.5',
            active
                ? 'bg-white/10 text-white font-semibold'
                : 'text-surface-400 hover:bg-white/5 hover:text-surface-200',
        ]"
    >
        <component
            :is="icon"
            :class="[
                'w-5 h-5 shrink-0 transition-colors',
                active ? 'text-accent-400' : 'text-surface-500 group-hover:text-surface-300',
            ]"
        />
        <span v-if="!collapsed" class="truncate text-sm">{{ label }}</span>

        <!-- Active indicator -->
        <div
            v-if="active"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5 bg-accent-400 rounded-r-full"
        />

        <!-- Collapsed tooltip -->
        <div
            v-if="collapsed"
            class="absolute left-full ml-2 px-2.5 py-1.5 bg-surface-700 text-white text-xs font-medium rounded-lg
                   opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 shadow-lg"
        >
            {{ label }}
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
    collapsed: { type: Boolean, default: false },
});
</script>

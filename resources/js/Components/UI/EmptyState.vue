<!-- Empty state placeholder with illustration -->
<template>
    <div class="flex flex-col items-center justify-center py-12 px-6 text-center">
        <div class="w-20 h-20 rounded-2xl bg-surface-100 flex items-center justify-center mb-5">
            <component :is="icon" class="w-10 h-10 text-surface-400" />
        </div>
        <h3 class="text-lg font-semibold text-surface-800 mb-1">{{ title }}</h3>
        <p class="text-sm text-surface-500 max-w-sm mb-6">{{ message }}</p>
        <slot name="action" />
    </div>
</template>

<script setup>
import { Inbox } from 'lucide-vue-next';

defineProps({
    title: { type: String, default: 'Nothing here yet' },
    message: { type: String, default: 'Get started by taking your first action.' },
    icon: { type: [Object, Function], default: () => Inbox },
});
</script>

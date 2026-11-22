<template>
    <TransitionRoot appear :show="open || show" as="template">
        <Dialog as="div" class="relative z-50" @close="closeable && $emit('close')">
            <!-- Overlay -->
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-surface-950/40 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95 translate-y-4"
                        enter-to="opacity-100 scale-100 translate-y-0"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100 translate-y-0"
                        leave-to="opacity-0 scale-95 translate-y-4"
                    >
                        <DialogPanel :class="panelClasses">
                            <DialogTitle v-if="!title" class="sr-only">Dialog</DialogTitle>
                            <!-- Header -->
                            <div v-if="title || $slots.header" class="flex items-start justify-between gap-4 p-5 sm:p-6 pb-0">
                                <div>
                                    <DialogTitle v-if="title" class="text-lg font-semibold text-surface-900">
                                        {{ title }}
                                    </DialogTitle>
                                    <p v-if="subtitle" class="text-sm text-surface-500 mt-1">{{ subtitle }}</p>
                                </div>
                                <slot name="header" />
                                <button
                                    v-if="closeable"
                                    aria-label="Close dialog"
                                    type="button"
                                    @click="$emit('close')"
                                    class="rounded-lg p-1.5 text-surface-400 hover:text-surface-600 hover:bg-surface-100 transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="p-5 sm:p-6">
                                <slot />
                            </div>

                            <!-- Footer -->
                            <div v-if="$slots.footer" class="flex items-center justify-end gap-3 border-t border-surface-100 px-5 sm:px-6 py-4">
                                <slot name="footer" />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed } from 'vue';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionRoot,
    TransitionChild,
} from '@headlessui/vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: null },
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    size: { type: String, default: 'md' },
    closeable: { type: Boolean, default: true },
});

defineEmits(['close']);

const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    full: 'max-w-6xl',
};

const panelClasses = computed(() => [
    'w-full transform overflow-hidden rounded-2xl bg-white shadow-2xl shadow-surface-900/10 transition-all',
    sizes[props.maxWidth || props.size] || sizes.md,
]);
</script>

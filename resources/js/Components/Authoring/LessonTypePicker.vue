<script setup>
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { X } from 'lucide-vue-next';
import { lessonTypes } from './lessonTypes';

defineProps({ open: Boolean, moduleTitle: String });
const emit = defineEmits(['close', 'select']);
const groups = [
    { key: 'content', title: 'Learning content' },
    { key: 'assessment', title: 'Assess learners' },
];
</script>

<template>
    <Dialog :open="open" class="relative z-50" @close="emit('close')">
        <div class="fixed inset-0 bg-surface-950/50" aria-hidden="true" />
        <div class="fixed inset-0 overflow-y-auto p-4 sm:p-8">
            <DialogPanel class="mx-auto w-full max-w-3xl rounded-2xl bg-white p-6 shadow-xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <DialogTitle class="text-2xl font-semibold text-surface-900">Select lesson type</DialogTitle>
                        <p class="mt-1 text-sm text-surface-500">Choose what learners will do{{ moduleTitle ? ` in “${moduleTitle}”` : '' }}.</p>
                    </div>
                    <button type="button" aria-label="Close" class="grid h-10 w-10 place-items-center rounded-full bg-surface-100 text-surface-500 hover:bg-surface-200" @click="emit('close')"><X class="h-5 w-5" /></button>
                </div>
                <section v-for="group in groups" :key="group.key" class="mt-7">
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-surface-500">{{ group.title }}</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <button v-for="type in lessonTypes.filter(item => item.group === group.key)" :key="type.value" type="button"
                            class="group flex min-h-32 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-surface-200 px-3 py-5 text-center transition hover:border-primary-500 hover:bg-primary-50/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                            @click="emit('select', type.value)">
                            <component :is="type.icon" class="h-8 w-8 text-primary-600" aria-hidden="true" />
                            <span class="text-sm font-semibold text-surface-800">{{ type.label }}</span>
                            <span class="text-[11px] leading-4 text-surface-500">{{ type.hint }}</span>
                        </button>
                    </div>
                </section>
            </DialogPanel>
        </div>
    </Dialog>
</template>

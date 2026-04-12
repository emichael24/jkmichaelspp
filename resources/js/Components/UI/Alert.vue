<template>
    <div :class="['flex items-center gap-3 rounded-xl p-4', variantClasses]">
        <div :class="['rounded-lg p-2', iconBg]">
            <component :is="icon" class="w-5 h-5" />
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium leading-tight">{{ title }}</p>
            <p v-if="message" class="text-sm opacity-80 mt-0.5">{{ message }}</p>
        </div>
        <button v-if="dismissible" @click="$emit('dismiss')" class="opacity-60 hover:opacity-100 transition-opacity p-1">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CheckCircle, AlertTriangle, Info, XCircle } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'info' },
    title: { type: String, required: true },
    message: { type: String, default: null },
    dismissible: { type: Boolean, default: false },
});

defineEmits(['dismiss']);

const icons = { info: Info, success: CheckCircle, warning: AlertTriangle, error: XCircle };
const icon = computed(() => icons[props.variant] || icons.info);

const variantMap = {
    info: 'bg-primary-50 text-primary-800 border border-primary-200',
    success: 'bg-success-50 text-success-800 border border-success-200',
    warning: 'bg-warning-50 text-warning-800 border border-warning-200',
    error: 'bg-danger-50 text-danger-800 border border-danger-200',
};
const variantClasses = computed(() => variantMap[props.variant] || variantMap.info);

const iconBgMap = {
    info: 'bg-primary-100',
    success: 'bg-success-100',
    warning: 'bg-warning-100',
    error: 'bg-danger-100',
};
const iconBg = computed(() => iconBgMap[props.variant] || iconBgMap.info);
</script>

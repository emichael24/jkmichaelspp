<template>
    <div :class="[wrapperClasses, $attrs.class]">
        <label v-if="label" :for="inputId" class="block text-sm font-medium text-surface-700 mb-1.5">
            {{ label }}
            <span v-if="required" class="text-danger-500 ml-0.5">*</span>
        </label>

        <div class="relative">
            <!-- Leading icon -->
            <div v-if="icon" class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                <component :is="icon" class="w-4.5 h-4.5 text-surface-400" />
            </div>

            <input
                v-bind="{ ...$attrs, class: undefined }"
                :id="inputId"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error || hint ? `${inputId}-description` : $attrs['aria-describedby']"
                ref="inputRef"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                :autocomplete="autocomplete"
                :class="inputClasses"
                @input="$emit('update:modelValue', $event.target.value)"
                @focus="focused = true"
                @blur="focused = false; $emit('blur', $event)"
            />

            <!-- Trailing element -->
            <div v-if="$slots.trailing || clearable" class="absolute inset-y-0 right-0 flex items-center pr-3">
                <slot name="trailing" />
                <button
                    v-if="clearable && modelValue"
                    type="button"
                    :aria-label="`Clear ${label || 'field'}`"
                    class="text-surface-400 hover:text-surface-600 transition-colors"
                    @click="$emit('update:modelValue', '')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Hint / Error -->
        <p v-if="error" :id="`${inputId}-description`" class="mt-1.5 text-sm text-danger-600 flex items-center gap-1">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${inputId}-description`" class="mt-1.5 text-sm text-surface-500">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    label: { type: String, default: null },
    placeholder: { type: String, default: '' },
    hint: { type: String, default: null },
    error: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
    size: { type: String, default: 'md' },
});

defineEmits(['update:modelValue', 'blur']);

const inputRef = ref(null);
const generatedId = useId();
const inputId = computed(() => props.id || generatedId);
const focused = ref(false);

const sizeMap = {
    sm: 'text-sm py-1.5 px-3',
    md: 'text-sm py-2.5 px-3.5',
    lg: 'text-base py-3 px-4',
};

const inputClasses = computed(() => [
    'block w-full rounded-xl border bg-white transition-all duration-200',
    'placeholder:text-surface-400',
    'focus:outline-none focus:ring-2 focus:ring-offset-0',
    'disabled:bg-surface-100 disabled:text-surface-500 disabled:cursor-not-allowed',
    sizeMap[props.size],
    props.icon ? 'pl-10' : '',
    props.error
        ? 'border-danger-300 focus:border-danger-500 focus:ring-danger-500/20'
        : 'border-surface-200 focus:border-primary-500 focus:ring-primary-500/20 hover:border-surface-300',
]);

const wrapperClasses = computed(() => [
    'w-full',
    props.disabled ? 'opacity-60' : '',
]);

defineExpose({ focus: () => inputRef.value?.focus() });
</script>

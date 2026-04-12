<template>
    <div
        :class="[
            'rounded-2xl border-2 p-5 transition-all duration-200 cursor-pointer',
            isSelected
                ? 'border-primary-500 bg-primary-50/50 ring-2 ring-primary-200'
                : 'border-surface-200 bg-white hover:border-surface-300 hover:shadow-sm',
            isReview && isCorrectOption && 'border-success-400 bg-success-50/50',
            isReview && isWrongSelection && 'border-danger-400 bg-danger-50/50',
            disabled && 'opacity-60 pointer-events-none',
        ]"
        @click="!disabled && $emit('select', option.id)"
    >
        <div class="flex items-start gap-3">
            <!-- Indicator -->
            <div
                :class="[
                    'w-8 h-8 rounded-full border-2 flex items-center justify-center shrink-0 text-sm font-semibold transition-colors',
                    isSelected && !isReview
                        ? 'border-primary-500 bg-primary-500 text-white'
                        : isReview && isCorrectOption
                            ? 'border-success-500 bg-success-500 text-white'
                            : isReview && isWrongSelection
                                ? 'border-danger-500 bg-danger-500 text-white'
                                : 'border-surface-300 text-surface-500',
                ]"
            >
                <template v-if="isReview && isCorrectOption">
                    <Check class="w-4 h-4" />
                </template>
                <template v-else-if="isReview && isWrongSelection">
                    <X class="w-4 h-4" />
                </template>
                <template v-else>
                    {{ letterLabel }}
                </template>
            </div>

            <!-- Option text -->
            <div class="flex-1 min-w-0">
                <p class="text-sm text-surface-800 leading-relaxed">{{ option.text }}</p>
                <img
                    v-if="option.media_url"
                    :src="option.media_url"
                    :alt="option.text"
                    class="mt-2 rounded-lg max-h-40 object-contain"
                />
            </div>

            <!-- Selection check -->
            <div v-if="isSelected && !isReview" class="shrink-0">
                <CheckCircle class="w-5 h-5 text-primary-500" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Check, X, CheckCircle } from 'lucide-vue-next';

const props = defineProps({
    option: { type: Object, required: true },
    index: { type: Number, required: true },
    isSelected: { type: Boolean, default: false },
    isReview: { type: Boolean, default: false },
    correctAnswerIds: { type: Array, default: () => [] },
    selectedAnswerIds: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});

defineEmits(['select']);

const letterLabel = computed(() => String.fromCharCode(65 + props.index));

const isCorrectOption = computed(() =>
    props.isReview && props.correctAnswerIds.includes(props.option.id)
);

const isWrongSelection = computed(() =>
    props.isReview
    && props.selectedAnswerIds.includes(props.option.id)
    && !props.correctAnswerIds.includes(props.option.id)
);
</script>

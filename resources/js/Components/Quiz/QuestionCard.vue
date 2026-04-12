<template>
    <div class="bg-white rounded-2xl border border-surface-200 overflow-hidden">
        <!-- Question header -->
        <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-surface-500">
                    Question {{ currentIndex + 1 }}
                    <span class="text-surface-400">/ {{ totalCount }}</span>
                </span>
                <span v-if="question.domain" class="text-xs font-medium text-primary-600 bg-primary-50 px-2.5 py-0.5 rounded-full">
                    {{ question.domain }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="question.type !== 'single_choice'" class="text-xs text-surface-500 bg-surface-100 px-2 py-0.5 rounded-full">
                    {{ typeLabel }}
                </span>
                <button
                    v-if="!isReview"
                    @click="$emit('flag')"
                    :class="[
                        'p-1.5 rounded-lg transition-colors',
                        isFlagged
                            ? 'bg-warning-100 text-warning-600'
                            : 'hover:bg-surface-100 text-surface-400',
                    ]"
                    title="Flag for review"
                >
                    <Flag class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Stem -->
        <div class="px-6 py-5">
            <p class="text-base text-surface-900 leading-relaxed font-medium mb-1">{{ question.stem }}</p>

            <!-- Stem media -->
            <img
                v-if="question.stem_media?.url"
                :src="question.stem_media.url"
                alt="Question media"
                class="mt-3 rounded-xl max-h-64 object-contain"
            />

            <!-- Type hint -->
            <p v-if="question.type === 'multiple_choice'" class="mt-3 text-xs text-surface-500 italic">
                Select all that apply
            </p>
        </div>

        <!-- Options -->
        <div class="px-6 pb-6 space-y-3">
            <OptionCard
                v-for="(option, idx) in question.options"
                :key="option.id"
                :option="option"
                :index="idx"
                :is-selected="selectedIds.includes(option.id)"
                :is-review="isReview"
                :correct-answer-ids="correctAnswerIds"
                :selected-answer-ids="selectedIds"
                :disabled="isReview"
                @select="handleSelect"
            />
        </div>

        <!-- Review explanations -->
        <div v-if="isReview && (explanation || examTip)" class="px-6 pb-6">
            <div v-if="explanation" class="rounded-xl border border-surface-200 bg-surface-50 p-4 mb-3">
                <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider mb-1.5">Explanation</p>
                <p class="text-sm text-surface-700 leading-relaxed">{{ explanation }}</p>
            </div>
            <div v-if="examTip" class="rounded-xl border border-accent-200 bg-accent-50 p-4">
                <div class="flex items-center gap-2 mb-1.5">
                    <Lightbulb class="w-4 h-4 text-accent-600" />
                    <p class="text-xs font-semibold text-accent-700 uppercase tracking-wider">Exam Tip</p>
                </div>
                <p class="text-sm text-accent-800 leading-relaxed">{{ examTip }}</p>
            </div>
        </div>

        <!-- Confidence rater -->
        <div v-if="!isReview && selectedIds.length > 0" class="px-6 pb-6 border-t border-surface-100 pt-4">
            <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider mb-2">How confident are you?</p>
            <div class="flex gap-2">
                <button
                    v-for="level in confidenceLevels"
                    :key="level.value"
                    @click="$emit('confidence', level.value)"
                    :class="[
                        'px-4 py-2 rounded-xl text-sm font-medium transition-all',
                        confidence === level.value
                            ? level.activeClass
                            : 'bg-surface-100 text-surface-500 hover:bg-surface-200',
                    ]"
                >
                    {{ level.label }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Flag, Lightbulb } from 'lucide-vue-next';
import OptionCard from './OptionCard.vue';

const props = defineProps({
    question: { type: Object, required: true },
    currentIndex: { type: Number, required: true },
    totalCount: { type: Number, required: true },
    selectedIds: { type: Array, default: () => [] },
    confidence: { type: String, default: null },
    isFlagged: { type: Boolean, default: false },
    isReview: { type: Boolean, default: false },
    correctAnswerIds: { type: Array, default: () => [] },
    explanation: { type: String, default: null },
    examTip: { type: String, default: null },
});

const emit = defineEmits(['select', 'flag', 'confidence']);

const typeLabel = computed(() => {
    const map = {
        single_choice: 'Single Choice',
        multiple_choice: 'Multiple Choice',
        true_false: 'True/False',
        fill_blank: 'Fill in the Blank',
        ordering: 'Ordering',
        matching: 'Matching',
        hotspot: 'Hotspot',
        drag_drop: 'Drag & Drop',
        case_study: 'Case Study',
    };
    return map[props.question.type] || props.question.type;
});

const confidenceLevels = [
    { value: 'low', label: 'Guessing', activeClass: 'bg-danger-100 text-danger-700 ring-2 ring-danger-200' },
    { value: 'medium', label: 'Somewhat sure', activeClass: 'bg-warning-100 text-warning-700 ring-2 ring-warning-200' },
    { value: 'high', label: 'Confident', activeClass: 'bg-success-100 text-success-700 ring-2 ring-success-200' },
];

function handleSelect(optionId) {
    const isMultiple = props.question.type === 'multiple_choice';

    if (isMultiple) {
        const newIds = props.selectedIds.includes(optionId)
            ? props.selectedIds.filter(id => id !== optionId)
            : [...props.selectedIds, optionId];
        emit('select', newIds);
    } else {
        emit('select', [optionId]);
    }
}
</script>

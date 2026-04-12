<template>
    <div
        @click="!flipped && $emit('flip')"
        class="relative w-full min-h-[280px] perspective-1000 cursor-pointer select-none"
    >
        <div
            class="relative w-full h-full transition-transform duration-500 transform-style-3d"
            :class="{ 'rotate-y-180': flipped }"
        >
            <!-- Front -->
            <div class="absolute inset-0 backface-hidden">
                <div class="h-full bg-white rounded-2xl border-2 border-surface-200 p-8 flex flex-col items-center justify-center text-center shadow-sm hover:shadow-md transition-shadow">
                    <span v-if="domain" class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-primary-100 text-primary-700 mb-4">{{ domain }}</span>
                    <p class="text-lg font-medium text-surface-900 leading-relaxed whitespace-pre-wrap">{{ front }}</p>
                    <p class="text-xs text-surface-400 mt-6">Tap to reveal</p>
                </div>
            </div>

            <!-- Back -->
            <div class="absolute inset-0 backface-hidden rotate-y-180">
                <div class="h-full bg-gradient-to-br from-primary-50 to-white rounded-2xl border-2 border-primary-200 p-8 flex flex-col items-center justify-center text-center shadow-sm">
                    <span v-if="source" class="text-xs font-medium px-2.5 py-0.5 rounded-full mb-4"
                          :class="sourceClass">{{ sourceLabel }}</span>
                    <p class="text-lg text-surface-800 leading-relaxed whitespace-pre-wrap">{{ back }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    front: String,
    back: String,
    domain: String,
    source: String,
    flipped: Boolean,
})

defineEmits(['flip'])

const sourceLabel = computed(() => {
    const map = { wrong_answer: 'From Quiz Mistake', lesson_term: 'Key Term', user_created: 'Your Card' }
    return map[props.source] || props.source
})

const sourceClass = computed(() => {
    const map = {
        wrong_answer: 'bg-danger-100 text-danger-700',
        lesson_term: 'bg-accent-100 text-accent-700',
        user_created: 'bg-xp-100 text-xp-700',
    }
    return map[props.source] || 'bg-surface-100 text-surface-600'
})
</script>

<style scoped>
.perspective-1000 {
    perspective: 1000px;
}
.transform-style-3d {
    transform-style: preserve-3d;
}
.backface-hidden {
    backface-visibility: hidden;
}
.rotate-y-180 {
    transform: rotateY(180deg);
}
</style>

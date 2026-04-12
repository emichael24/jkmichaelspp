<template>
    <div class="bg-white rounded-2xl border border-surface-200 p-4">
        <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider mb-3">Question Navigator</p>

        <div class="grid grid-cols-5 sm:grid-cols-8 gap-1.5">
            <button
                v-for="(q, idx) in questions"
                :key="q.id"
                @click="$emit('goto', idx)"
                :class="[
                    'w-9 h-9 rounded-lg text-xs font-semibold transition-all relative',
                    currentIndex === idx
                        ? 'bg-primary-600 text-white ring-2 ring-primary-300 shadow-sm'
                        : isAnswered(idx)
                            ? 'bg-primary-100 text-primary-700 hover:bg-primary-200'
                            : 'bg-surface-100 text-surface-500 hover:bg-surface-200',
                ]"
            >
                {{ idx + 1 }}
                <!-- Flag dot -->
                <span
                    v-if="isFlagged(idx)"
                    class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-warning-500 border-2 border-white"
                />
            </button>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap gap-4 text-[11px] text-surface-500">
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded bg-primary-600" />
                Current
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded bg-primary-100" />
                Answered
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded bg-surface-100" />
                Unanswered
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-warning-500" />
                Flagged
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    questions: { type: Array, required: true },
    currentIndex: { type: Number, required: true },
    answers: { type: Object, default: () => ({}) },
    flags: { type: Object, default: () => ({}) },
});

defineEmits(['goto']);

function isAnswered(idx) {
    const qId = props.questions[idx]?.id;
    return qId && props.answers[qId]?.length > 0;
}

function isFlagged(idx) {
    const qId = props.questions[idx]?.id;
    return qId && props.flags[qId];
}
</script>

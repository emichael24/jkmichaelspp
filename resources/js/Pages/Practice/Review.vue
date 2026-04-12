<template>
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <nav class="flex items-center gap-2 text-sm text-surface-500 mb-6">
            <Link :href="route('practice.dashboard')" class="hover:text-primary-600">Practice</Link>
            <span>/</span>
            <span class="text-surface-700">Review Session</span>
        </nav>

        <!-- Empty state -->
        <div v-if="!cards.length" class="text-center py-16">
            <CheckCircle class="w-16 h-16 text-success-400 mx-auto mb-4" />
            <h2 class="text-xl font-bold text-surface-900 mb-2">All caught up!</h2>
            <p class="text-sm text-surface-500 mb-6">No cards are due for review right now. Come back later.</p>
            <Link :href="route('practice.dashboard')" class="px-5 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700">
                Back to Practice
            </Link>
        </div>

        <!-- Review session -->
        <div v-else>
            <!-- Progress -->
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-surface-500">Card {{ currentIndex + 1 }} of {{ cards.length }}</span>
                <span class="text-sm text-surface-500">{{ reviewed }} reviewed</span>
            </div>
            <div class="w-full h-1.5 bg-surface-100 rounded-full mb-8">
                <div class="h-full bg-primary-500 rounded-full transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>

            <!-- Flashcard -->
            <div v-if="currentCard" class="mb-8">
                <FlashcardFlip
                    :front="currentCard.front"
                    :back="currentCard.back"
                    :domain="currentCard.domain"
                    :source="currentCard.source"
                    :flipped="isFlipped"
                    @flip="isFlipped = true"
                />
            </div>

            <!-- Quality rater (only after flip) -->
            <div v-if="isFlipped" class="space-y-4">
                <p class="text-center text-sm text-surface-600 font-medium">How well did you know this?</p>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                    <button
                        v-for="q in qualityOptions"
                        :key="q.value"
                        @click="submitQuality(q.value)"
                        :disabled="submitting"
                        class="p-3 rounded-xl border text-center transition hover:shadow-md"
                        :class="q.color"
                    >
                        <span class="text-lg font-bold block">{{ q.value }}</span>
                        <span class="text-[10px] leading-tight block mt-0.5">{{ q.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Tap to reveal -->
            <div v-else class="text-center">
                <button
                    @click="isFlipped = true"
                    class="px-8 py-3 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition"
                >
                    Show Answer
                </button>
            </div>
        </div>

        <!-- Session complete modal -->
        <div v-if="sessionComplete" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-8 max-w-md w-full text-center">
                <Trophy class="w-12 h-12 text-xp-500 mx-auto mb-4" />
                <h3 class="text-xl font-bold text-surface-900 mb-2">Session Complete!</h3>
                <p class="text-sm text-surface-600 mb-6">You reviewed {{ reviewed }} cards.</p>
                <div class="flex gap-3 justify-center">
                    <Link :href="route('practice.dashboard')" class="px-6 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">
                        Back to Practice
                    </Link>
                    <Link :href="route('practice.review')" class="px-6 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700">
                        Review More
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { CheckCircle, Trophy } from 'lucide-vue-next'
import FlashcardFlip from '@/Components/Practice/FlashcardFlip.vue'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cards: Array,
    stats: Object,
})

const currentIndex = ref(0)
const isFlipped = ref(false)
const reviewed = ref(0)
const submitting = ref(false)
const sessionComplete = ref(false)

const currentCard = computed(() => props.cards[currentIndex.value])
const progressPct = computed(() => Math.round(reviewed.value / props.cards.length * 100))

const qualityOptions = [
    { value: 0, label: 'Forgot', color: 'border-danger-300 bg-danger-50 text-danger-700 hover:bg-danger-100' },
    { value: 1, label: 'Wrong', color: 'border-danger-200 bg-danger-50/50 text-danger-600 hover:bg-danger-100' },
    { value: 2, label: 'Hard', color: 'border-warning-300 bg-warning-50 text-warning-700 hover:bg-warning-100' },
    { value: 3, label: 'OK', color: 'border-accent-300 bg-accent-50 text-accent-700 hover:bg-accent-100' },
    { value: 4, label: 'Good', color: 'border-success-300 bg-success-50 text-success-700 hover:bg-success-100' },
    { value: 5, label: 'Easy', color: 'border-success-300 bg-success-100 text-success-800 hover:bg-success-200' },
]

function submitQuality(quality) {
    submitting.value = true

    router.post(route('practice.submitReview', currentCard.value.item_id), {
        quality,
    }, {
        preserveState: true,
        onSuccess: () => {
            reviewed.value++
            submitting.value = false

            if (currentIndex.value < props.cards.length - 1) {
                currentIndex.value++
                isFlipped.value = false
            } else {
                sessionComplete.value = true
            }
        },
        onError: () => {
            submitting.value = false
        },
    })
}
</script>

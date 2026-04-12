<template>
    <div class="fixed inset-0 bg-surface-900 flex flex-col z-50">
        <!-- Top bar -->
        <div class="bg-surface-800 border-b border-surface-700 px-6 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
                <h1 class="text-white font-semibold text-sm truncate max-w-[200px]">{{ mockExam.title }}</h1>
                <span class="text-surface-400 text-xs">Q {{ currentIndex + 1 }} / {{ questions.length }}</span>
            </div>

            <div class="flex items-center gap-4">
                <!-- Progress -->
                <div class="hidden md:flex items-center gap-2">
                    <div class="w-32 h-1.5 bg-surface-700 rounded-full">
                        <div class="h-full bg-primary-500 rounded-full transition-all" :style="{ width: progressPct + '%' }"></div>
                    </div>
                    <span class="text-surface-400 text-xs">{{ answeredCount }}/{{ questions.length }}</span>
                </div>

                <!-- Timer -->
                <QuizTimer
                    v-if="mockExam.time_limit_minutes"
                    :total-seconds="remainingSeconds"
                    @expired="handleExpired"
                    class="text-white"
                />

                <!-- Pause -->
                <button
                    v-if="mockExam.allow_pause"
                    @click="showPauseConfirm = true"
                    class="p-2 text-surface-400 hover:text-white transition"
                    title="Pause Exam"
                >
                    <PauseCircle class="w-5 h-5" />
                </button>

                <!-- Submit -->
                <button
                    @click="showSubmitConfirm = true"
                    class="px-4 py-1.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition"
                >
                    Submit Exam
                </button>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Question area -->
            <div class="flex-1 overflow-y-auto p-6 lg:p-10">
                <div class="max-w-3xl mx-auto">
                    <QuestionCard
                        :question="currentQuestion"
                        :current-index="currentIndex"
                        :total-count="questions.length"
                        :selected-ids="currentSelected"
                        :confidence="currentConfidence"
                        :is-flagged="isFlagged(currentQuestion.id)"
                        :is-review="false"
                        @select="handleSelect"
                        @confidence="handleConfidence"
                        @flag="handleFlag"
                    />
                </div>
            </div>

            <!-- Side panel (nav grid) -->
            <div class="hidden lg:block w-64 bg-surface-800 border-l border-surface-700 p-4 overflow-y-auto">
                <QuizNavGrid
                    :questions="questions"
                    :current-index="currentIndex"
                    :answers="answersObject"
                    :flags="flagsObject"
                    @goto="goToQuestion"
                />
            </div>
        </div>

        <!-- Bottom nav -->
        <div class="bg-surface-800 border-t border-surface-700 px-6 py-3 flex items-center justify-between shrink-0">
            <button
                @click="prevQuestion"
                :disabled="currentIndex === 0"
                class="px-4 py-2 text-sm font-medium rounded-lg transition"
                :class="currentIndex === 0 ? 'text-surface-600 cursor-not-allowed' : 'text-white bg-surface-700 hover:bg-surface-600'"
            >
                Previous
            </button>

            <!-- Mobile nav toggles -->
            <button @click="showNavModal = true" class="lg:hidden p-2 text-surface-400 hover:text-white">
                <LayoutGrid class="w-5 h-5" />
            </button>

            <button
                @click="nextQuestion"
                :disabled="currentIndex === questions.length - 1"
                class="px-4 py-2 text-sm font-medium rounded-lg transition"
                :class="currentIndex === questions.length - 1 ? 'text-surface-600 cursor-not-allowed' : 'text-white bg-surface-700 hover:bg-surface-600'"
            >
                Next
            </button>
        </div>

        <!-- Submit confirm modal -->
        <div v-if="showSubmitConfirm" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4">
            <div class="bg-white rounded-2xl p-8 max-w-md w-full text-center">
                <AlertTriangle class="w-12 h-12 text-warning-500 mx-auto mb-4" />
                <h3 class="text-xl font-bold text-surface-900 mb-2">Submit Mock Exam?</h3>
                <p class="text-sm text-surface-600 mb-4">
                    You have answered <strong>{{ answeredCount }}</strong> of <strong>{{ questions.length }}</strong> questions.
                    <span v-if="Object.keys(flagsObject).length"> {{ Object.keys(flagsObject).length }} flagged.</span>
                </p>
                <p v-if="unansweredCount > 0" class="text-sm text-warning-600 mb-6">
                    {{ unansweredCount }} question{{ unansweredCount > 1 ? 's' : '' }} unanswered — they will be marked incorrect.
                </p>
                <div class="flex gap-3 justify-center">
                    <button @click="showSubmitConfirm = false" class="px-6 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">
                        Continue Exam
                    </button>
                    <button @click="submitExam" :disabled="submitting" class="px-6 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700">
                        {{ submitting ? 'Submitting...' : 'Submit Exam' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Pause confirm modal -->
        <div v-if="showPauseConfirm" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4">
            <div class="bg-white rounded-2xl p-8 max-w-md w-full text-center">
                <PauseCircle class="w-12 h-12 text-accent-500 mx-auto mb-4" />
                <h3 class="text-xl font-bold text-surface-900 mb-2">Pause Exam?</h3>
                <p class="text-sm text-surface-600 mb-6">Your progress will be saved. You can resume later.</p>
                <div class="flex gap-3 justify-center">
                    <button @click="showPauseConfirm = false" class="px-6 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">
                        Cancel
                    </button>
                    <Link :href="route('mock-exams.show', mockExam.id)" class="px-6 py-2.5 text-sm font-semibold bg-accent-600 text-white rounded-xl hover:bg-accent-700">
                        Pause & Exit
                    </Link>
                </div>
            </div>
        </div>

        <!-- Mobile nav modal -->
        <div v-if="showNavModal" class="fixed inset-0 bg-black/60 flex items-end z-[60]" @click.self="showNavModal = false">
            <div class="bg-white rounded-t-2xl w-full p-6 max-h-[60vh] overflow-y-auto">
                <QuizNavGrid
                    :questions="questions"
                    :current-index="currentIndex"
                    :answers="answersObject"
                    :flags="flagsObject"
                    @goto="(i) => { goToQuestion(i); showNavModal = false }"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { PauseCircle, LayoutGrid, AlertTriangle } from 'lucide-vue-next'
import QuestionCard from '@/Components/Quiz/QuestionCard.vue'
import QuizTimer from '@/Components/Quiz/QuizTimer.vue'
import QuizNavGrid from '@/Components/Quiz/QuizNavGrid.vue'

const props = defineProps({
    mockExam: Object,
    attempt: Object,
    questions: Array,
    existingResponses: Object,
})

const currentIndex = ref(0)
const responses = ref({}) // { questionId: { selected_answer_ids, confidence_level, time_spent_seconds, is_flagged } }
const showSubmitConfirm = ref(false)
const showPauseConfirm = ref(false)
const showNavModal = ref(false)
const submitting = ref(false)
const questionStartTime = ref(Date.now())

// Hydrate existing responses
onMounted(() => {
    if (props.existingResponses) {
        Object.entries(props.existingResponses).forEach(([qId, data]) => {
            responses.value[qId] = { ...data }
        })
    }
})

const currentQuestion = computed(() => props.questions[currentIndex.value])
const currentSelected = computed(() => responses.value[currentQuestion.value?.id]?.selected_answer_ids || [])
const currentConfidence = computed(() => responses.value[currentQuestion.value?.id]?.confidence_level || null)

// Timer: compute remaining seconds from exam start
const remainingSeconds = computed(() => {
    if (!props.mockExam.time_limit_minutes) return 0
    const elapsed = Math.floor((Date.now() - new Date(props.attempt.started_at).getTime()) / 1000)
    return Math.max(0, props.mockExam.time_limit_minutes * 60 - elapsed)
})

// QuizNavGrid-compatible objects (Object maps, not Sets)
const answersObject = computed(() => {
    const obj = {}
    Object.entries(responses.value).forEach(([qId, d]) => {
        if (d.selected_answer_ids?.length > 0) obj[qId] = d.selected_answer_ids
    })
    return obj
})
const flagsObject = computed(() => {
    const obj = {}
    Object.entries(responses.value).forEach(([qId, d]) => {
        if (d.is_flagged) obj[qId] = true
    })
    return obj
})

const answeredCount = computed(() => Object.keys(answersObject.value).length)
const unansweredCount = computed(() => props.questions.length - answeredCount.value)
const progressPct = computed(() => Math.round(answeredCount.value / props.questions.length * 100))

function isFlagged(qId) {
    return responses.value[qId]?.is_flagged || false
}

function handleSelect(optionIds) {
    const qId = currentQuestion.value.id
    if (!responses.value[qId]) responses.value[qId] = {}
    responses.value[qId].selected_answer_ids = optionIds
    autoSave(qId)
}

function handleConfidence(level) {
    const qId = currentQuestion.value.id
    if (!responses.value[qId]) responses.value[qId] = {}
    responses.value[qId].confidence_level = level
}

function handleFlag() {
    const qId = currentQuestion.value.id
    if (!responses.value[qId]) responses.value[qId] = {}
    responses.value[qId].is_flagged = !responses.value[qId].is_flagged
}

function autoSave(qId) {
    const data = responses.value[qId]
    if (!data?.selected_answer_ids?.length) return

    const elapsed = Math.round((Date.now() - questionStartTime.value) / 1000)

    router.post(route('mock-exams.answer', props.attempt.id), {
        question_id: qId,
        selected_answer_ids: data.selected_answer_ids,
        confidence_level: data.confidence_level || null,
        time_spent_seconds: elapsed,
        is_flagged: data.is_flagged || false,
    }, { preserveState: true, preserveScroll: true })
}

function goToQuestion(index) {
    currentIndex.value = index
    questionStartTime.value = Date.now()
}

function prevQuestion() {
    if (currentIndex.value > 0) goToQuestion(currentIndex.value - 1)
}

function nextQuestion() {
    if (currentIndex.value < props.questions.length - 1) goToQuestion(currentIndex.value + 1)
}

function handleExpired() {
    submitExam()
}

function submitExam() {
    submitting.value = true
    router.post(route('mock-exams.complete', props.attempt.id))
}
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Mock Exams</h1>
                <p class="text-surface-500 mt-1">Simulate real exam conditions and track your readiness</p>
            </div>
        </div>

        <!-- Course filter -->
        <div class="mb-6">
            <select
                v-model="selectedCourse"
                @change="applyFilter"
                class="px-4 py-2 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
                <option value="">All Courses</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
        </div>

        <!-- Grid -->
        <div v-if="mocks.data.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="mock in mocks.data"
                :key="mock.id"
                class="bg-white rounded-2xl border border-surface-200 overflow-hidden hover:shadow-lg transition group"
            >
                <!-- Type badge -->
                <div class="px-6 pt-5 pb-3 flex items-center gap-2">
                    <span :class="typeBadgeClass(mock.type)" class="text-xs font-medium px-2.5 py-0.5 rounded-full">
                        {{ typeLabel(mock.type) }}
                    </span>
                    <span v-if="mock.is_exam_simulation" class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-warning-100 text-warning-700">
                        Exam Simulation
                    </span>
                </div>

                <!-- Content -->
                <div class="px-6 pb-4">
                    <h3 class="text-lg font-semibold text-surface-900 group-hover:text-primary-600 transition">{{ mock.title }}</h3>
                    <p class="text-sm text-surface-500 mt-1">{{ mock.course?.title }}</p>
                </div>

                <!-- Stats -->
                <div class="px-6 pb-4 grid grid-cols-3 gap-3">
                    <div class="text-center">
                        <p class="text-lg font-bold text-surface-900">{{ mock.question_count }}</p>
                        <p class="text-xs text-surface-500">Questions</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-surface-900">{{ mock.time_limit_minutes }}</p>
                        <p class="text-xs text-surface-500">Minutes</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-surface-900">{{ mock.passing_score_pct }}%</p>
                        <p class="text-xs text-surface-500">To Pass</p>
                    </div>
                </div>

                <!-- User progress -->
                <div class="px-6 pb-4" v-if="attemptScores[mock.id]">
                    <div class="flex items-center gap-3 p-3 rounded-xl" :class="attemptScores[mock.id].best_score >= mock.passing_score_pct ? 'bg-success-50' : 'bg-surface-50'">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                             :class="attemptScores[mock.id].best_score >= mock.passing_score_pct ? 'bg-success-500 text-white' : 'bg-surface-200 text-surface-700'">
                            {{ attemptScores[mock.id].best_score }}%
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-700">Best Score</p>
                            <p class="text-xs text-surface-500">{{ attemptScores[mock.id].attempt_count }} attempt(s)</p>
                        </div>
                    </div>
                </div>

                <!-- Action -->
                <div class="px-6 pb-5">
                    <Link
                        :href="route('mock-exams.show', mock.id)"
                        class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700 transition"
                    >
                        <Play class="w-4 h-4" />
                        {{ attemptScores[mock.id] ? 'Retake' : 'Start Exam' }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <div v-else class="text-center py-16">
            <FileQuestion class="w-12 h-12 text-surface-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-surface-700">No mock exams available</h3>
            <p class="text-sm text-surface-500 mt-1">Mock exams will appear here once published.</p>
        </div>

        <!-- Pagination -->
        <div v-if="mocks.links?.length > 3" class="mt-8 flex justify-center gap-1">
            <Link
                v-for="link in mocks.links"
                :key="link.label"
                :href="link.url || '#'"
                v-html="link.label"
                class="px-3 py-1.5 text-sm rounded-lg"
                :class="link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100'"
                preserve-scroll
            />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Play, FileQuestion } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    mocks: Object,
    courses: Array,
    attemptScores: Object,
    filters: Object,
})

const selectedCourse = ref(props.filters?.course_id || '')

function applyFilter() {
    router.get(route('mock-exams.index'), {
        course_id: selectedCourse.value || undefined,
    }, { preserveState: true })
}

function typeLabel(type) {
    const map = { full_length: 'Full Length', mini_mock: 'Mini Mock', domain_focused: 'Domain Focused' }
    return map[type] || type
}

function typeBadgeClass(type) {
    const map = {
        full_length: 'bg-primary-100 text-primary-700',
        mini_mock: 'bg-accent-100 text-accent-700',
        domain_focused: 'bg-xp-100 text-xp-700',
    }
    return map[type] || 'bg-surface-100 text-surface-700'
}
</script>

<template>
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-display font-bold text-surface-900 mb-6">Edit Mock Exam</h1>

        <p class="text-surface-600 mb-6">Build a balanced assessment with a fixed time limit and a reproducible question blueprint.</p>
        <form @submit.prevent="submit" class="space-y-6">
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                <p class="font-semibold">Review these details before saving</p>
                <ul class="mt-2 list-disc pl-5"><li v-for="(error, key) in form.errors" :key="key">{{ error }}</li></ul>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-6 space-y-5">
                <div>
                    <label for="exam-title" class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                    <input id="exam-title" v-model="form.title" type="text" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500" required />
                    <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label for="exam-description" class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                    <textarea id="exam-description" v-model="form.description" rows="3" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="exam-course_id" class="block text-sm font-medium text-surface-700 mb-1">Course</label>
                        <select id="exam-course_id" v-model="form.course_id" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required>
                            <option value="">Select course</option>
                            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="exam-type" class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                        <select id="exam-type" v-model="form.type" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required>
                            <option value="full_length">Full Length</option>
                            <option value="mini_mock">Mini Mock</option>
                            <option value="domain_focused">Domain Focused</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="exam-question_count" class="block text-sm font-medium text-surface-700 mb-1">Question Count</label>
                        <input id="exam-question_count" v-model.number="form.question_count" type="number" min="5" max="300" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required />
                    </div>
                    <div>
                        <label for="exam-time_limit_minutes" class="block text-sm font-medium text-surface-700 mb-1">Time Limit (min)</label>
                        <input id="exam-time_limit_minutes" v-model.number="form.time_limit_minutes" type="number" min="5" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required />
                    </div>
                    <div>
                        <label for="exam-passing_score_pct" class="block text-sm font-medium text-surface-700 mb-1">Passing Score (%)</label>
                        <input id="exam-passing_score_pct" v-model.number="form.passing_score_pct" type="number" min="1" max="100" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required />
                    </div>
                </div>

                <!-- Domain distribution -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-2">Domain Distribution</label>
                    <div v-for="(count, i) in domainEntries" :key="i" class="flex items-center gap-3 mb-2">
                        <input :aria-label="`Domain ${i + 1} name`" v-model="domainEntries[i].domain" type="text" placeholder="Domain name" class="flex-1 px-3 py-2 border border-surface-300 rounded-lg text-sm" />
                        <input :aria-label="`Domain ${i + 1} question count`" v-model.number="domainEntries[i].count" type="number" min="1" class="w-20 px-3 py-2 border border-surface-300 rounded-lg text-sm" placeholder="#" />
                        <button type="button" :aria-label="`Remove domain ${i + 1}`" @click="domainEntries.splice(i, 1)" class="p-1.5 text-danger-500 hover:text-danger-700">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <button type="button" @click="domainEntries.push({ domain: '', count: 5 })" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        + Add Domain
                    </button>
                </div>

                <fieldset class="rounded-xl border border-surface-200 bg-surface-50 p-4">
                    <legend class="px-2 text-sm font-semibold text-surface-800">Difficulty distribution</legend>
                    <p class="text-sm text-surface-600 mb-4">Leave all counts at zero for any difficulty. Otherwise, counts must total {{ form.question_count }}.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div v-for="level in ['easy', 'medium', 'hard', 'expert']" :key="level">
                            <label :for="`difficulty-${level}`" class="block text-sm capitalize mb-1">{{ level }}</label>
                            <input :id="`difficulty-${level}`" v-model.number="difficultyCounts[level]" type="number" min="0" :max="form.question_count" class="w-full rounded-lg border border-surface-300 px-3 py-2" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-surface-600">The published question bank must meet both domain and difficulty counts before this exam can be published.</p>
                </fieldset>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_exam_simulation" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-surface-700">Exam Simulation</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.allow_pause" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-surface-700">Allow Pause</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_published" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-surface-700">Published</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3 justify-end">
                <Link :href="route('admin.mock-exams.index')" class="px-5 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    mockExam: Object,
    courses: Array,
})

const form = useForm({
    title: props.mockExam.title,
    description: props.mockExam.description || '',
    course_id: props.mockExam.course_id,
    type: props.mockExam.type,
    question_count: props.mockExam.question_count,
    time_limit_minutes: props.mockExam.time_limit_minutes,
    passing_score_pct: props.mockExam.passing_score_pct,
    domain_distribution: props.mockExam.domain_distribution,
    difficulty_distribution: props.mockExam.difficulty_distribution,
    is_exam_simulation: props.mockExam.is_exam_simulation,
    allow_pause: props.mockExam.allow_pause,
    is_published: props.mockExam.is_published,
})

const difficultyCounts = ref({ easy: 0, medium: 0, hard: 0, expert: 0, ...(props.mockExam.difficulty_distribution || {}) })
const domainEntries = ref([])

onMounted(() => {
    if (props.mockExam.domain_distribution) {
        domainEntries.value = Object.entries(props.mockExam.domain_distribution).map(([domain, count]) => ({ domain, count }))
    }
})

watch(domainEntries, (entries) => {
    const dist = {}
    entries.forEach(e => { if (e.domain) dist[e.domain] = e.count })
    form.domain_distribution = Object.keys(dist).length ? dist : null
}, { deep: true })

function submit() {
    const entries = domainEntries.value.filter(e => e.domain.trim())
    if (new Set(entries.map(e => e.domain.trim())).size !== entries.length) {
        form.setError('domain_distribution', 'Each domain can only appear once.')
        return
    }
    form.domain_distribution = Object.fromEntries(entries.map(e => [e.domain.trim(), e.count]))
    form.difficulty_distribution = Object.values(difficultyCounts.value).some(Number) ? { ...difficultyCounts.value } : null
    form.put(route('admin.mock-exams.update', props.mockExam.id))
}
</script>

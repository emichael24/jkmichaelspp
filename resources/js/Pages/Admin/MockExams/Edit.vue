<template>
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-display font-bold text-surface-900 mb-6">Edit Mock Exam</h1>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-white rounded-xl border border-surface-200 p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                    <input v-model="form.title" type="text" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500" required />
                    <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                    <textarea v-model="form.description" rows="3" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Course</label>
                        <select v-model="form.course_id" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required>
                            <option value="">Select course</option>
                            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                        <select v-model="form.type" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required>
                            <option value="full_length">Full Length</option>
                            <option value="mini_mock">Mini Mock</option>
                            <option value="domain_focused">Domain Focused</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Question Count</label>
                        <input v-model.number="form.question_count" type="number" min="5" max="300" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Time Limit (min)</label>
                        <input v-model.number="form.time_limit_minutes" type="number" min="5" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Passing Score (%)</label>
                        <input v-model.number="form.passing_score_pct" type="number" min="1" max="100" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm" required />
                    </div>
                </div>

                <!-- Domain distribution -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-2">Domain Distribution</label>
                    <div v-for="(count, i) in domainEntries" :key="i" class="flex items-center gap-3 mb-2">
                        <input v-model="domainEntries[i].domain" type="text" placeholder="Domain name" class="flex-1 px-3 py-2 border border-surface-300 rounded-lg text-sm" />
                        <input v-model.number="domainEntries[i].count" type="number" min="1" class="w-20 px-3 py-2 border border-surface-300 rounded-lg text-sm" placeholder="#" />
                        <button type="button" @click="domainEntries.splice(i, 1)" class="p-1.5 text-danger-500 hover:text-danger-700">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <button type="button" @click="domainEntries.push({ domain: '', count: 5 })" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        + Add Domain
                    </button>
                </div>

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
    is_exam_simulation: props.mockExam.is_exam_simulation,
    allow_pause: props.mockExam.allow_pause,
    is_published: props.mockExam.is_published,
})

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
    form.put(route('admin.mock-exams.update', props.mockExam.id))
}
</script>

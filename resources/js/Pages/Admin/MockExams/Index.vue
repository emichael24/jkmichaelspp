<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-display font-bold text-surface-900">Mock Exams</h1>
            <Link :href="route('admin.mock-exams.create')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700">
                <Plus class="w-4 h-4" /> New Mock Exam
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6">
            <input v-model="search" @input="debouncedFilter" type="text" placeholder="Search..." class="px-4 py-2 border border-surface-300 rounded-xl text-sm w-56" />
            <select v-model="courseFilter" @change="applyFilters" class="px-4 py-2 border border-surface-300 rounded-xl text-sm">
                <option value="">All Courses</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <select v-model="typeFilter" @change="applyFilters" class="px-4 py-2 border border-surface-300 rounded-xl text-sm">
                <option value="">All Types</option>
                <option value="full_length">Full Length</option>
                <option value="mini_mock">Mini Mock</option>
                <option value="domain_focused">Domain Focused</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-surface-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-50 text-surface-600 text-left">
                    <tr>
                        <th class="px-6 py-3 font-medium">Title</th>
                        <th class="px-6 py-3 font-medium">Course</th>
                        <th class="px-6 py-3 font-medium">Type</th>
                        <th class="px-6 py-3 font-medium">Questions</th>
                        <th class="px-6 py-3 font-medium">Time</th>
                        <th class="px-6 py-3 font-medium">Attempts</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    <tr v-for="mock in mocks.data" :key="mock.id" class="hover:bg-surface-50">
                        <td class="px-6 py-3 font-medium text-surface-900">{{ mock.title }}</td>
                        <td class="px-6 py-3 text-surface-600">{{ mock.course?.title }}</td>
                        <td class="px-6 py-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="typeBadge(mock.type)">
                                {{ typeLabel(mock.type) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-surface-700">{{ mock.question_count }}</td>
                        <td class="px-6 py-3 text-surface-700">{{ mock.time_limit_minutes }}m</td>
                        <td class="px-6 py-3 text-surface-700">{{ mock.completed_count }}/{{ mock.attempts_count }}</td>
                        <td class="px-6 py-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                                  :class="mock.is_published ? 'bg-success-100 text-success-700' : 'bg-surface-100 text-surface-600'">
                                {{ mock.is_published ? 'Published' : 'Draft' }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-1">
                                <Link :href="route('admin.mock-exams.edit', mock.id)" class="p-1.5 text-surface-400 hover:text-primary-600">
                                    <Pencil class="w-4 h-4" />
                                </Link>
                                <button
                                    @click="togglePublish(mock)"
                                    class="p-1.5 transition"
                                    :class="mock.is_published ? 'text-success-500 hover:text-warning-600' : 'text-surface-400 hover:text-success-600'"
                                >
                                    <component :is="mock.is_published ? EyeOff : Eye" class="w-4 h-4" />
                                </button>
                                <button @click="confirmDelete(mock)" class="p-1.5 text-surface-400 hover:text-danger-600">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="mocks.links?.length > 3" class="mt-6 flex justify-center gap-1">
            <Link v-for="link in mocks.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 text-sm rounded-lg"
                  :class="link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100'"
                  preserve-scroll />
        </div>

        <!-- Delete modal -->
        <div v-if="deletingMock" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center">
                <Trash2 class="w-10 h-10 text-danger-500 mx-auto mb-3" />
                <h3 class="text-lg font-bold text-surface-900 mb-2">Delete "{{ deletingMock.title }}"?</h3>
                <div class="flex gap-3 justify-center mt-6">
                    <button @click="deletingMock = null" class="px-5 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">Cancel</button>
                    <button @click="deleteMock" class="px-5 py-2.5 text-sm font-semibold bg-danger-600 text-white rounded-xl hover:bg-danger-700">Delete</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2, Eye, EyeOff } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    mocks: Object,
    courses: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const courseFilter = ref(props.filters?.course_id || '')
const typeFilter = ref(props.filters?.type || '')
const deletingMock = ref(null)

let filterTimeout = null
function debouncedFilter() {
    clearTimeout(filterTimeout)
    filterTimeout = setTimeout(applyFilters, 400)
}

function applyFilters() {
    router.get(route('admin.mock-exams.index'), {
        search: search.value || undefined,
        course_id: courseFilter.value || undefined,
        type: typeFilter.value || undefined,
    }, { preserveState: true })
}

function typeLabel(type) {
    return { full_length: 'Full Length', mini_mock: 'Mini Mock', domain_focused: 'Domain Focused' }[type] || type
}

function typeBadge(type) {
    return {
        full_length: 'bg-primary-100 text-primary-700',
        mini_mock: 'bg-accent-100 text-accent-700',
        domain_focused: 'bg-xp-100 text-xp-700',
    }[type] || 'bg-surface-100 text-surface-600'
}

function togglePublish(mock) {
    const r = mock.is_published ? 'admin.mock-exams.unpublish' : 'admin.mock-exams.publish'
    router.put(route(r, mock.id), {}, { preserveState: true })
}

function confirmDelete(mock) { deletingMock.value = mock }
function deleteMock() {
    router.delete(route('admin.mock-exams.destroy', deletingMock.value.id), { onSuccess: () => { deletingMock.value = null } })
}
</script>

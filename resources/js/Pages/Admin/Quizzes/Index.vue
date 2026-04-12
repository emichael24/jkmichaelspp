<template>
    <Head title="Quiz Management" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Quizzes</h1>
                <p class="text-surface-500 mt-1 text-sm">Create and manage assessment quizzes</p>
            </div>
            <Link href="/admin/quizzes/create">
                <Button variant="primary" size="md">
                    <Plus class="w-4 h-4 mr-1.5" />
                    Create Quiz
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <Input
                        v-model="filterForm.search"
                        placeholder="Search quizzes..."
                        :icon="SearchIcon"
                        clearable
                        @update:modelValue="debouncedFilter"
                    />
                </div>
                <select v-model="filterForm.course_id" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Courses</option>
                    <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                </select>
                <select v-model="filterForm.type" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Types</option>
                    <option value="topic_quiz">Topic Quiz</option>
                    <option value="module_test">Module Test</option>
                    <option value="practice_exam">Practice Exam</option>
                    <option value="diagnostic">Diagnostic</option>
                    <option value="certification_prep">Certification Prep</option>
                </select>
                <select v-model="filterForm.status" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
        </div>

        <!-- Quizzes Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Quiz</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Course</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Type</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Questions</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Attempts</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Time</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="quiz in quizzes.data" :key="quiz.id" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-medium text-surface-800">{{ quiz.title }}</p>
                                <p class="text-xs text-surface-400 mt-0.5">Pass: {{ quiz.passing_score_pct }}% · Max: {{ quiz.max_attempts || '∞' }} attempts</p>
                            </td>
                            <td class="px-5 py-4 text-surface-600">{{ quiz.course?.title || '—' }}</td>
                            <td class="px-5 py-4 text-center">
                                <span class="text-xs font-medium text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">
                                    {{ typeLabel(quiz.type) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center font-medium text-surface-700">
                                {{ quiz.questions_count }} / {{ quiz.question_count }}
                            </td>
                            <td class="px-5 py-4 text-center text-surface-600">{{ quiz.attempts_count }}</td>
                            <td class="px-5 py-4 text-center text-surface-600">
                                {{ quiz.time_limit_minutes ? `${quiz.time_limit_minutes}min` : '—' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="quiz.is_published ? 'bg-success-50 text-success-700' : 'bg-surface-100 text-surface-600'" class="text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ quiz.is_published ? 'Published' : 'Draft' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/admin/quizzes/${quiz.id}/edit`" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors">
                                        <Pencil class="w-4 h-4" />
                                    </Link>
                                    <button @click="confirmDelete(quiz)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="quizzes.data.length === 0">
                            <td colspan="8" class="px-5 py-12 text-center text-surface-400">
                                No quizzes found. Create your first quiz.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="quizzes.last_page > 1" class="border-t border-surface-100 px-5 py-3 flex items-center justify-between">
                <p class="text-xs text-surface-500">Showing {{ quizzes.from }}-{{ quizzes.to }} of {{ quizzes.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in quizzes.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                            link.active ? 'bg-primary-100 text-primary-700' : 'text-surface-500 hover:bg-surface-100',
                            !link.url && 'opacity-50 pointer-events-none',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <Modal :show="!!deleting" @close="deleting = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-2">Delete Quiz?</h3>
                <p class="text-sm text-surface-500 mb-6">This will remove the quiz and all its question assignments. Existing attempt history will be preserved.</p>
                <div class="flex gap-3 justify-end">
                    <button @click="deleting = null" class="px-4 py-2 rounded-xl text-sm font-medium text-surface-600 hover:bg-surface-100">Cancel</button>
                    <button @click="deleteQuiz" class="px-4 py-2 rounded-xl bg-danger-600 text-white text-sm font-semibold hover:bg-danger-700">Delete</button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Search as SearchIcon } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Modal from '@/Components/UI/Modal.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    quizzes: Object,
    courses: Array,
    filters: Object,
});

const filterForm = reactive({ ...props.filters });
const deleting = ref(null);
let debounceTimer;

function applyFilters() {
    router.get('/admin/quizzes', filterForm, { preserveState: true, preserveScroll: true });
}

function debouncedFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
}

function confirmDelete(quiz) { deleting.value = quiz; }

function deleteQuiz() {
    if (!deleting.value) return;
    router.delete(`/admin/quizzes/${deleting.value.id}`, {
        onSuccess: () => { deleting.value = null; },
    });
}

function typeLabel(t) {
    return {
        topic_quiz: 'Topic Quiz', module_test: 'Module Test', practice_exam: 'Practice Exam',
        diagnostic: 'Diagnostic', certification_prep: 'Cert Prep',
    }[t] || t;
}
</script>

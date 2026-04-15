<template>
    <Head title="Question Bank" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Question Bank</h1>
                <p class="text-surface-500 mt-1 text-sm">Manage assessment questions across all courses</p>
            </div>
            <Link href="/admin/questions/create">
                <Button variant="primary" size="md">
                    <Plus class="w-4 h-4 mr-1.5" />
                    Add Question
                </Button>
            </Link>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-surface-200 p-4">
                <p class="text-2xl font-bold text-surface-900">{{ stats.total }}</p>
                <p class="text-xs text-surface-500 mt-1">Total Questions</p>
            </div>
            <div class="bg-white rounded-2xl border border-surface-200 p-4">
                <p class="text-2xl font-bold text-success-600">{{ stats.published }}</p>
                <p class="text-xs text-surface-500 mt-1">Published</p>
            </div>
            <div class="bg-white rounded-2xl border border-surface-200 p-4">
                <p class="text-2xl font-bold text-surface-500">{{ stats.draft }}</p>
                <p class="text-xs text-surface-500 mt-1">Drafts</p>
            </div>
            <div class="bg-white rounded-2xl border border-surface-200 p-4">
                <p class="text-2xl font-bold text-warning-600">{{ stats.review }}</p>
                <p class="text-xs text-surface-500 mt-1">In Review</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <Input
                        v-model="filterForm.search"
                        placeholder="Search question stems..."
                        :icon="SearchIcon"
                        clearable
                        @update:modelValue="debouncedFilter"
                    />
                </div>
                <select v-model="filterForm.course_id" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Courses</option>
                    <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                </select>
                <select v-model="filterForm.domain" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Domains</option>
                    <option v-for="d in domains" :key="d" :value="d">{{ d }}</option>
                </select>
                <select v-model="filterForm.difficulty" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Difficulties</option>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                    <option value="expert">Expert</option>
                </select>
                <select v-model="filterForm.status" class="rounded-xl border-surface-200 text-sm px-3 py-2" @change="applyFilters">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="review">In Review</option>
                    <option value="approved">Approved</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
        </div>

        <!-- Questions Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Question</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Course</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Domain</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Type</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Difficulty</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Correct %</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="q in questions.data" :key="q.id" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-4 max-w-[300px]">
                                <p class="text-surface-800 font-medium truncate">{{ q.stem }}</p>
                                <p class="text-xs text-surface-400 mt-0.5">ID: {{ q.uuid?.substring(0, 8) }}</p>
                            </td>
                            <td class="px-5 py-4 text-surface-600">{{ q.course?.title || '—' }}</td>
                            <td class="px-5 py-4 text-surface-600">{{ q.domain || '—' }}</td>
                            <td class="px-5 py-4 text-center">
                                <span class="text-xs font-medium text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">
                                    {{ typeLabel(q.type) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="difficultyClass(q.difficulty)">{{ q.difficulty }}</span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span v-if="q.exposure_count > 0" class="text-surface-700 font-medium">
                                    {{ Math.round(q.correct_rate * 100) }}%
                                </span>
                                <span v-else class="text-surface-400">—</span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="statusClass(q.status)">{{ statusLabel(q.status) }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/admin/questions/${q.id}/edit`" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors">
                                        <Pencil class="w-4 h-4" />
                                    </Link>
                                    <button @click="confirmDelete(q)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="questions.data.length === 0">
                            <td colspan="8" class="px-5 py-12 text-center text-surface-400">
                                No questions found. Create your first question to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="questions.last_page > 1" class="border-t border-surface-100 px-5 py-3 flex items-center justify-between">
                <p class="text-xs text-surface-500">Showing {{ questions.from }}-{{ questions.to }} of {{ questions.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in questions.links"
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

        <!-- Delete confirm modal -->
        <Modal :show="!!deleting" @close="deleting = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-2">Delete Question?</h3>
                <p class="text-sm text-surface-500 mb-6">This will soft-delete the question. It can be restored later if needed.</p>
                <div class="flex gap-3 justify-end">
                    <button @click="deleting = null" class="px-4 py-2 rounded-xl text-sm font-medium text-surface-600 hover:bg-surface-100">Cancel</button>
                    <button @click="deleteQuestion" class="px-4 py-2 rounded-xl bg-danger-600 text-white text-sm font-semibold hover:bg-danger-700">Delete</button>
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
    questions: Object,
    courses: Array,
    domains: Array,
    stats: Object,
    filters: Object,
});

const filterForm = reactive({ ...props.filters });
const deleting = ref(null);
let debounceTimer;

function applyFilters() {
    router.get('/admin/questions', filterForm, { preserveState: true, preserveScroll: true });
}

function debouncedFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 400);
}

function confirmDelete(q) { deleting.value = q; }

function deleteQuestion() {
    if (!deleting.value) return;
    router.delete(`/admin/questions/${deleting.value.id}`, {
        onSuccess: () => { deleting.value = null; },
    });
}

function typeLabel(t) {
    return {
        multiple_choice: 'Single',
        multiple_select: 'Multi',
        true_false: 'T/F',
        scenario: 'Scenario',
        situational_judgment: 'SJT',
        formula: 'Formula',
        fill_blank: 'Fill',
        ordering: 'Order',
        matching: 'Match',
    }[t] || t;
}

function difficultyClass(d) {
    const base = 'text-xs font-medium px-2 py-0.5 rounded-full capitalize';
    const map = {
        easy: 'bg-success-50 text-success-700',
        medium: 'bg-warning-50 text-warning-700',
        hard: 'bg-danger-50 text-danger-700',
        expert: 'bg-surface-800 text-white',
    };
    return `${base} ${map[d] || 'bg-surface-100 text-surface-600'}`;
}

function statusClass(s) {
    const base = 'text-xs font-medium px-2 py-0.5 rounded-full';
    const map = {
        draft: 'bg-surface-100 text-surface-600',
        review: 'bg-warning-50 text-warning-700',
        approved: 'bg-primary-50 text-primary-700',
        published: 'bg-success-50 text-success-700',
        archived: 'bg-danger-50 text-danger-700',
    };
    return `${base} ${map[s] || 'bg-surface-100 text-surface-600'}`;
}

function statusLabel(s) {
    return { draft: 'Draft', review: 'In Review', approved: 'Approved', published: 'Published', archived: 'Archived' }[s] || s;
}
</script>

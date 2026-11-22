<template>
    <Head title="Course Management" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Courses</h1>
                <p class="text-surface-500 mt-1 text-sm">Manage all courses on the platform</p>
            </div>
            <Link href="/admin/courses/create">
                <Button variant="primary" size="md">
                    <Plus class="w-4 h-4 mr-1.5" />
                    Create Course
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <Input
                        aria-label="Search courses" v-model="filterForm.search"
                        placeholder="Search courses..."
                        :icon="SearchIcon"
                        clearable
                        @update:modelValue="debouncedFilter"
                    />
                </div>
                <select
                    aria-label="Filter by category" v-model="filterForm.category"
                    class="rounded-xl border-surface-200 text-sm px-3 py-2"
                    @change="applyFilters"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <select
                    aria-label="Filter by level" v-model="filterForm.level"
                    class="rounded-xl border-surface-200 text-sm px-3 py-2"
                    @change="applyFilters"
                >
                    <option value="">All Levels</option>
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
                <select
                    aria-label="Filter by status" v-model="filterForm.status"
                    class="rounded-xl border-surface-200 text-sm px-3 py-2"
                    @change="applyFilters"
                >
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
        </div>

        <!-- Course Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Course</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Category</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Modules</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Lessons</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Enrolled</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="course in courses.data" :key="course.id" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-8 rounded-lg bg-surface-100 overflow-hidden flex-shrink-0">
                                        <img v-if="course.thumbnail" :src="`/storage/${course.thumbnail}`" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <BookOpen class="w-4 h-4 text-surface-300" />
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-surface-900 truncate">{{ course.title }}</p>
                                        <p class="text-xs text-surface-400">{{ course.level }} · {{ course.type }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-surface-600">{{ course.category?.name || '—' }}</td>
                            <td class="px-5 py-4 text-center text-surface-600">{{ course.modules_count }}</td>
                            <td class="px-5 py-4 text-center text-surface-600">{{ course.lessons_count }}</td>
                            <td class="px-5 py-4 text-center text-surface-600"><Link :href="`/admin/courses/${course.id}/learners`" :aria-label="`Manage learners for ${course.title}`" class="rounded-lg px-3 py-2 font-semibold text-primary-700 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">{{ course.enrollments_count }} <span class="text-xs font-normal">Manage</span></Link></td>
                            <td class="px-5 py-4 text-center">
                                <Badge :variant="course.is_published ? 'success' : 'warning'" size="sm">
                                    {{ course.is_published ? 'Published' : 'Draft' }}
                                </Badge>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/admin/courses/${course.id}/edit`" :aria-label="`Edit ${course.title}`" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors"><Pencil class="w-4 h-4" /></Link>
                                    <button
                                        v-if="!course.is_published"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-success-600 transition-colors"
                                        :aria-label="`Publish ${course.title}`" @click="publish(course)"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-else
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-warning-600 transition-colors"
                                        :aria-label="`Unpublish ${course.title}`" @click="unpublish(course)"
                                    >
                                        <EyeOff class="w-4 h-4" />
                                    </button>
                                    <button
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors"
                                        :aria-label="`Delete ${course.title}`" @click="confirmDelete(course)"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-if="!courses.data?.length" class="p-12 text-center">
                <BookOpen class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No courses yet</p>
                <p class="text-sm text-surface-400 mt-1">Create your first course to get started.</p>
            </div>

            <!-- Pagination -->
            <div v-if="courses.last_page > 1" class="border-t border-surface-100 px-5 py-3 flex items-center justify-between">
                <p class="text-sm text-surface-400">
                    Showing {{ courses.from }}–{{ courses.to }} of {{ courses.total }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in courses.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm transition-colors',
                            link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                        v-html="link.label"
                        preserve-state
                    />
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-surface-900">Delete Course</h3>
                <p class="mt-2 text-sm text-surface-500">
                    Are you sure you want to delete "{{ courseToDelete?.title }}"? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
                    <Button variant="danger" @click="deleteCourse" :disabled="deleteForm.processing">Delete</Button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { Search as SearchIcon, Plus, BookOpen, Pencil, Eye, EyeOff, Trash2 } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    courses: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search: props.filters.search || '',
    category: props.filters.category || '',
    level: props.filters.level || '',
    status: props.filters.status || '',
});

let filterTimeout = null;
const debouncedFilter = () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 400);
};

const applyFilters = () => {
    router.get('/admin/courses', {
        search: filterForm.search || undefined,
        category: filterForm.category || undefined,
        level: filterForm.level || undefined,
        status: filterForm.status || undefined,
    }, { preserveState: true, replace: true });
};

const publish = (course) => {
    router.put(`/admin/courses/${course.id}/publish`, {}, { preserveState: true });
};

const unpublish = (course) => {
    router.put(`/admin/courses/${course.id}/unpublish`, {}, { preserveState: true });
};

const showDeleteModal = ref(false);
const courseToDelete = ref(null);
const deleteForm = useForm({});

const confirmDelete = (course) => {
    courseToDelete.value = course;
    showDeleteModal.value = true;
};

const deleteCourse = () => {
    deleteForm.delete(`/admin/courses/${courseToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
            courseToDelete.value = null;
        },
    });
};
</script>

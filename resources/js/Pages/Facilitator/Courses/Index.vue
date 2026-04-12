<template>
    <Head title="My Courses" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">My Courses</h1>
                <p class="text-surface-500 mt-1 text-sm">Create and manage your courses. Submit for admin review when ready.</p>
            </div>
            <Link href="/facilitator/courses/create">
                <Button variant="primary" size="md">
                    <Plus class="w-4 h-4 mr-1.5" />
                    New Course
                </Button>
            </Link>
        </div>

        <!-- Status Tabs -->
        <div class="flex gap-1 bg-surface-100 p-1 rounded-xl w-fit">
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                @click="setStatus(tab.value)"
                :class="[
                    'px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                    currentStatus === tab.value
                        ? 'bg-white shadow-sm text-surface-900'
                        : 'text-surface-500 hover:text-surface-700',
                ]"
            >
                {{ tab.label }}
                <span v-if="tab.value !== 'all'" :class="['ml-1.5 text-xs px-1.5 py-0.5 rounded-full', statusBadgeClass(tab.value)]">
                    {{ counts[tab.value] || 0 }}
                </span>
            </button>
        </div>

        <!-- Course List -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div v-if="courses.data?.length" class="divide-y divide-surface-100">
                <div
                    v-for="course in courses.data"
                    :key="course.id"
                    class="p-5 hover:bg-surface-50 transition-colors"
                >
                    <div class="flex items-start gap-4">
                        <!-- Thumbnail -->
                        <div class="w-16 h-12 rounded-lg bg-surface-100 overflow-hidden flex-shrink-0">
                            <img v-if="course.thumbnail" :src="`/storage/${course.thumbnail}`" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <BookOpen class="w-5 h-5 text-surface-300" />
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-surface-900 truncate">{{ course.title }}</h3>
                                    <p class="text-sm text-surface-500 mt-0.5">
                                        {{ course.category?.name || 'No category' }} · {{ course.level }} · {{ course.type }}
                                    </p>
                                </div>
                                <!-- Approval Status Badge -->
                                <Badge :variant="approvalBadgeVariant(course.approval_status)" size="sm" class="flex-shrink-0">
                                    {{ approvalLabel(course.approval_status) }}
                                </Badge>
                            </div>

                            <!-- Rejection reason -->
                            <div v-if="course.approval_status === 'rejected' && course.rejection_reason"
                                 class="mt-2 p-2 bg-danger-50 rounded-lg border border-danger-100">
                                <p class="text-xs text-danger-700">
                                    <strong>Rejection reason:</strong> {{ course.rejection_reason }}
                                </p>
                            </div>

                            <!-- Stats + Actions -->
                            <div class="flex items-center justify-between mt-3">
                                <div class="flex items-center gap-4 text-xs text-surface-400">
                                    <span class="flex items-center gap-1">
                                        <Layers class="w-3.5 h-3.5" />
                                        {{ course.modules_count || 0 }} modules
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <FileText class="w-3.5 h-3.5" />
                                        {{ course.lessons_count || 0 }} lessons
                                    </span>
                                    <span v-if="course.price" class="flex items-center gap-1">
                                        <DollarSign class="w-3.5 h-3.5" />
                                        ₦{{ Number(course.price).toLocaleString() }}
                                    </span>
                                    <span v-else class="text-success-600 font-medium">Free</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Submit for Review -->
                                    <button
                                        v-if="['draft', 'rejected'].includes(course.approval_status)"
                                        @click="submitForReview(course)"
                                        class="px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors"
                                    >
                                        Submit for Review
                                    </button>
                                    <!-- Pending badge (non-interactive) -->
                                    <span v-else-if="course.approval_status === 'pending_review'"
                                          class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium">
                                        Under Review
                                    </span>

                                    <!-- Edit -->
                                    <Link
                                        v-if="['draft', 'rejected'].includes(course.approval_status)"
                                        :href="`/facilitator/courses/${course.id}/edit`"
                                    >
                                        <button class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                    </Link>

                                    <!-- Delete -->
                                    <button
                                        v-if="course.approval_status === 'draft'"
                                        @click="confirmDelete(course)"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="p-16 text-center">
                <BookOpen class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No courses yet</p>
                <p class="text-sm text-surface-400 mt-1">Create your first course to get started.</p>
                <Link href="/facilitator/courses/create" class="inline-block mt-4">
                    <Button variant="primary" size="sm">Create Course</Button>
                </Link>
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
    </div>

    <!-- Delete Confirm Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-surface-900">Delete Course</h3>
            <p class="mt-2 text-sm text-surface-500">
                Are you sure you want to delete "{{ courseToDelete?.title }}"? This cannot be undone.
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
                <Button variant="danger" @click="deleteCourse" :disabled="deleteForm.processing">Delete</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { BookOpen, Plus, Layers, FileText, DollarSign, Pencil, Trash2 } from 'lucide-vue-next';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    courses: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    currentStatus: { type: String, default: 'all' },
});

const statusTabs = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending_review', label: 'Pending Review' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

function setStatus(status) {
    router.get('/facilitator/courses', { status }, { preserveState: true, replace: true });
}

function approvalBadgeVariant(status) {
    return {
        draft: 'secondary',
        pending_review: 'info',
        approved: 'success',
        rejected: 'danger',
    }[status] || 'secondary';
}

function approvalLabel(status) {
    return {
        draft: 'Draft',
        pending_review: 'Pending Review',
        approved: 'Approved',
        rejected: 'Rejected',
    }[status] || status;
}

function statusBadgeClass(status) {
    return {
        draft: 'bg-surface-200 text-surface-600',
        pending_review: 'bg-blue-100 text-blue-700',
        approved: 'bg-success-100 text-success-700',
        rejected: 'bg-danger-100 text-danger-700',
    }[status] || 'bg-surface-200 text-surface-600';
}

// Submit for review
function submitForReview(course) {
    if (!confirm(`Submit "${course.title}" for admin review?`)) return;
    router.post(`/facilitator/courses/${course.id}/submit-for-review`, {}, {
        preserveScroll: true,
    });
}

// Delete
const showDeleteModal = ref(false);
const courseToDelete = ref(null);
const deleteForm = useForm({});

function confirmDelete(course) {
    courseToDelete.value = course;
    showDeleteModal.value = true;
}

function deleteCourse() {
    deleteForm.delete(`/facilitator/courses/${courseToDelete.value.id}`, {
        onSuccess: () => { showDeleteModal.value = false; courseToDelete.value = null; },
    });
}
</script>

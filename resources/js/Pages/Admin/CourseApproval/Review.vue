<template>
    <Head :title="`Review: ${course.title}`" />

    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/admin/course-approvals" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </Link>
            <div class="flex-1">
                <h1 class="text-xl font-display font-bold text-surface-900">Review Course</h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <Badge :variant="statusVariant" size="sm">{{ statusLabel }}</Badge>
                    <span class="text-xs text-surface-400">
                        by {{ course.author?.first_name }} {{ course.author?.last_name }}
                        <span v-if="course.submitted_for_review_at">
                            · submitted {{ formatDate(course.submitted_for_review_at) }}
                        </span>
                    </span>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div v-if="course.approval_status === 'pending_review'" class="flex gap-2">
                <button
                    @click="showApproveModal = true"
                    class="px-4 py-2 bg-success-600 text-white rounded-xl text-sm font-semibold hover:bg-success-700 transition-colors flex items-center gap-2"
                >
                    <CheckCircle class="w-4 h-4" /> Approve
                </button>
                <button
                    @click="showRejectModal = true"
                    class="px-4 py-2 bg-danger-600 text-white rounded-xl text-sm font-semibold hover:bg-danger-700 transition-colors flex items-center gap-2"
                >
                    <XCircle class="w-4 h-4" /> Reject
                </button>
            </div>

            <div v-else-if="course.approval_status === 'approved'" class="flex gap-2">
                <span class="text-xs text-surface-400 self-center">
                    Approved by {{ course.approver?.first_name }} on {{ formatDate(course.approved_at) }}
                </span>
                <button
                    @click="unpublish"
                    class="px-3 py-2 border border-surface-300 text-surface-600 rounded-xl text-sm font-medium hover:bg-surface-100"
                >
                    Unpublish
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Course Preview (left 2/3) -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Thumbnail + Info -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
                    <div class="aspect-video w-full bg-surface-100 overflow-hidden">
                        <img v-if="course.thumbnail" :src="`/storage/${course.thumbnail}`" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <BookOpen class="w-12 h-12 text-surface-300" />
                        </div>
                    </div>
                    <div class="p-5 space-y-3">
                        <h2 class="text-xl font-display font-bold text-surface-900">{{ course.title }}</h2>
                        <p v-if="course.subtitle" class="text-surface-600">{{ course.subtitle }}</p>

                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="px-2.5 py-1 bg-surface-100 text-surface-600 rounded-full">{{ course.level }}</span>
                            <span class="px-2.5 py-1 bg-surface-100 text-surface-600 rounded-full">{{ course.type }}</span>
                            <span v-if="course.category" class="px-2.5 py-1 bg-primary-50 text-primary-700 rounded-full">{{ course.category.name }}</span>
                            <span v-if="course.duration_hours" class="px-2.5 py-1 bg-surface-100 text-surface-600 rounded-full">
                                {{ course.duration_hours }}h
                            </span>
                            <span v-if="course.price" class="px-2.5 py-1 bg-success-50 text-success-700 rounded-full font-semibold">
                                ₦{{ Number(course.price).toLocaleString() }}
                            </span>
                            <span v-else class="px-2.5 py-1 bg-success-50 text-success-700 rounded-full font-semibold">Free</span>
                        </div>

                        <p class="text-sm text-surface-600 leading-relaxed">{{ course.description }}</p>

                        <!-- Learning Outcomes -->
                        <div v-if="course.learning_outcomes?.length" class="mt-3 pt-3 border-t border-surface-100">
                            <p class="font-semibold text-sm text-surface-800 mb-2">Learning Outcomes</p>
                            <ul class="space-y-1.5">
                                <li v-for="(outcome, i) in course.learning_outcomes" :key="i" class="flex items-start gap-2 text-sm text-surface-600">
                                    <CheckCircle class="w-4 h-4 text-success-500 flex-shrink-0 mt-0.5" />
                                    {{ outcome }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Modules & Lessons -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5 space-y-3">
                    <h3 class="font-semibold text-surface-800">Curriculum ({{ course.modules?.length || 0 }} modules)</h3>
                    <div v-for="(mod, mi) in course.modules" :key="mod.id" class="border border-surface-100 rounded-xl overflow-hidden">
                        <div class="flex items-center gap-3 px-4 py-3 bg-surface-50">
                            <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                {{ mi + 1 }}
                            </span>
                            <span class="font-medium text-surface-800 text-sm">{{ mod.title }}</span>
                            <span class="ml-auto text-xs text-surface-400">{{ mod.lessons?.length || 0 }} lessons</span>
                        </div>
                        <div v-if="mod.lessons?.length" class="divide-y divide-surface-100">
                            <div v-for="(lesson, li) in mod.lessons" :key="lesson.id" class="flex items-center gap-3 px-4 py-2.5">
                                <span class="text-xs text-surface-400 w-5 text-center">{{ li + 1 }}</span>
                                <div class="flex-1">
                                    <span class="text-sm text-surface-700">{{ lesson.title }}</span>
                                    <span class="ml-2 text-xs text-surface-400">{{ lesson.content_type }}</span>
                                </div>
                                <span v-if="lesson.is_free_preview" class="text-xs text-primary-600 font-medium">Free preview</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="!course.modules?.length" class="p-6 text-center text-sm text-surface-400">
                        No modules added yet.
                    </div>
                </div>
            </div>

            <!-- Sidebar (right 1/3) -->
            <div class="space-y-5">
                <!-- Author Card -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
                    <h3 class="font-semibold text-surface-800 mb-3">Facilitator</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-sm font-bold text-primary-700 flex-shrink-0">
                            {{ (course.author?.first_name?.[0] || '') + (course.author?.last_name?.[0] || '') }}
                        </div>
                        <div>
                            <p class="font-medium text-surface-900 text-sm">{{ course.author?.first_name }} {{ course.author?.last_name }}</p>
                            <p class="text-xs text-surface-400">{{ course.author?.email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Admin Notes -->
                <div v-if="course.approval_status === 'approved' && course.admin_notes" class="bg-success-50 rounded-2xl border border-success-200 p-5">
                    <h3 class="font-semibold text-success-800 mb-2">Approval Notes</h3>
                    <p class="text-sm text-success-700">{{ course.admin_notes }}</p>
                </div>

                <!-- Rejection -->
                <div v-if="course.approval_status === 'rejected'" class="bg-danger-50 rounded-2xl border border-danger-200 p-5">
                    <h3 class="font-semibold text-danger-800 mb-2">Rejection Reason</h3>
                    <p class="text-sm text-danger-700">{{ course.rejection_reason }}</p>
                </div>

                <!-- Quick Approve/Reject actions (duplicate for convenience on small screens) -->
                <div v-if="course.approval_status === 'pending_review'" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5 space-y-3">
                    <h3 class="font-semibold text-surface-800">Decision</h3>
                    <button @click="showApproveModal = true" class="w-full py-2.5 bg-success-600 text-white rounded-xl text-sm font-semibold hover:bg-success-700 transition-colors flex items-center justify-center gap-2">
                        <CheckCircle class="w-4 h-4" /> Approve Course
                    </button>
                    <button @click="showRejectModal = true" class="w-full py-2.5 bg-danger-600 text-white rounded-xl text-sm font-semibold hover:bg-danger-700 transition-colors flex items-center justify-center gap-2">
                        <XCircle class="w-4 h-4" /> Reject Course
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <Modal :show="showApproveModal" @close="showApproveModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-surface-900 flex items-center gap-2">
                <CheckCircle class="w-5 h-5 text-success-600" /> Approve Course
            </h3>
            <p class="mt-2 text-sm text-surface-500">
                Approving will notify the facilitator. The course can optionally be published immediately.
            </p>
            <div class="mt-4 space-y-3">
                <div>
                    <label class="text-sm font-medium text-surface-700">Notes (optional)</label>
                    <textarea
                        v-model="approveForm.admin_notes"
                        rows="3"
                        placeholder="Any feedback for the facilitator…"
                        class="mt-1.5 w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                    ></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="publish_now" v-model="approveForm.publish_immediately" class="rounded text-primary-600" />
                    <label for="publish_now" class="text-sm text-surface-700">Publish immediately after approval</label>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <Button variant="ghost" @click="showApproveModal = false">Cancel</Button>
                <Button variant="success" @click="approveCourse" :disabled="approveForm.processing">
                    {{ approveForm.processing ? 'Approving…' : 'Confirm Approval' }}
                </Button>
            </div>
        </div>
    </Modal>

    <!-- Reject Modal -->
    <Modal :show="showRejectModal" @close="showRejectModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-surface-900 flex items-center gap-2">
                <XCircle class="w-5 h-5 text-danger-600" /> Reject Course
            </h3>
            <p class="mt-2 text-sm text-surface-500">
                Please provide a clear reason so the facilitator can improve and resubmit.
            </p>
            <div class="mt-4">
                <label class="text-sm font-medium text-surface-700">Rejection Reason <span class="text-danger-600">*</span></label>
                <textarea
                    v-model="rejectForm.rejection_reason"
                    rows="4"
                    placeholder="Explain what needs to be changed or improved…"
                    class="mt-1.5 w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                ></textarea>
                <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-danger-600 mt-1">{{ rejectForm.errors.rejection_reason }}</p>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <Button variant="ghost" @click="showRejectModal = false">Cancel</Button>
                <Button variant="danger" @click="rejectCourse" :disabled="rejectForm.processing || !rejectForm.rejection_reason">
                    {{ rejectForm.processing ? 'Rejecting…' : 'Confirm Rejection' }}
                </Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { ArrowLeft, BookOpen, CheckCircle, XCircle } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    course: { type: Object, required: true },
});

const statusVariant = computed(() => ({
    draft: 'secondary', pending_review: 'warning', approved: 'success', rejected: 'danger',
}[props.course.approval_status] || 'secondary'));

const statusLabel = computed(() => ({
    draft: 'Draft', pending_review: 'Pending Review', approved: 'Approved', rejected: 'Rejected',
}[props.course.approval_status] || props.course.approval_status));

function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

// Approve
const showApproveModal = ref(false);
const approveForm = useForm({ admin_notes: '', publish_immediately: false });

function approveCourse() {
    approveForm.post(`/admin/course-approvals/${props.course.id}/approve`, {
        onSuccess: () => { showApproveModal.value = false; },
    });
}

// Reject
const showRejectModal = ref(false);
const rejectForm = useForm({ rejection_reason: '' });

function rejectCourse() {
    rejectForm.post(`/admin/course-approvals/${props.course.id}/reject`, {
        onSuccess: () => { showRejectModal.value = false; },
    });
}

// Unpublish
function unpublish() {
    if (!confirm('Unpublish this course? It will be hidden from learners.')) return;
    router.post(`/admin/course-approvals/${props.course.id}/unpublish`, {}, { preserveScroll: true });
}
</script>

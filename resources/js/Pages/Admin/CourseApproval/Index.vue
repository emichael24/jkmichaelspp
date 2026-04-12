<template>
    <Head title="Course Approvals" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Course Approvals</h1>
                <p class="text-surface-500 mt-1 text-sm">Review and approve courses submitted by facilitators.</p>
            </div>
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
                <span v-if="counts[tab.value] !== undefined"
                      :class="['ml-1.5 text-xs px-1.5 py-0.5 rounded-full', tab.badgeClass]">
                    {{ counts[tab.value] }}
                </span>
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Course</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Facilitator</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Category</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Submitted</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Action</th>
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
                                        <p class="font-semibold text-surface-900 truncate max-w-xs">{{ course.title }}</p>
                                        <p class="text-xs text-surface-400">{{ course.level }} · {{ course.type }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-700 flex-shrink-0">
                                        {{ (course.author?.first_name?.[0] || '') + (course.author?.last_name?.[0] || '') }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-surface-800">{{ course.author?.first_name }} {{ course.author?.last_name }}</p>
                                        <p class="text-xs text-surface-400">{{ course.author?.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-surface-600">{{ course.category?.name || '—' }}</td>
                            <td class="px-5 py-4 text-surface-500 text-xs">
                                {{ course.submitted_for_review_at ? formatDate(course.submitted_for_review_at) : '—' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <Badge :variant="statusVariant(course.approval_status)" size="sm">
                                    {{ statusLabel(course.approval_status) }}
                                </Badge>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Link :href="`/admin/course-approvals/${course.id}`">
                                    <Button variant="secondary" size="sm">Review</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-if="!courses.data?.length" class="p-16 text-center">
                <CheckCircle class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No courses in this queue</p>
                <p class="text-sm text-surface-400 mt-1">New submissions from facilitators will appear here.</p>
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
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import { BookOpen, CheckCircle } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    courses: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    currentStatus: { type: String, default: 'pending_review' },
});

const statusTabs = [
    { value: 'pending_review', label: 'Pending Review', badgeClass: 'bg-amber-100 text-amber-700' },
    { value: 'approved', label: 'Approved', badgeClass: 'bg-success-100 text-success-700' },
    { value: 'rejected', label: 'Rejected', badgeClass: 'bg-danger-100 text-danger-700' },
    { value: 'draft', label: 'Drafts', badgeClass: 'bg-surface-200 text-surface-600' },
];

function setStatus(status) {
    router.get('/admin/course-approvals', { status }, { preserveState: true, replace: true });
}

function statusVariant(status) {
    return { draft: 'secondary', pending_review: 'warning', approved: 'success', rejected: 'danger' }[status] || 'secondary';
}

function statusLabel(status) {
    return { draft: 'Draft', pending_review: 'Pending', approved: 'Approved', rejected: 'Rejected' }[status] || status;
}

function formatDate(dt) {
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

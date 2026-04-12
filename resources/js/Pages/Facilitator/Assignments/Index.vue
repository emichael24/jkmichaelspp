<template>
    <Head :title="`Assignments – ${cohort.name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Assignments</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }}</p>
                </div>
            </div>
            <a :href="`/facilitator/cohorts/${cohort.id}/assignments/create`" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                New Assignment
            </a>
        </div>

        <!-- Assignments List -->
        <div v-if="assignments.length" class="space-y-3">
            <div
                v-for="assignment in assignments"
                :key="assignment.id"
                class="bg-white rounded-xl border border-surface-200/60 p-5"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="font-semibold text-surface-900">{{ assignment.title }}</h3>
                        <p class="text-sm text-surface-500 mt-1 line-clamp-2">{{ assignment.description }}</p>
                        <div class="flex items-center gap-4 mt-3 text-xs text-surface-400">
                            <span class="flex items-center gap-1">
                                <FileText class="w-3.5 h-3.5" />
                                {{ assignment.submissions_count || 0 }} submissions
                            </span>
                            <span v-if="assignment.due_date" class="flex items-center gap-1">
                                <Clock class="w-3.5 h-3.5" />
                                Due {{ formatDate(assignment.due_date) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Award class="w-3.5 h-3.5" />
                                {{ assignment.max_score }} pts
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 ml-4">
                        <a :href="`/facilitator/cohorts/${cohort.id}/assignments/${assignment.id}/submissions`"
                           class="px-3 py-1.5 bg-primary-50 text-primary-700 rounded-lg text-xs font-medium hover:bg-primary-100">
                            Grade
                        </a>
                        <a :href="`/facilitator/cohorts/${cohort.id}/assignments/${assignment.id}/edit`"
                           class="px-3 py-1.5 bg-surface-50 text-surface-600 rounded-lg text-xs font-medium hover:bg-surface-100">
                            Edit
                        </a>
                        <button @click="deleteAssignment(assignment)" class="p-1.5 text-surface-400 hover:text-danger-600 rounded-lg hover:bg-danger-50">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <ClipboardList class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500 mb-4">No assignments yet.</p>
            <a :href="`/facilitator/cohorts/${cohort.id}/assignments/create`" class="text-primary-600 font-medium text-sm hover:underline">
                Create your first assignment
            </a>
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, FileText, Clock, Award, Trash2, ClipboardList } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

defineProps({
    cohort: { type: Object, required: true },
    assignments: { type: Array, default: () => [] },
});

function deleteAssignment(assignment) {
    if (!confirm(`Delete "${assignment.title}"?`)) return;
    router.delete(`/facilitator/cohorts/${assignment.cohort_id}/assignments/${assignment.id}`);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

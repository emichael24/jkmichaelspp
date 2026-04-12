<template>
    <Head :title="`Edit: ${assignment.title}`" />

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a :href="`/facilitator/cohorts/${cohort.id}/assignments`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <h1 class="text-xl font-display font-bold text-surface-900">Edit Assignment</h1>
        </div>

        <form @submit.prevent="submit" class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                <input v-model="form.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                <textarea v-model="form.description" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
                <p v-if="form.errors.description" class="text-xs text-danger-600 mt-1">{{ form.errors.description }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Instructions</label>
                <textarea v-model="form.instructions" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Max Score</label>
                    <input v-model.number="form.max_score" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Due Date</label>
                    <input v-model="form.due_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Max Resubmissions</label>
                    <input v-model.number="form.max_resubmissions" type="number" min="0" max="10" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input v-model="form.allow_late" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                    <span class="text-sm text-surface-700">Allow late submissions</span>
                </label>
                <label class="flex items-center gap-2">
                    <input v-model="form.is_published" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                    <span class="text-sm text-surface-700">Published</span>
                </label>
            </div>

            <!-- Rubric -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-surface-700">Rubric Criteria</label>
                    <button type="button" @click="addCriterion" class="text-xs text-primary-600 font-medium hover:underline">+ Add criterion</button>
                </div>
                <div v-for="(c, i) in form.rubric" :key="i" class="flex items-start gap-2 mb-2">
                    <input v-model="c.criterion" type="text" class="flex-1 px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Criterion name" />
                    <input v-model.number="c.max_score" type="number" min="1" class="w-20 px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Pts" />
                    <button type="button" @click="form.rubric.splice(i, 1)" class="p-2 text-surface-400 hover:text-danger-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-surface-100">
                <a :href="`/facilitator/cohorts/${cohort.id}/assignments`" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</a>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, X } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    assignment: { type: Object, required: true },
});

const form = useForm({
    title: props.assignment.title,
    description: props.assignment.description,
    instructions: props.assignment.instructions || '',
    max_score: props.assignment.max_score,
    due_date: props.assignment.due_date ? props.assignment.due_date.slice(0, 10) : '',
    allow_late: props.assignment.allow_late,
    max_resubmissions: props.assignment.max_resubmissions,
    is_published: props.assignment.is_published,
    rubric: props.assignment.rubric || [],
});

function addCriterion() {
    form.rubric.push({ criterion: '', description: '', max_score: 10 });
}

function submit() {
    form.put(`/facilitator/cohorts/${props.cohort.id}/assignments/${props.assignment.id}`);
}
</script>

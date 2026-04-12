<template>
    <Head title="Create Assignment" />

    <div class="max-w-2xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <a :href="`/facilitator/cohorts/${cohort.id}/assignments`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <div>
                <h1 class="text-xl font-display font-bold text-surface-900">Create Assignment</h1>
                <p class="text-sm text-surface-500">{{ cohort.name }} · {{ cohort.course?.title }}</p>
            </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                <input v-model="form.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="e.g., Risk Register Analysis" />
                <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                <textarea v-model="form.description" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="What learners will do..."></textarea>
                <p v-if="form.errors.description" class="text-xs text-danger-600 mt-1">{{ form.errors.description }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Instructions</label>
                <textarea v-model="form.instructions" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Step-by-step instructions (optional)"></textarea>
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

            <div class="flex items-center gap-2">
                <input v-model="form.allow_late" type="checkbox" id="allow_late" class="rounded border-surface-300 text-primary-600" />
                <label for="allow_late" class="text-sm text-surface-700">Allow late submissions</label>
            </div>

            <!-- Rubric Builder -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-surface-700">Rubric Criteria (optional)</label>
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
                    Create Assignment
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
});

const form = useForm({
    title: '',
    description: '',
    instructions: '',
    max_score: 100,
    due_date: '',
    allow_late: false,
    max_resubmissions: 1,
    rubric: [],
});

function addCriterion() {
    form.rubric.push({ criterion: '', description: '', max_score: 10 });
}

function submit() {
    form.post(`/facilitator/cohorts/${props.cohort.id}/assignments`);
}
</script>

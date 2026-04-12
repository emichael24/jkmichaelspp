<template>
    <Head :title="`Edit ${cohort.name}`" />

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <h1 class="text-2xl font-display font-bold text-surface-900">Edit Cohort</h1>
        </div>

        <form @submit.prevent="submit" class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Cohort Name</label>
                <input v-model="form.name" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                <p v-if="form.errors.name" class="text-xs text-danger-600 mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Start Date</label>
                    <input v-model="form.start_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p v-if="form.errors.start_date" class="text-xs text-danger-600 mt-1">{{ form.errors.start_date }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">End Date</label>
                    <input v-model="form.end_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p v-if="form.errors.end_date" class="text-xs text-danger-600 mt-1">{{ form.errors.end_date }}</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Max Learners</label>
                <input v-model="form.max_learners" type="number" min="1" max="500" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Status</label>
                <select v-model="form.status" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-surface-100">
                <a :href="`/facilitator/cohorts/${cohort.id}`" class="px-4 py-2 text-sm font-medium text-surface-700 hover:bg-surface-100 rounded-lg transition-colors">Cancel</a>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors disabled:opacity-50">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    courses: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.cohort.name,
    description: props.cohort.description || '',
    start_date: props.cohort.start_date?.split('T')[0] || '',
    end_date: props.cohort.end_date?.split('T')[0] || '',
    max_learners: props.cohort.max_learners,
    status: props.cohort.status,
});

function submit() {
    form.put(`/facilitator/cohorts/${props.cohort.id}`);
}
</script>

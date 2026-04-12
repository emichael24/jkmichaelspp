<template>
    <Head title="Create Internship Track" />

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="/admin/internships" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <h1 class="text-xl font-display font-bold text-surface-900">Create Internship Track</h1>
        </div>

        <form @submit.prevent="submit" class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                <input v-model="form.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="e.g., Business Analysis Internship" />
                <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                <textarea v-model="form.description" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Overview of the internship track..."></textarea>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Duration (weeks)</label>
                    <input v-model.number="form.duration_weeks" type="number" min="1" max="52" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Price</label>
                    <input v-model.number="form.price" type="number" min="0" step="0.01" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Max Participants</label>
                    <input v-model.number="form.max_participants" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Expectations</label>
                <textarea v-model="form.expectations" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="What is expected of participants..."></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Skills Covered (comma-separated)</label>
                <input v-model="skillsInput" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Stakeholder Analysis, Requirements Gathering, Data Modeling" />
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Tools Required (comma-separated)</label>
                <input v-model="toolsInput" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="MS Excel, Jira, Confluence" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-surface-100">
                <a href="/admin/internships" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</a>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                    Create Track
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const skillsInput = ref('');
const toolsInput = ref('');

const form = useForm({
    title: '',
    description: '',
    duration_weeks: 12,
    price: 0,
    max_participants: 20,
    expectations: '',
    skills_covered: [],
    tools_required: [],
});

function submit() {
    form.skills_covered = skillsInput.value.split(',').map(s => s.trim()).filter(Boolean);
    form.tools_required = toolsInput.value.split(',').map(s => s.trim()).filter(Boolean);
    form.post('/admin/internships');
}
</script>

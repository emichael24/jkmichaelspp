<template>
    <Head title="Create Simulation" />

    <div class="max-w-3xl mx-auto space-y-6">
        <a href="/admin/simulations" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to simulations
        </a>

        <h1 class="text-2xl font-display font-bold text-surface-900">Create Simulation</h1>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Basic Information">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Title *</label>
                        <input v-model="form.title" type="text" required
                               class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                        <p v-if="form.errors.title" class="text-error-600 text-xs mt-1">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="3"
                                  class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Context Narrative *</label>
                        <textarea v-model="form.context_narrative" rows="5" required
                                  class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500"
                                  placeholder="Describe the scenario context that sets the stage for the learner..." />
                        <p v-if="form.errors.context_narrative" class="text-error-600 text-xs mt-1">{{ form.errors.context_narrative }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Type *</label>
                            <select v-model="form.type" required class="w-full rounded-lg border-surface-300 text-sm">
                                <option value="branching_scenario">Branching Scenario</option>
                                <option value="role_play">Role Play</option>
                                <option value="case_study_lab">Case Study Lab</option>
                                <option value="decision_room">Decision Room</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Difficulty *</label>
                            <select v-model="form.difficulty" required class="w-full rounded-lg border-surface-300 text-sm">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Course</label>
                            <select v-model="form.course_id" class="w-full rounded-lg border-surface-300 text-sm">
                                <option :value="null">— None —</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Est. Minutes</label>
                            <input v-model.number="form.estimated_minutes" type="number" min="1"
                                   class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">XP Reward</label>
                        <input v-model.number="form.xp_reward" type="number" min="0"
                               class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                    </div>
                </div>
            </Card>

            <div class="flex gap-3 justify-end">
                <a href="/admin/simulations"
                   class="px-4 py-2 rounded-lg text-sm font-medium text-surface-700 bg-surface-100 hover:bg-surface-200 transition-colors">
                    Cancel
                </a>
                <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 transition-colors disabled:opacity-50">
                    {{ form.processing ? 'Creating...' : 'Create Simulation' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    courses: { type: Array, default: () => [] },
});

const form = useForm({
    title: '',
    description: '',
    context_narrative: '',
    type: 'branching_scenario',
    difficulty: 'medium',
    course_id: null,
    estimated_minutes: 15,
    xp_reward: 50,
});

function submit() {
    form.post('/admin/simulations');
}
</script>

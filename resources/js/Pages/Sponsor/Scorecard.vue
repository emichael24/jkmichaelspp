<template>
    <Head :title="`Scorecard: ${enrollment.user?.first_name}`" />

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a :href="`/sponsor/interns/${enrollment.id}/progress`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <div>
                <h1 class="text-xl font-display font-bold text-surface-900">Submit Scorecard</h1>
                <p class="text-sm text-surface-500">{{ enrollment.user?.first_name }} {{ enrollment.user?.last_name }} · {{ enrollment.track?.title }}</p>
            </div>
        </div>

        <!-- Existing Scorecards -->
        <div v-if="existingScorecards.length" class="bg-white rounded-xl border border-surface-200/60 p-5">
            <h2 class="text-sm font-semibold text-surface-700 mb-3">Past Scorecards</h2>
            <div class="flex flex-wrap gap-2">
                <div v-for="sc in existingScorecards" :key="sc.id" class="px-3 py-1.5 bg-surface-50 rounded-lg text-xs">
                    <span class="font-medium capitalize">{{ sc.period_type }} #{{ sc.period_number }}</span>
                    <span class="ml-2 font-bold text-accent-600">{{ sc.overall_score }}/5</span>
                </div>
            </div>
        </div>

        <!-- Scorecard Form -->
        <form @submit.prevent="submit" class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-5"><div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800"><p v-for="error in form.errors" :key="error">{{ error }}</p></div><p class="text-sm text-surface-500">Score all five categories from 1 to 5. The saved overall score is their average. Each period can be submitted once.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Period Type</label>
                    <select v-model="form.period_type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Period Number</label>
                    <input v-model.number="form.period_number" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                </div>
            </div>

            <!-- Score Categories -->
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-2">Evaluation criteria (1–5)</label>
                <div class="space-y-3">
                    <div v-for="cat in categories" :key="cat.key" class="flex items-center gap-3">
                        <label :for="`rating-${cat.key}`" class="text-sm text-surface-600 w-40">{{ cat.label }}</label>
                        <input :id="`rating-${cat.key}`" v-model.number="form.scores[cat.key]" type="number" min="1" max="5" step="1" required
                               class="w-24 px-3 py-2 border border-surface-200 rounded-lg text-sm text-center" />
                        <div class="flex-1">
                            <div class="w-full bg-surface-100 rounded-full h-2">
                                <div class="bg-accent-500 h-2 rounded-full transition-all" :style="{ width: ((form.scores[cat.key] || 0) / 5 * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-surface-700">Calculated overall score</p><output class="mt-2 block text-2xl font-semibold text-primary-700" aria-live="polite">{{ average }} / 5</output>
            </div>

            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Comments</label>
                <textarea v-model="form.comments" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"
                          placeholder="General observations, strengths, areas for improvement..."></textarea>
            </div>

            <!-- Improvement Actions -->
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Improvement Actions</label>
                <div class="space-y-2">
                    <div v-for="(action, idx) in form.improvement_actions" :key="idx" class="flex gap-2">
                        <input v-model="form.improvement_actions[idx]" type="text" class="flex-1 px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="Action item..." />
                        <button type="button" @click="form.improvement_actions.splice(idx, 1)" class="px-2 text-danger-500 hover:text-danger-700">✕</button>
                    </div>
                </div>
                <button type="button" @click="form.improvement_actions.push('')" class="mt-2 text-xs text-primary-600 font-medium hover:underline">+ Add Action</button>
            </div>

            <div class="flex items-center gap-2">
                <input v-model="form.ready_for_next" type="checkbox" id="ready_next" class="rounded border-surface-300 text-success-600" />
                <label for="ready_next" class="text-sm text-surface-700">Ready for next milestone/phase</label>
            </div>

            <div class="flex justify-end pt-4 border-t border-surface-100">
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                    Submit Scorecard
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

const props = defineProps({
    enrollment: { type: Object, required: true },
    existingScorecards: { type: Array, default: () => [] },
});

const categories = [
    { key: 'quality', label: 'Work Quality' },
    { key: 'timeliness', label: 'Timeliness' },
    { key: 'communication', label: 'Communication' },
    { key: 'initiative', label: 'Initiative' },
    { key: 'technical_skill', label: 'Technical skill' },
];

const nextPeriod = (props.existingScorecards?.length || 0) + 1;

const form = useForm({
    enrollment_id: props.enrollment.id,
    period_type: 'weekly',
    period_number: nextPeriod,
    scores: { quality: '', timeliness: '', communication: '', initiative: '', technical_skill: '' },
    overall_score: 0,
    comments: '',
    improvement_actions: [],
    ready_for_next: false,
});

const average = computed(() => Object.values(form.scores).every(v => v !== '') ? (Object.values(form.scores).reduce((sum, v) => sum + Number(v), 0) / 5).toFixed(1) : '—');
function submit() {
    form.improvement_actions = form.improvement_actions.filter(a => a.trim());
    form.post('/sponsor/scorecards');
}
</script>

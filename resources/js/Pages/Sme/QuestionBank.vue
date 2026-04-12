<template>
    <Head title="Question Bank" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Question Bank</h1>
            <p class="text-surface-500 mt-1 text-sm">Manage questions with quality analytics and filters.</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            <StatCard label="Total" :value="stats.total" :icon="Database" color="primary" />
            <StatCard label="Published" :value="stats.published" :icon="CheckCircle" color="success" />
            <StatCard label="Draft" :value="stats.draft" :icon="FileEdit" color="warning" />
            <StatCard label="Flagged" :value="stats.flagged" :icon="Flag" color="error" />
            <StatCard label="High Exposure" :value="stats.high_exposure" :icon="Eye" color="accent" />
            <StatCard label="Low Discrim." :value="stats.low_discrimination" :icon="TrendingDown" color="warning" />
        </div>

        <!-- Filters -->
        <div class="flex gap-3 flex-wrap">
            <input v-model="form.search" type="text" placeholder="Search questions..."
                   @input="debouncedFilter"
                   class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500 w-64" />
            <select v-model="form.course_id" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Courses</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <select v-model="form.domain" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Domains</option>
                <option v-for="d in domains" :key="d" :value="d">{{ d }}</option>
            </select>
            <select v-model="form.difficulty" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Difficulty</option>
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
                <option value="expert">Expert</option>
            </select>
            <select v-model="form.sort" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">Latest</option>
                <option value="exposure">Most Exposed</option>
                <option value="difficulty">Hardest (by correct rate)</option>
                <option value="discrimination">Best Discrimination</option>
            </select>
        </div>

        <!-- Questions list -->
        <div class="space-y-3">
            <a v-for="q in questions.data" :key="q.id"
               :href="`/sme/questions/${q.id}/analytics`"
               class="block bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-surface-900 leading-snug line-clamp-2">{{ q.stem }}</p>
                        <div class="flex items-center gap-3 mt-2 text-xs text-surface-400">
                            <span>{{ q.course?.title }}</span>
                            <span v-if="q.domain" class="px-1.5 py-0.5 bg-surface-100 rounded text-surface-600">{{ q.domain }}</span>
                            <span :class="difficultyClass(q.difficulty)">{{ q.difficulty }}</span>
                            <span>{{ q.responses_count || 0 }} responses</span>
                        </div>
                    </div>
                    <div class="ml-4 flex flex-col items-end gap-1 shrink-0 text-xs text-surface-400">
                        <span v-if="q.correct_rate" :class="['font-semibold', q.correct_rate >= 0.7 ? 'text-success-600' : q.correct_rate >= 0.4 ? 'text-warning-600' : 'text-error-600']">
                            {{ (q.correct_rate * 100).toFixed(0) }}% correct
                        </span>
                        <span v-if="q.discrimination_index">DI: {{ q.discrimination_index }}</span>
                        <span>Exp: {{ q.exposure_count || 0 }}</span>
                    </div>
                </div>
            </a>
        </div>

        <EmptyState v-if="!questions.data?.length" title="No Questions Found" description="No questions match your filters." />

        <!-- Pagination -->
        <div v-if="questions.links && questions.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in questions.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Database, CheckCircle, FileEdit, Flag, Eye, TrendingDown } from 'lucide-vue-next';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    questions: { type: Object, default: () => ({ data: [] }) },
    courses: { type: Array, default: () => [] },
    domains: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ ...props.filters });

let debounceTimer = null;
function debouncedFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}

function applyFilters() {
    const params = {};
    Object.entries(form).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/sme/questions', params, { preserveState: true });
}

function difficultyClass(d) {
    const map = {
        easy: 'text-success-600 bg-success-50 px-1.5 py-0.5 rounded',
        medium: 'text-warning-600 bg-warning-50 px-1.5 py-0.5 rounded',
        hard: 'text-error-600 bg-error-50 px-1.5 py-0.5 rounded',
        expert: 'text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded',
    };
    return map[d] || '';
}
</script>

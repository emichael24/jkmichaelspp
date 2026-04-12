<template>
    <Head :title="`Edit: ${quiz.title}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/admin/quizzes" class="p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-display font-bold text-surface-900 truncate">{{ quiz.title }}</h1>
                <p class="text-surface-500 mt-0.5 text-sm">{{ quiz.course?.title }} · {{ quiz.attempts_count }} attempt(s)</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="togglePublish"
                    :class="[
                        'px-4 py-2 rounded-xl text-sm font-semibold transition-colors',
                        quiz.is_published
                            ? 'bg-warning-100 text-warning-700 hover:bg-warning-200'
                            : 'bg-success-100 text-success-700 hover:bg-success-200',
                    ]"
                >
                    {{ quiz.is_published ? 'Unpublish' : 'Publish' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Settings -->
            <div class="lg:col-span-1">
                <form @submit.prevent="updateSettings" class="bg-white rounded-2xl border border-surface-200 p-6 space-y-4">
                    <h2 class="text-base font-semibold text-surface-900">Settings</h2>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Title *</label>
                        <input v-model="settingsForm.title" type="text" class="w-full rounded-xl border-surface-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Type</label>
                        <select v-model="settingsForm.type" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="topic_quiz">Topic Quiz</option>
                            <option value="module_test">Module Test</option>
                            <option value="practice_exam">Practice Exam</option>
                            <option value="diagnostic">Diagnostic</option>
                            <option value="certification_prep">Certification Prep</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-surface-600 mb-1">Questions</label>
                            <input v-model.number="settingsForm.question_count" type="number" min="1" class="w-full rounded-lg border-surface-200 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-surface-600 mb-1">Time (min)</label>
                            <input v-model.number="settingsForm.time_limit_minutes" type="number" min="1" class="w-full rounded-lg border-surface-200 text-sm" placeholder="None" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-surface-600 mb-1">Pass %</label>
                            <input v-model.number="settingsForm.passing_score_pct" type="number" min="1" max="100" class="w-full rounded-lg border-surface-200 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-surface-600 mb-1">Max Attempts</label>
                            <input v-model.number="settingsForm.max_attempts" type="number" min="1" class="w-full rounded-lg border-surface-200 text-sm" placeholder="∞" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="settingsForm.is_randomized" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                        <span class="text-sm text-surface-700">Randomize</span>
                    </label>
                    <Button type="submit" variant="primary" size="sm" :disabled="settingsForm.processing" class="w-full">
                        Save Settings
                    </Button>
                </form>
            </div>

            <!-- Question assignment area -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Assigned questions -->
                <div class="bg-white rounded-2xl border border-surface-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-surface-900">
                            Assigned Questions
                            <span class="text-surface-400 font-normal">({{ quiz.questions?.length || 0 }})</span>
                        </h2>
                    </div>

                    <div v-if="quiz.questions && quiz.questions.length > 0" class="space-y-2">
                        <div
                            v-for="(q, idx) in quiz.questions"
                            :key="q.id"
                            class="flex items-center gap-3 p-3 rounded-xl border border-surface-100 bg-surface-50/30 hover:bg-surface-50 transition-colors group"
                        >
                            <span class="w-7 h-7 rounded-lg bg-surface-200 flex items-center justify-center text-xs font-bold text-surface-600 shrink-0">
                                {{ idx + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-surface-800 truncate">{{ q.stem }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] text-primary-600 font-medium">{{ q.type }}</span>
                                    <span class="text-[11px] text-surface-400">·</span>
                                    <span class="text-[11px] text-surface-500">{{ q.domain || 'No domain' }}</span>
                                    <span class="text-[11px] text-surface-400">·</span>
                                    <span class="text-[11px] text-surface-500 capitalize">{{ q.difficulty }}</span>
                                </div>
                            </div>
                            <button
                                @click="removeQuestion(q.id)"
                                class="opacity-0 group-hover:opacity-100 p-1.5 rounded-lg hover:bg-danger-100 text-surface-400 hover:text-danger-600 transition-all"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-surface-400">
                        <p class="text-sm">No questions assigned yet</p>
                        <p class="text-xs mt-1">Add questions from the pool below</p>
                    </div>
                </div>

                <!-- Available questions pool -->
                <div class="bg-white rounded-2xl border border-surface-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-surface-900">
                            Available Questions
                            <span class="text-surface-400 font-normal">({{ availableQuestions?.length || 0 }})</span>
                        </h2>
                        <button
                            v-if="selectedPoolIds.length > 0"
                            @click="addSelected"
                            class="px-4 py-2 rounded-xl bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700 transition-colors"
                        >
                            Add {{ selectedPoolIds.length }} selected
                        </button>
                    </div>

                    <!-- Filter bar -->
                    <div class="flex gap-2 mb-4">
                        <input
                            v-model="poolSearch"
                            type="text"
                            class="flex-1 rounded-xl border-surface-200 text-sm"
                            placeholder="Filter questions..."
                        />
                        <select v-model="poolDomain" class="rounded-xl border-surface-200 text-sm">
                            <option value="">All Domains</option>
                            <option v-for="d in uniqueDomains" :key="d" :value="d">{{ d }}</option>
                        </select>
                        <select v-model="poolDifficulty" class="rounded-xl border-surface-200 text-sm">
                            <option value="">All Diff.</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>

                    <div v-if="filteredPool.length > 0" class="space-y-2 max-h-96 overflow-y-auto">
                        <div
                            v-for="q in filteredPool"
                            :key="q.id"
                            @click="togglePoolSelection(q.id)"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors',
                                selectedPoolIds.includes(q.id)
                                    ? 'border-primary-300 bg-primary-50/50'
                                    : 'border-surface-100 hover:bg-surface-50',
                            ]"
                        >
                            <div :class="[
                                'w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0',
                                selectedPoolIds.includes(q.id)
                                    ? 'border-primary-500 bg-primary-500'
                                    : 'border-surface-300',
                            ]">
                                <Check v-if="selectedPoolIds.includes(q.id)" class="w-3 h-3 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-surface-800 truncate">{{ q.stem }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] text-primary-600 font-medium">{{ q.type }}</span>
                                    <span class="text-[11px] text-surface-400">·</span>
                                    <span class="text-[11px] text-surface-500">{{ q.domain || 'No domain' }}</span>
                                    <span class="text-[11px] text-surface-400">·</span>
                                    <span class="text-[11px] text-surface-500 capitalize">{{ q.difficulty }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-6 text-surface-400 text-sm">
                        {{ availableQuestions?.length > 0 ? 'No questions match filter.' : 'All published questions from this course are assigned.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ArrowLeft, X, Check } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    quiz: Object,
    courses: Array,
    availableQuestions: Array,
});

// Settings form
const settingsForm = useForm({
    title: props.quiz.title,
    course_id: props.quiz.course_id,
    module_id: props.quiz.module_id || '',
    lesson_id: props.quiz.lesson_id || '',
    type: props.quiz.type,
    question_count: props.quiz.question_count,
    time_limit_minutes: props.quiz.time_limit_minutes,
    passing_score_pct: props.quiz.passing_score_pct,
    is_randomized: props.quiz.is_randomized,
    max_attempts: props.quiz.max_attempts,
    is_published: props.quiz.is_published,
});

function updateSettings() {
    settingsForm.put(`/admin/quizzes/${props.quiz.id}`);
}

function togglePublish() {
    const url = props.quiz.is_published
        ? `/admin/quizzes/${props.quiz.id}/unpublish`
        : `/admin/quizzes/${props.quiz.id}/publish`;
    router.put(url);
}

// Pool selection
const selectedPoolIds = ref([]);
const poolSearch = ref('');
const poolDomain = ref('');
const poolDifficulty = ref('');

const uniqueDomains = computed(() => {
    const domains = (props.availableQuestions || []).map(q => q.domain).filter(Boolean);
    return [...new Set(domains)].sort();
});

const filteredPool = computed(() => {
    let pool = props.availableQuestions || [];
    if (poolSearch.value) {
        const s = poolSearch.value.toLowerCase();
        pool = pool.filter(q => q.stem.toLowerCase().includes(s));
    }
    if (poolDomain.value) pool = pool.filter(q => q.domain === poolDomain.value);
    if (poolDifficulty.value) pool = pool.filter(q => q.difficulty === poolDifficulty.value);
    return pool;
});

function togglePoolSelection(id) {
    const idx = selectedPoolIds.value.indexOf(id);
    if (idx >= 0) selectedPoolIds.value.splice(idx, 1);
    else selectedPoolIds.value.push(id);
}

function addSelected() {
    router.post(`/admin/quizzes/${props.quiz.id}/questions`, {
        question_ids: selectedPoolIds.value,
    }, {
        onSuccess: () => { selectedPoolIds.value = []; },
    });
}

function removeQuestion(questionId) {
    router.delete(`/admin/quizzes/${props.quiz.id}/questions/${questionId}`);
}
</script>

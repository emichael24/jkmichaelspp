<template>
    <Head :title="isEdit ? 'Edit Question' : 'Create Question'" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/admin/questions" class="p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">
                    {{ isEdit ? 'Edit Question' : 'Create Question' }}
                </h1>
                <p class="text-surface-500 mt-0.5 text-sm">Fill in the question details and options below</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Question Stem -->
                    <div class="bg-white rounded-2xl border border-surface-200 p-6">
                        <h2 class="text-base font-semibold text-surface-900 mb-4">Question Stem</h2>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Stem *</label>
                            <textarea
                                v-model="form.stem"
                                rows="4"
                                class="w-full rounded-xl border-surface-200 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400"
                                placeholder="Enter the question text..."
                            />
                            <p v-if="form.errors.stem" class="text-danger-600 text-xs mt-1">{{ form.errors.stem }}</p>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="bg-white rounded-2xl border border-surface-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-surface-900">Answer Options</h2>
                            <button
                                type="button"
                                @click="addOption"
                                class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1"
                            >
                                <Plus class="w-4 h-4" />
                                Add Option
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(opt, idx) in form.options"
                                :key="opt.id"
                                class="flex items-start gap-3 p-3 rounded-xl border border-surface-100 bg-surface-50/50"
                            >
                                <!-- Correct answer toggle -->
                                <button
                                    type="button"
                                    @click="toggleCorrect(opt.id)"
                                    :class="[
                                        'mt-1.5 w-7 h-7 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors',
                                        form.correct_answer_ids.includes(opt.id)
                                            ? 'border-success-500 bg-success-500 text-white'
                                            : 'border-surface-300 text-surface-300 hover:border-success-300',
                                    ]"
                                    :title="`Mark option ${String.fromCharCode(65 + idx)} as correct`"
                                >
                                    <Check class="w-3.5 h-3.5" />
                                </button>

                                <!-- Option text -->
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold text-surface-500 mb-1">
                                        Option {{ String.fromCharCode(65 + idx) }}
                                    </label>
                                    <input
                                        v-model="opt.text"
                                        type="text"
                                        class="w-full rounded-lg border-surface-200 text-sm focus:ring-2 focus:ring-primary-200"
                                        :placeholder="`Enter option ${String.fromCharCode(65 + idx)}...`"
                                    />
                                </div>

                                <!-- Remove -->
                                <button
                                    v-if="form.options.length > 2"
                                    type="button"
                                    @click="removeOption(idx)"
                                    class="mt-6 p-1 rounded hover:bg-surface-200 text-surface-400"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.correct_answer_ids" class="text-danger-600 text-xs mt-2">{{ form.errors.correct_answer_ids }}</p>
                    </div>

                    <!-- Explanations -->
                    <div class="bg-white rounded-2xl border border-surface-200 p-6">
                        <h2 class="text-base font-semibold text-surface-900 mb-4">Explanations</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Correct Answer Explanation</label>
                                <textarea
                                    v-model="form.explanation_correct"
                                    rows="3"
                                    class="w-full rounded-xl border-surface-200 text-sm"
                                    placeholder="Explain why the correct answer is right..."
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Exam Tip</label>
                                <textarea
                                    v-model="form.exam_tip"
                                    rows="2"
                                    class="w-full rounded-xl border-surface-200 text-sm"
                                    placeholder="Exam tip for students..."
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar settings -->
                <div class="space-y-6">
                    <!-- Classification -->
                    <div class="bg-white rounded-2xl border border-surface-200 p-6">
                        <h2 class="text-base font-semibold text-surface-900 mb-4">Classification</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Course *</label>
                                <select v-model="form.course_id" class="w-full rounded-xl border-surface-200 text-sm" @change="onCourseChange">
                                    <option value="">Select course</option>
                                    <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Domain</label>
                                <input v-model="form.domain" type="text" class="w-full rounded-xl border-surface-200 text-sm" placeholder="e.g., Risk Management" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Subtopic</label>
                                <input v-model="form.subtopic" type="text" class="w-full rounded-xl border-surface-200 text-sm" placeholder="e.g., Risk Identification" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Linked Lesson</label>
                                <select v-model="form.linked_lesson_id" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="">None</option>
                                    <option v-for="l in availableLessons" :key="l.id" :value="l.id">{{ l.title }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Properties -->
                    <div class="bg-white rounded-2xl border border-surface-200 p-6">
                        <h2 class="text-base font-semibold text-surface-900 mb-4">Properties</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Type *</label>
                                <select v-model="form.type" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="multiple_choice">Single Answer (Multiple Choice)</option>
                                    <option value="multiple_select">Multiple Select</option>
                                    <option value="true_false">True / False</option>
                                    <option value="scenario">Scenario</option>
                                    <option value="situational_judgment">Situational Judgment</option>
                                    <option value="formula">Formula</option>
                                    <option value="fill_blank">Fill in the Blank</option>
                                    <option value="ordering">Ordering</option>
                                    <option value="matching">Matching</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Difficulty *</label>
                                <select v-model="form.difficulty" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Bloom's Level</label>
                                <select v-model="form.bloom_level" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="">None</option>
                                    <option value="remember">Remember</option>
                                    <option value="understand">Understand</option>
                                    <option value="apply">Apply</option>
                                    <option value="analyze">Analyze</option>
                                    <option value="evaluate">Evaluate</option>
                                    <option value="create">Create</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Time Est. (seconds)</label>
                                <input v-model.number="form.time_estimate_seconds" type="number" min="0" class="w-full rounded-xl border-surface-200 text-sm" placeholder="60" />
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white rounded-2xl border border-surface-200 p-6">
                        <h2 class="text-base font-semibold text-surface-900 mb-4">Publishing</h2>
                        <select v-model="form.status" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="draft">Draft</option>
                            <option value="review">In Review</option>
                            <option value="approved">Approved</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <!-- Submit -->
                    <div class="flex gap-3">
                        <Button type="submit" variant="primary" size="md" class="flex-1" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : isEdit ? 'Update Question' : 'Create Question' }}
                        </Button>
                        <Link href="/admin/questions">
                            <Button variant="secondary" size="md">Cancel</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Check, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    question: { type: Object, default: null },
    courses: Array,
    lessons: { type: Array, default: () => [] },
    preselectedCourse: { type: Number, default: null },
});

const isEdit = !!props.question;
const availableLessons = ref(props.lessons || []);

function generateId() {
    return 'opt_' + Math.random().toString(36).substring(2, 10);
}

const defaultOptions = [
    { id: generateId(), text: '', media_url: null },
    { id: generateId(), text: '', media_url: null },
    { id: generateId(), text: '', media_url: null },
    { id: generateId(), text: '', media_url: null },
];

const form = useForm({
    course_id: props.question?.course_id || props.preselectedCourse || '',
    domain: props.question?.domain || '',
    subtopic: props.question?.subtopic || '',
    type: props.question?.type || 'multiple_choice',
    difficulty: props.question?.difficulty || 'medium',
    bloom_level: props.question?.bloom_level || '',
    stem: props.question?.stem || '',
    stem_media: props.question?.stem_media || null,
    options: props.question?.options || JSON.parse(JSON.stringify(defaultOptions)),
    correct_answer_ids: props.question?.correct_answer_ids || [],
    explanation_correct: props.question?.explanation_correct || '',
    explanation_incorrect: props.question?.explanation_incorrect || null,
    exam_tip: props.question?.exam_tip || '',
    linked_lesson_id: props.question?.linked_lesson_id || '',
    time_estimate_seconds: props.question?.time_estimate_seconds || 60,
    tags: props.question?.tags || [],
    status: props.question?.status || 'draft',
});

function addOption() {
    form.options.push({ id: generateId(), text: '', media_url: null });
}

function removeOption(idx) {
    const removed = form.options.splice(idx, 1);
    form.correct_answer_ids = form.correct_answer_ids.filter(id => id !== removed[0].id);
}

function toggleCorrect(optId) {
    if (form.type !== 'multiple_select') {
        form.correct_answer_ids = [optId];
    } else {
        if (form.correct_answer_ids.includes(optId)) {
            form.correct_answer_ids = form.correct_answer_ids.filter(id => id !== optId);
        } else {
            form.correct_answer_ids.push(optId);
        }
    }
}

async function onCourseChange() {
    if (!form.course_id) {
        availableLessons.value = [];
        return;
    }

    try {
        const resp = await fetch(`/admin/courses/${form.course_id}/lessons-json`);
        availableLessons.value = await resp.json();
    } catch {
        availableLessons.value = [];
    }
}

function submit() {
    if (isEdit) {
        form.put(`/admin/questions/${props.question.id}`);
    } else {
        form.post('/admin/questions');
    }
}
</script>

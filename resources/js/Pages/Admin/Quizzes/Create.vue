<template>
    <Head title="Create Quiz" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/admin/quizzes" class="p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Create Quiz</h1>
                <p class="text-surface-500 mt-0.5 text-sm">Set up a new quiz, then add questions</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <div class="bg-white rounded-2xl border border-surface-200 p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Title *</label>
                    <input v-model="form.title" type="text" class="w-full rounded-xl border-surface-200 text-sm" placeholder="e.g., Module 3 Assessment" />
                    <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Course *</label>
                    <select v-model="form.course_id" class="w-full rounded-xl border-surface-200 text-sm" @change="updateModulesAndLessons">
                        <option value="">Select course</option>
                        <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Module (optional)</label>
                        <select v-model="form.module_id" class="w-full rounded-xl border-surface-200 text-sm" @change="updateLessons">
                            <option value="">All modules</option>
                            <option v-for="m in availableModules" :key="m.id" :value="m.id">{{ m.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Lesson (optional)</label>
                        <select v-model="form.lesson_id" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="">All lessons</option>
                            <option v-for="l in availableLessons" :key="l.id" :value="l.id">{{ l.title }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Type *</label>
                    <select v-model="form.type" class="w-full rounded-xl border-surface-200 text-sm">
                        <option value="lesson_check">Lesson Check</option>
                        <option value="module_quiz">Module Quiz</option>
                        <option value="domain_practice">Domain Practice</option>
                        <option value="adaptive">Adaptive</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Questions to show *</label>
                        <input v-model.number="form.question_count" type="number" min="1" max="200" class="w-full rounded-xl border-surface-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Time limit (minutes)</label>
                        <input v-model.number="form.time_limit_minutes" type="number" min="1" class="w-full rounded-xl border-surface-200 text-sm" placeholder="No limit" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Passing score (%) *</label>
                        <input v-model.number="form.passing_score_pct" type="number" min="1" max="100" class="w-full rounded-xl border-surface-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Max attempts</label>
                        <input v-model.number="form.max_attempts" type="number" min="1" class="w-full rounded-xl border-surface-200 text-sm" placeholder="Unlimited" />
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.is_randomized" type="checkbox" class="rounded border-surface-300 text-primary-600 focus:ring-primary-200" />
                        <span class="text-sm text-surface-700">Randomize question order</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.is_published" type="checkbox" class="rounded border-surface-300 text-primary-600 focus:ring-primary-200" />
                        <span class="text-sm text-surface-700">Published</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3">
                <Button type="submit" variant="primary" size="md" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create Quiz' }}
                </Button>
                <Link href="/admin/quizzes">
                    <Button variant="secondary" size="md">Cancel</Button>
                </Link>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    courses: Array,
    preselectedCourse: { type: Number, default: null },
});

const form = useForm({
    title: '',
    course_id: props.preselectedCourse || '',
    module_id: '',
    lesson_id: '',
    type: 'lesson_check',
    question_count: 10,
    time_limit_minutes: null,
    passing_score_pct: 70,
    is_randomized: true,
    max_attempts: null,
    is_published: false,
});

const availableModules = computed(() => {
    if (!form.course_id) return [];
    const course = props.courses.find(c => c.id === form.course_id);
    return course?.modules || [];
});

const availableLessons = ref([]);

function updateModulesAndLessons() {
    form.module_id = '';
    form.lesson_id = '';
    updateLessons();
}

function updateLessons() {
    form.lesson_id = '';
    if (form.module_id) {
        const mod = availableModules.value.find(m => m.id === form.module_id);
        availableLessons.value = mod?.lessons || [];
    } else {
        availableLessons.value = availableModules.value.flatMap(m => m.lessons || []);
    }
}

function submit() {
    form.post('/admin/quizzes');
}
</script>

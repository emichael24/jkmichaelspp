<template>
    <Head :title="course?.title || 'Course'" />

    <div v-if="course" class="max-w-5xl mx-auto space-y-6">
        <!-- Back link -->
        <Link href="/courses" class="inline-flex items-center gap-1.5 text-sm text-surface-500 hover:text-primary-600 transition-colors">
            <ArrowLeft class="w-4 h-4" />
            Back to courses
        </Link>

        <!-- Hero -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="relative h-48 sm:h-64 bg-gradient-to-br from-primary-600 to-primary-800 overflow-hidden">
                <img
                    v-if="course.thumbnail"
                    :src="`/storage/${course.thumbnail}`"
                    :alt="course.title"
                    class="w-full h-full object-cover opacity-30"
                />
                <div class="absolute inset-0 flex items-end">
                    <div class="p-6 sm:p-8 w-full">
                        <Badge v-if="course.category" variant="primary" size="sm" class="mb-3 bg-white/20 text-white border-0">
                            {{ course.category?.name || course.category }}
                        </Badge>
                        <h1 class="text-2xl sm:text-3xl font-display font-bold text-white mb-2">
                            {{ course.title }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-primary-200">
                            <span class="flex items-center gap-1.5">
                                <Layers class="w-4 h-4" />
                                {{ course.modules_count || 0 }} modules · {{ course.lessons_count || 0 }} lessons
                            </span>
                            <span class="flex items-center gap-1.5">
                                <Clock class="w-4 h-4" />
                                {{ course.duration_hours ? course.duration_hours + ' hours' : 'Self-paced' }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <Users class="w-4 h-4" />
                                {{ course.enrollments_count || course.total_enrollments || 0 }} learners
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row gap-6">
                    <div class="flex-1">
                        <p class="text-surface-600 leading-relaxed">{{ course.description }}</p>

                        <!-- What you'll learn -->
                        <div v-if="course.learning_outcomes?.length" class="mt-6">
                            <h3 class="font-semibold text-surface-900 mb-3">What you'll learn</h3>
                            <div class="grid sm:grid-cols-2 gap-2">
                                <div
                                    v-for="(outcome, i) in course.learning_outcomes"
                                    :key="i"
                                    class="flex items-start gap-2.5 p-2"
                                >
                                    <div class="w-5 h-5 rounded-full bg-success-50 flex items-center justify-center shrink-0 mt-0.5">
                                        <Check class="w-3 h-3 text-success-600" />
                                    </div>
                                    <span class="text-sm text-surface-700">{{ outcome }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Prerequisites -->
                        <div v-if="course.prerequisites?.length" class="mt-6">
                            <h3 class="font-semibold text-surface-900 mb-3">Prerequisites</h3>
                            <ul class="space-y-1.5">
                                <li v-for="(prereq, i) in course.prerequisites" :key="i" class="flex items-center gap-2 text-sm text-surface-600">
                                    <div class="w-1.5 h-1.5 rounded-full bg-surface-400 shrink-0" />
                                    {{ prereq }}
                                </li>
                            </ul>
                        </div>

                        <!-- Author -->
                        <div v-if="course.author" class="mt-6 pt-6 border-t border-surface-100">
                            <h3 class="font-semibold text-surface-900 mb-3">Instructor</h3>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-sm">
                                    {{ (course.author.first_name?.[0] || '') + (course.author.last_name?.[0] || '') }}
                                </div>
                                <div>
                                    <p class="font-medium text-surface-800 text-sm">{{ course.author.first_name }} {{ course.author.last_name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enroll card -->
                    <div class="sm:w-72 shrink-0">
                        <div class="bg-surface-50 rounded-xl p-5 border border-surface-200/60 sticky top-24">
                            <div v-if="!isEnrolled" class="text-center">
                                <p v-if="course.price > 0" class="text-3xl font-display font-bold text-surface-900 mb-1">
                                    <span v-if="course.discount_price" class="text-lg line-through text-surface-400 mr-2">₦{{ Number(course.price).toLocaleString() }}</span>
                                    ₦{{ Number(course.discount_price || course.price).toLocaleString() }}
                                </p>
                                <p v-else class="text-lg font-semibold text-success-600 mb-1">Free</p>
                                <Button variant="primary" size="lg" class="w-full mt-3" @click="enroll">
                                    {{ course.price > 0 ? 'Enroll Now' : 'Start Learning' }}
                                </Button>
                            </div>
                            <div v-else class="text-center">
                                <CircularProgress :percentage="enrollment?.progress_pct || 0" :size="80" :stroke-width="6" color="primary" class="mx-auto mb-3" />
                                <p class="text-sm text-surface-500 mb-3">{{ enrollment?.progress_pct || 0 }}% complete</p>
                                <Link :href="nextLessonUrl" class="block">
                                    <Button variant="primary" size="lg" class="w-full" :icon="Play">
                                        Continue Learning
                                    </Button>
                                </Link>
                            </div>

                            <div class="mt-5 pt-5 border-t border-surface-200 space-y-3">
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <BookOpen class="w-4 h-4 text-surface-400" />
                                    <span>{{ course.lessons_count || 0 }} lessons</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <Clock class="w-4 h-4 text-surface-400" />
                                    <span>{{ course.duration_hours ? course.duration_hours + ' hours' : 'Self-paced' }}</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <Award class="w-4 h-4 text-surface-400" />
                                    <span>Certificate on completion</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <span class="text-xs font-medium capitalize px-2 py-0.5 rounded-lg bg-surface-100 text-surface-600">{{ course.level }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Curriculum -->
        <Card title="Curriculum">
            <template #header-actions>
                <span class="text-sm text-surface-400">
                    {{ course.modules_count || 0 }} modules · {{ course.lessons_count || 0 }} lessons
                </span>
            </template>

            <div class="space-y-3">
                <div
                    v-for="(module, mi) in modules"
                    :key="module.id"
                    class="border border-surface-200/60 rounded-xl overflow-hidden"
                >
                    <!-- Module header -->
                    <button
                        class="flex items-center justify-between w-full p-4 text-left hover:bg-surface-50 transition-colors"
                        @click="toggleModule(module.id)"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center text-primary-600 font-semibold text-sm">
                                {{ mi + 1 }}
                            </div>
                            <div>
                                <h4 class="font-semibold text-surface-900 text-sm">{{ module.title }}</h4>
                                <p class="text-xs text-surface-400">{{ module.lessons?.length || 0 }} lessons · {{ module.duration_minutes ? module.duration_minutes + ' min' : '' }}</p>
                            </div>
                        </div>
                        <ChevronDown
                            :class="[
                                'w-5 h-5 text-surface-400 transition-transform',
                                expandedModules[module.id] ? 'rotate-180' : '',
                            ]"
                        />
                    </button>

                    <!-- Lessons list -->
                    <transition name="expand">
                        <div v-if="expandedModules[module.id]" class="border-t border-surface-100 divide-y divide-surface-100">
                            <component
                                :is="isEnrolled || lesson.is_free_preview ? 'a' : 'div'"
                                v-for="(lesson, li) in module.lessons"
                                :key="lesson.id"
                                :href="isEnrolled || lesson.is_free_preview ? `/courses/${course.slug}/lessons/${lesson.id}` : undefined"
                                @click.prevent="isEnrolled || lesson.is_free_preview ? goToLesson(lesson.id) : null"
                                :class="[
                                    'flex items-center gap-3 px-4 py-3 transition-colors',
                                    isEnrolled || lesson.is_free_preview
                                        ? 'hover:bg-primary-50 cursor-pointer'
                                        : 'hover:bg-surface-50 opacity-80',
                                ]"
                            >
                                <div :class="[
                                    'w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-[11px] font-semibold',
                                    lessonProgress[lesson.id]?.completed ? 'bg-success-500 text-white' : 'bg-surface-100 text-surface-400',
                                ]">
                                    <Check v-if="lessonProgress[lesson.id]?.completed" class="w-3.5 h-3.5" />
                                    <span v-else>{{ li + 1 }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-surface-700 truncate">{{ lesson.title }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <component :is="lessonTypeIcon(lesson.content_type)" class="w-4 h-4 text-surface-300" />
                                    <span class="text-xs text-surface-400">{{ lesson.duration_minutes ? lesson.duration_minutes + ' min' : '' }}</span>
                                    <Badge v-if="lesson.is_free_preview" variant="info" size="xs">Preview</Badge>
                                </div>
                            </component>
                        </div>
                    </transition>
                </div>
            </div>
        </Card>

        <!-- Resources -->
        <Card v-if="resources?.length" title="Resources">
            <div class="divide-y divide-surface-100">
                <div v-for="resource in resources" :key="resource.id" class="flex items-center justify-between py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-primary-50 flex items-center justify-center">
                            <FileText class="w-4 h-4 text-primary-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-800">{{ resource.title }}</p>
                            <p class="text-xs text-surface-400 capitalize">{{ resource.type.replace('_', ' ') }}</p>
                        </div>
                    </div>
                    <Button v-if="isEnrolled" variant="ghost" size="sm">
                        <Download class="w-4 h-4" />
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import CircularProgress from '@/Components/UI/CircularProgress.vue';
import {
    ArrowLeft, Layers, Clock, Users, Check, Award, BookOpen,
    ChevronDown, Play, FileText, Headphones, Video, Download,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    course: { type: Object, required: true },
    modules: { type: Array, default: () => [] },
    resources: { type: Array, default: () => [] },
    isEnrolled: { type: Boolean, default: false },
    enrollment: { type: Object, default: null },
    lessonProgress: { type: Object, default: () => ({}) },
});

const expandedModules = reactive({});

const toggleModule = (id) => {
    expandedModules[id] = !expandedModules[id];
};

const lessonTypeIcon = (type) => {
    const map = { video: Video, audio: Headphones, text: FileText, pdf: FileText, slides: FileText };
    return map[type] || Play;
};

const enroll = () => {
    router.post(`/courses/${props.course.id}/enroll`);
};

const goToLesson = (lessonId) => {
    router.visit(`/courses/${props.course.slug}/lessons/${lessonId}`);
};

const nextLessonUrl = (() => {
    // Find the first incomplete lesson
    for (const mod of props.modules) {
        for (const lesson of mod.lessons || []) {
            if (!props.lessonProgress[lesson.id]?.completed) {
                return `/courses/${props.course.slug}/lessons/${lesson.id}`;
            }
        }
    }
    // Fallback to first lesson
    const firstLesson = props.modules?.[0]?.lessons?.[0];
    return firstLesson ? `/courses/${props.course.slug}/lessons/${firstLesson.id}` : `/courses/${props.course.slug}`;
})();
</script>

<style scoped>
.expand-enter-active, .expand-leave-active {
    transition: all 0.3s ease;
    overflow: hidden;
}
.expand-enter-from, .expand-leave-to {
    max-height: 0;
    opacity: 0;
}
.expand-enter-to, .expand-leave-from {
    max-height: 600px;
    opacity: 1;
}
</style>

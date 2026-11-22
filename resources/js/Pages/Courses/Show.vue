<template>
    <Head :title="course?.title || 'Course'" />

    <div v-if="course" class="mx-auto max-w-5xl space-y-6">
        <Link href="/courses" class="inline-flex items-center gap-1.5 text-sm text-surface-500 transition-colors hover:text-primary-600">
            <ArrowLeft class="h-4 w-4" />
            Back to courses
        </Link>

        <div class="overflow-hidden rounded-2xl border border-surface-200/60 bg-white shadow-sm">
            <div class="relative h-48 overflow-hidden bg-gradient-to-br from-primary-600 to-primary-800 sm:h-64">
                <img
                    v-if="course.thumbnail"
                    :src="`/storage/${course.thumbnail}`"
                    :alt="course.title"
                    class="h-full w-full object-cover opacity-30"
                />

                <div class="absolute inset-0 flex items-end">
                    <div class="w-full p-6 sm:p-8">
                        <Badge v-if="course.category" variant="primary" size="sm" class="mb-3 border-0 bg-white/20 text-white">
                            {{ course.category?.name || course.category }}
                        </Badge>

                        <h1 class="mb-2 text-2xl font-bold text-white sm:text-3xl">{{ course.title }}</h1>

                        <div class="flex flex-wrap items-center gap-4 text-sm text-primary-200">
                            <span class="flex items-center gap-1.5">
                                <Layers class="h-4 w-4" />
                                {{ course.modules_count || 0 }} modules - {{ course.lessons_count || 0 }} lessons
                            </span>
                            <span class="flex items-center gap-1.5">
                                <Clock class="h-4 w-4" />
                                {{ course.duration_hours ? course.duration_hours + ' hours' : 'Self-paced' }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <Users class="h-4 w-4" />
                                {{ course.enrollments_count || course.total_enrollments || 0 }} learners
                            </span>
                            <span v-if="ratings.total" class="flex items-center gap-1.5">
                                <Star class="h-4 w-4 fill-current text-warning-300" />
                                {{ ratingAverage.toFixed(1) }} from {{ ratings.total }} ratings
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                <div class="flex flex-col gap-6 sm:flex-row">
                    <div class="flex-1">
                        <SafeHtml :content="course.description || ''" class="text-surface-600" />

                        <div v-if="previewVideo" class="mt-6">
                            <h3 class="mb-3 font-semibold text-surface-900">Course Preview</h3>
                            <div class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-950">
                                <iframe
                                    v-if="previewVideo.type === 'embed'"
                                    :src="previewVideo.url"
                                    class="aspect-video w-full"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen
                                />
                                <video
                                    v-else
                                    :src="previewVideo.url"
                                    class="aspect-video w-full"
                                    controls
                                    playsinline
                                />
                            </div>
                        </div>

                        <div v-if="course.learning_outcomes?.length" class="mt-6">
                            <h3 class="mb-3 font-semibold text-surface-900">What you'll learn</h3>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="(outcome, i) in course.learning_outcomes"
                                    :key="i"
                                    class="flex items-start gap-2.5 p-2"
                                >
                                    <div class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-success-50">
                                        <Check class="h-3 w-3 text-success-600" />
                                    </div>
                                    <span class="text-sm text-surface-700">{{ outcome }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="course.prerequisites?.length" class="mt-6">
                            <h3 class="mb-3 font-semibold text-surface-900">Prerequisites</h3>
                            <ul class="space-y-1.5">
                                <li v-for="(prereq, i) in course.prerequisites" :key="i" class="flex items-center gap-2 text-sm text-surface-600">
                                    <div class="h-1.5 w-1.5 shrink-0 rounded-full bg-surface-400" />
                                    {{ prereq }}
                                </li>
                            </ul>
                        </div>

                        <div v-if="course.author" class="mt-6 border-t border-surface-100 pt-6">
                            <h3 class="mb-3 font-semibold text-surface-900">Instructor</h3>
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
                                    {{ (course.author.first_name?.[0] || '') + (course.author.last_name?.[0] || '') }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-surface-800">{{ course.author.first_name }} {{ course.author.last_name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 sm:w-72">
                        <div class="sticky top-24 rounded-xl border border-surface-200/60 bg-surface-50 p-5">
                            <div v-if="!isEnrolled" class="text-center">
                                <div v-if="hasEnrollment" class="mb-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-left text-sm text-warning-800">
                                    <p class="font-semibold">{{ enrollmentStatus === 'suspended' ? 'Enrollment paused' : 'Course access unavailable' }}</p>
                                    <p class="mt-1 text-xs text-warning-700">You currently cannot access this course.</p>
                                </div>
                                <div v-if="!hasEnrollment || ['active', 'completed'].includes(enrollmentStatus)">
                                    <p v-if="checkoutOffer.price > 0" class="mb-1 text-3xl font-bold text-surface-900">
                                        {{ checkoutOffer.currency }} {{ Number(checkoutOffer.price).toLocaleString() }}
                                    </p>
                                    <p v-else class="mb-1 text-lg font-semibold text-success-600">Free</p>
                                    <Button variant="primary" size="lg" class="mt-3 w-full" :disabled="enrolling || !checkoutOffer.purchasable || prerequisiteCourses.some(c => !c.completed)" @click="enroll">
                                        {{ enrolling ? 'Please wait…' : !checkoutOffer.purchasable ? 'Currently unavailable' : checkoutOffer.price > 0 ? 'Continue to checkout' : 'Start Learning' }}
                                    </Button>
                                    <p v-if="checkoutError" role="alert" class="mt-3 text-sm text-red-700">{{ checkoutError }}</p>
                                </div>
                            </div>

                            <div v-else class="text-center">
                                <CircularProgress :percentage="enrollment?.progress_pct || 0" :size="80" :stroke-width="6" color="primary" class="mx-auto mb-3" />
                                <p class="mb-3 text-sm text-surface-500">{{ enrollment?.progress_pct || 0 }}% complete</p>
                                <p v-if="prerequisiteCourses.some(c => !c.completed)" class="rounded-xl bg-warning-50 p-3 text-sm text-warning-800">Complete the prerequisites below to continue.</p>
                                <Link v-else :href="nextLessonUrl" class="block">
                                    <Button variant="primary" size="lg" class="w-full" :icon="Play">
                                        Continue Learning
                                    </Button>
                                </Link>
                            </div>

                            <div class="mt-5 space-y-3 border-t border-surface-200 pt-5">
                                <div v-if="ratings.total" class="flex items-center gap-3 text-sm text-surface-600">
                                    <Star class="h-4 w-4 fill-warning-500 text-warning-500" />
                                    <span>{{ ratingAverage.toFixed(1) }} average rating</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <BookOpen class="h-4 w-4 text-surface-400" />
                                    <span>{{ course.lessons_count || 0 }} lessons</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <Clock class="h-4 w-4 text-surface-400" />
                                    <span>{{ course.duration_hours ? course.duration_hours + ' hours' : 'Self-paced' }}</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <Award class="h-4 w-4 text-surface-400" />
                                    <span>Certificate on completion</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-surface-600">
                                    <span class="rounded-lg bg-surface-100 px-2 py-0.5 text-xs font-medium capitalize text-surface-600">{{ course.level }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section v-if="prerequisiteCourses.length" class="rounded-2xl border border-surface-200 bg-white p-5" aria-label="Course prerequisites">
            <h2 class="font-semibold text-surface-900">Before you start</h2><p class="mt-1 text-sm text-surface-600">Complete these courses to unlock enrollment, lessons and resources.</p>
            <ul class="mt-3 space-y-2"><li v-for="required in prerequisiteCourses" :key="required.id" class="flex flex-wrap justify-between gap-3 text-sm"><Link v-if="required.url" :href="required.url" class="font-medium text-primary-700 underline underline-offset-4">{{ required.title }}</Link><span v-else>{{ required.title }} · currently unavailable</span><span :class="required.completed ? 'text-success-700' : 'text-surface-600'">{{ required.completed ? 'Completed' : 'Required' }}</span></li></ul>
        </section>
        <div class="border-b border-surface-200 bg-white rounded-2xl overflow-hidden">
            <div class="scrollbar-hide flex items-center gap-1 overflow-x-auto px-4 sm:px-6">
                <button
                    v-for="tab in detailTabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-all',
                        activeTab === tab.id
                            ? 'border-primary-500 text-primary-600'
                            : 'border-transparent text-surface-500 hover:border-surface-300 hover:text-surface-700',
                    ]"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <Card v-if="activeTab === 'curriculum'" title="Curriculum">
            <template #header-actions>
                <span class="text-sm text-surface-400">
                    {{ course.modules_count || 0 }} modules - {{ course.lessons_count || 0 }} lessons
                </span>
            </template>

            <div class="space-y-3">
                <div
                    v-for="(module, mi) in modules"
                    :key="module.id"
                    class="overflow-hidden rounded-xl border border-surface-200/60"
                >
                    <button
                        class="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-surface-50"
                        @click="toggleModule(module.id)"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-50 text-sm font-semibold text-primary-600">
                                {{ mi + 1 }}
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-surface-900">{{ module.title }}</h4>
                                <p class="text-xs text-surface-400">{{ module.lessons?.length || 0 }} lessons - {{ module.duration_minutes ? module.duration_minutes + ' min' : '' }}</p>
                            </div>
                        </div>
                        <ChevronDown :class="['h-5 w-5 text-surface-400 transition-transform', expandedModules[module.id] ? 'rotate-180' : '']" />
                    </button>

                    <transition name="expand">
                        <div v-if="expandedModules[module.id]" class="divide-y divide-surface-100 border-t border-surface-100">
                            <component
                                :is="lesson.can_access ? 'a' : 'div'"
                                v-for="(lesson, li) in module.lessons"
                                :key="lesson.id"
                                :href="lesson.can_access ? `/courses/${course.slug}/lessons/${lesson.id}` : undefined"
                                @click.prevent="lesson.can_access ? goToLesson(lesson.id) : null"
                                :class="[
                                    'flex items-center gap-3 px-4 py-3 transition-colors',
                                    lesson.can_access
                                        ? 'cursor-pointer hover:bg-primary-50'
                                        : 'opacity-80 hover:bg-surface-50',
                                ]"
                            >
                                <div :class="[
                                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                    lessonProgress[lesson.id]?.completed ? 'bg-success-500 text-white' : 'bg-surface-100 text-surface-400',
                                ]">
                                    <Check v-if="lessonProgress[lesson.id]?.completed" class="h-3.5 w-3.5" />
                                    <span v-else>{{ li + 1 }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-surface-700">{{ lesson.title }}</p>
                                    <p v-if="lesson.is_locked && lesson.availability_label" class="mt-0.5 text-xs text-surface-400">
                                        {{ lesson.availability_label }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <component :is="lessonTypeIcon(lesson.content_type)" class="h-4 w-4 text-surface-300" />
                                    <span class="text-xs text-surface-400">{{ lesson.duration_minutes ? lesson.duration_minutes + ' min' : '' }}</span>
                                    <Badge v-if="lesson.is_free_preview" variant="info" size="xs">Preview</Badge>
                                    <Lock v-if="lesson.is_locked" class="h-4 w-4 text-surface-300" />
                                </div>
                            </component>
                        </div>
                    </transition>
                </div>
            </div>
        </Card>

        <Card v-if="activeTab === 'resources' && resources?.length" title="Resources">
            <div class="divide-y divide-surface-100">
                <div v-for="resource in resources" :key="resource.id" class="flex items-center justify-between py-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50">
                            <FileText class="h-4 w-4 text-primary-600" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-surface-800">{{ resource.title }}</p>
                            <p class="text-xs capitalize text-surface-400">
                                {{ resource.type.replace('_', ' ') }} - {{ formatBytes(resource.file_size_bytes) }}
                            </p>
                        </div>
                    </div>
                    <Button v-if="isEnrolled" variant="ghost" size="sm" @click="downloadResource(resource)">
                        <Download class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </Card>

        <Card v-if="activeTab === 'reviews'" title="Ratings & Reviews">
            <CourseRating
                :course-id="course.id"
                :average="ratingAverage"
                :distribution="ratings.distribution || {}"
                :reviews="ratings.reviews || []"
                :user-rating="ratings.userRating"
                :total-ratings="ratings.total || 0"
                :can-rate="isEnrolled"
            />
        </Card>
    </div>
</template>

<script setup>
import SafeHtml from '@/Components/SafeHtml.vue';
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import CircularProgress from '@/Components/UI/CircularProgress.vue';
import CourseRating from '@/Components/CourseRating.vue';
import {
    ArrowLeft, Layers, Clock, Users, Check, Award, BookOpen,
    ChevronDown, Play, FileText, Headphones, Video, Download, Star, Lock, List, Star as StarIcon,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    checkoutOffer: { type: Object, default: () => ({ price: 0, currency: 'NGN', purchasable: false }) },
    course: { type: Object, required: true },
    modules: { type: Array, default: () => [] },
    resources: { type: Array, default: () => [] },
    prerequisiteCourses: { type: Array, default: () => [] },
    isEnrolled: { type: Boolean, default: false },
    hasEnrollment: { type: Boolean, default: false },
    enrollmentStatus: { type: String, default: null },
    enrollment: { type: Object, default: null },
    lessonProgress: { type: Object, default: () => ({}) },
    ratings: {
        type: Object,
        default: () => ({
            average: 0,
            distribution: {},
            reviews: [],
            total: 0,
            userRating: null,
        }),
    },
});

const expandedModules = reactive({});
const ratingAverage = computed(() => Number(props.ratings?.average || 0));
const displayCurrency = computed(() => (props.course.currency || 'NGN').toUpperCase());
const activeTab = ref('curriculum');
const enrolling = ref(false);
const checkoutError = ref('');

const detailTabs = computed(() => {
    const tabs = [
        { id: 'curriculum', label: 'Curriculum', icon: List },
    ];

    if (props.resources?.length) {
        tabs.push({ id: 'resources', label: 'Resources', icon: FileText });
    }

    tabs.push({ id: 'reviews', label: 'Reviews', icon: StarIcon });

    return tabs;
});

function toggleModule(id) {
    expandedModules[id] = !expandedModules[id];
}

function lessonTypeIcon(type) {
    const map = { video: Video, audio: Headphones, text: FileText, pdf: FileText, slides: FileText };
    return map[type] || Play;
}

function enroll() {
    enrolling.value = true; checkoutError.value = '';
    router.post(`/courses/${props.course.id}/enroll`, {}, { onFinish: () => { enrolling.value = false; }, onError: errors => { checkoutError.value = Object.values(errors).join(' '); } });
}

function goToLesson(lessonId) {
    router.visit(`/courses/${props.course.slug}/lessons/${lessonId}`);
}

function downloadResource(resource) {
    if (!resource?.id) {
        return;
    }

    router.visit(`/courses/${props.course.slug}/resources/${resource.id}/download`);
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / Math.pow(1024, index)).toFixed(1)} ${units[index]}`;
}

const nextLessonUrl = computed(() => {
    for (const mod of props.modules) {
        for (const lesson of mod.lessons || []) {
            if (!lesson.can_access) {
                continue;
            }

            if (!props.lessonProgress[lesson.id]?.completed) {
                return `/courses/${props.course.slug}/lessons/${lesson.id}`;
            }
        }
    }

    for (const mod of props.modules) {
        const accessibleLesson = (mod.lessons || []).find((lesson) => lesson.can_access);
        if (accessibleLesson) {
            return `/courses/${props.course.slug}/lessons/${accessibleLesson.id}`;
        }
    }

    return `/courses/${props.course.slug}`;
});

const previewVideo = computed(() => resolvePreviewVideo(props.course.preview_video));

function resolvePreviewVideo(url) {
    if (!url) {
        return null;
    }

    if (url.includes('youtube.com/watch?v=')) {
        try {
            const videoId = new URL(url).searchParams.get('v');
            return videoId ? { type: 'embed', url: `https://www.youtube.com/embed/${videoId}` } : null;
        } catch (error) {
            return null;
        }
    }

    if (url.includes('youtu.be/')) {
        const videoId = url.split('youtu.be/')[1]?.split('?')[0];
        return videoId ? { type: 'embed', url: `https://www.youtube.com/embed/${videoId}` } : null;
    }

    if (url.includes('vimeo.com/')) {
        const videoId = url.split('vimeo.com/')[1]?.split('?')[0];
        return videoId ? { type: 'embed', url: `https://player.vimeo.com/video/${videoId}` } : null;
    }

    return { type: 'video', url };
}
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

<template>
    <Head :title="lesson.title" />

    <div class="flex min-h-[calc(100vh-4rem)] flex-col lg:flex-row">
        <aside
            :class="[
                'hidden lg:flex flex-col w-80 border-r border-surface-200 bg-white shrink-0',
                'sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto',
            ]"
        >
            <div class="border-b border-surface-100 p-4">
                <Link :href="`/courses/${course.slug}`" class="mb-2 flex items-center gap-2 text-sm text-surface-500 transition-colors hover:text-primary-600">
                    <ArrowLeft class="h-4 w-4" />
                    Back to course
                </Link>
                <h2 class="text-sm font-semibold leading-tight text-surface-900">{{ course.title }}</h2>
            </div>

            <nav class="flex-1 space-y-2 overflow-y-auto p-3">
                <div v-for="mod in modules" :key="mod.id">
                    <button
                        @click="toggleModule(mod.id)"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-surface-500 transition-colors hover:bg-surface-50"
                    >
                        <span class="truncate">{{ mod.title }}</span>
                        <ChevronDown :class="['h-4 w-4 transition-transform', expandedModules.includes(mod.id) ? 'rotate-180' : '']" />
                    </button>

                    <div v-if="expandedModules.includes(mod.id)" class="mt-1 space-y-0.5">
                        <component
                            :is="l.can_access ? Link : 'div'"
                            v-for="l in mod.lessons"
                            :key="l.id"
                            :href="l.can_access ? `/courses/${course.slug}/lessons/${l.id}` : undefined"
                            :class="[
                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all',
                                l.id === lesson.id
                                    ? 'bg-primary-50 font-medium text-primary-700'
                                    : l.can_access
                                        ? 'text-surface-600 hover:bg-surface-50'
                                        : 'bg-surface-50/70 text-surface-400',
                            ]"
                        >
                            <div :class="lessonStatusIcon(l.id)" />
                            <component :is="lessonIcon(l)" class="h-3.5 w-3.5 shrink-0 text-surface-400" aria-hidden="true" />
                            <div class="min-w-0 flex-1">
                                <span class="block truncate">{{ l.title }}</span>
                                <span v-if="l.is_locked && l.availability_label" class="text-[11px] text-surface-400">
                                    {{ l.availability_label }}
                                </span>
                            </div>
                        </component>
                    </div>
                </div>
            </nav>

            <div class="border-t border-surface-100 p-4">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-surface-500">Course Progress</span>
                    <span class="text-xs font-semibold text-primary-600">{{ overallProgress }}%</span>
                </div>
                <ProgressBar :percentage="overallProgress" size="sm" color="primary" />
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <div class="sticky top-0 z-10 border-b border-surface-200/60 bg-white/80 backdrop-blur-xl">
                <div class="flex items-center justify-between px-4 py-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <Link :href="`/courses/${course.slug}`" class="rounded-lg p-1 transition-colors hover:bg-surface-100 lg:hidden">
                            <ArrowLeft class="h-5 w-5 text-surface-500" />
                        </Link>
                        <div class="min-w-0">
                            <p class="truncate text-xs text-surface-400">{{ lesson.module_title }}</p>
                            <h1 class="truncate text-sm font-semibold text-surface-900 sm:text-base">{{ lesson.title }}</h1>
                            <p v-if="lesson.availability_label" class="mt-0.5 truncate text-xs text-surface-400">
                                {{ lesson.availability_label }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="toggleBookmark"
                            :class="[
                                'rounded-lg p-2 transition-all',
                                isBookmarked
                                    ? 'bg-warning-50 text-warning-500 hover:bg-warning-100'
                                    : 'text-surface-400 hover:bg-surface-100 hover:text-surface-600',
                            ]"
                            title="Bookmark this lesson"
                        >
                            <BookmarkIcon class="h-5 w-5" :fill="isBookmarked ? 'currentColor' : 'none'" />
                        </button>

                        <span
                            v-if="progress.status !== 'completed' && lesson.is_activity"
                            class="hidden text-xs text-surface-500 sm:inline"
                        >
                            {{ lesson.content_type === 'quiz' ? 'Pass the quiz to complete' : 'Submit your work to complete' }}
                        </span>
                        <Button
                            v-else-if="progress.status !== 'completed' && !lesson.scorm_path"
                            variant="success"
                            size="sm"
                            :icon="CheckCircle"
                            :loading="completing"
                            @click="markComplete"
                        >
                            Mark Complete
                        </Button>
                        <Badge v-else-if="progress.status === 'completed'" variant="success" size="sm">
                            <CheckCircle class="mr-1 h-3.5 w-3.5" />
                            Completed
                        </Badge>
                    </div>
                </div>

                <ProgressBar :percentage="progress.progress_pct" size="xs" color="primary" class="px-0" />
            </div>

            <div class="border-b border-surface-200 bg-white">
                <div class="scrollbar-hide flex items-center gap-1 overflow-x-auto px-4 sm:px-6">
                    <button
                        v-for="tab in availableTabs"
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

            <div class="max-w-4xl p-4 sm:p-6 lg:p-8">
                <div v-if="hasStudyTools" class="mb-5 rounded-xl border border-surface-200 bg-surface-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-surface-800">Study Tools</p>
                            <p class="text-xs text-surface-500">Turn key terms into flashcards and save lessons for offline access.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="`/flashcards?course_id=${course.id}`"
                                class="rounded-lg border border-surface-200 px-3 py-2 text-xs font-semibold text-surface-600 hover:bg-white"
                            >
                                View Flashcards
                            </Link>
                            <Button
                                v-if="hasKeyTerms"
                                variant="primary"
                                size="sm"
                                :loading="creatingFlashcards"
                                @click="createFlashcards"
                            >
                                Create Flashcards
                            </Button>
                            <Button
                                v-if="canSaveOffline && !downloadedLesson"
                                variant="secondary"
                                size="sm"
                                :loading="downloadingLesson"
                                @click="downloadLesson"
                            >
                                Save reading offline
                            </Button>
                            <Button
                                v-else-if="canSaveOffline"
                                variant="ghost"
                                size="sm"
                                :loading="downloadingLesson"
                                @click="removeOffline"
                            >
                                Remove Offline
                            </Button>
                        </div>
                    </div>
                </div>

                <p v-if="offlineMessage" role="status" class="mb-4 rounded-xl border border-surface-200 bg-white p-4 text-sm text-surface-700">{{ offlineMessage }}</p>

                <LessonActivity
                    v-if="activeTab === 'activity'"
                    :lesson="lesson"
                    :activity="activity"
                />

                <ReadContent
                    v-if="activeTab === 'read'"
                    :text-content="lesson.text_content"
                    :summary="lesson.summary"
                    :key-terms="lesson.key_terms"
                    :duration-minutes="lesson.duration_minutes"
                />

                <VideoPlayer
                    v-if="activeTab === 'watch'"
                    :video-url="lesson.video_url"
                    :chapters="lesson.video_chapters"
                    :transcript="lesson.video_transcript"
                    :duration-seconds="lesson.video_duration_seconds"
                    :initial-position="progress.video_position_sec"
                    @position-update="onVideoPositionUpdate"
                />

                <AudioPlayer
                    v-if="activeTab === 'listen'"
                    :audio-url="lesson.audio_url"
                    :duration-seconds="lesson.audio_duration_seconds"
                    :lesson-title="lesson.title"
                />

                <PdfViewer
                    v-if="activeTab === 'pdf'"
                    :pdf-url="lesson.pdf_url"
                    :slides-url="lesson.slides_url"
                    :is-downloadable="lesson.is_downloadable"
                    :lesson-id="lesson.id"
                />

                <div v-if="activeTab === 'scorm'" class="space-y-4">
                    <div class="rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-600">
                        Interactive lesson progress saves automatically while the package is in use.
                    </div>

                    <ScormPlayer
                        :lesson-id="lesson.id"
                        :entry-path="lesson.scorm_path"
                        :version="lesson.scorm_version || '1.2'"
                        :initial-status="lesson.scorm_progress?.lesson_status || 'not attempted'"
                        :initial-score="lesson.scorm_progress?.score_raw"
                        :initial-location="lesson.scorm_progress?.lesson_location || ''"
                        :initial-suspend-data="lesson.scorm_progress?.suspend_data"
                        @scorm-progress="handleScormProgress"
                        @scorm-completed="handleScormCompleted"
                    />
                </div>

                <LessonNotes v-if="activeTab === 'notes'" :notes="notes" :lesson-id="lesson.id" />
            </div>

            <div class="sticky bottom-0 border-t border-surface-200/60 bg-white/80 px-4 py-3 backdrop-blur-xl sm:px-6">
                <div class="flex max-w-4xl items-center justify-between">
                    <Link
                        v-if="prevLesson?.can_access"
                        :href="`/courses/${course.slug}/lessons/${prevLesson.id}`"
                        class="flex items-center gap-2 text-sm text-surface-600 transition-colors hover:text-primary-600"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ prevLesson.title }}</span>
                        <span class="sm:hidden">Previous</span>
                    </Link>
                    <div v-else-if="prevLesson" class="flex items-center gap-2 text-sm text-surface-400">
                        <ChevronLeft class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ prevLesson.title }}</span>
                        <span class="sm:hidden">Previous</span>
                    </div>
                    <div v-else />

                    <span class="text-xs font-medium text-surface-400">
                        {{ currentIndex + 1 }} / {{ allLessons.length }}
                    </span>

                    <Link
                        v-if="nextLesson?.can_access"
                        :href="`/courses/${course.slug}/lessons/${nextLesson.id}`"
                        class="flex items-center gap-2 text-sm text-surface-600 transition-colors hover:text-primary-600"
                    >
                        <span class="hidden sm:inline">{{ nextLesson.title }}</span>
                        <span class="sm:hidden">Next</span>
                        <ChevronRight class="h-4 w-4" />
                    </Link>
                    <div v-else-if="nextLesson" class="flex items-center gap-2 text-sm text-surface-400">
                        <span class="hidden sm:inline">{{ nextLesson.title }}</span>
                        <span class="sm:hidden">Next</span>
                        <ChevronRight class="h-4 w-4" />
                    </div>
                    <div v-else />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import ReadContent from '@/Components/Lesson/ReadContent.vue';
import VideoPlayer from '@/Components/Lesson/VideoPlayer.vue';
import AudioPlayer from '@/Components/Lesson/AudioPlayer.vue';
import PdfViewer from '@/Components/Lesson/PdfViewer.vue';
import LessonNotes from '@/Components/Lesson/LessonNotes.vue';
import ScormPlayer from '@/Components/ScormPlayer.vue';
import LessonActivity from '@/Components/Lesson/LessonActivity.vue';
import { lessonIcon } from '@/Components/Authoring/lessonTypes';
import { saveLessonOffline, getOfflineLesson, removeOfflineLesson } from '@/pwa';
import {
    ArrowLeft, BookmarkIcon, CheckCircle, ChevronDown, ChevronLeft, ChevronRight,
    FileText, Play, Headphones, File, StickyNote,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    course: Object,
    lesson: Object,
    progress: Object,
    notes: Array,
    isBookmarked: Boolean,
    allLessons: Array,
    currentIndex: Number,
    prevLesson: Object,
    nextLesson: Object,
    lessonProgress: Object,
    modules: Array,
    activity: { type: Object, default: null },
    sequential: Boolean,
});

const availableTabs = computed(() => {
    const tabs = [];

    if (props.lesson.is_activity) {
        const labels = { quiz: 'Quiz', assignment: 'Assignment', case_study: 'Case study' };
        tabs.push({ id: 'activity', label: labels[props.lesson.content_type] || 'Activity', icon: lessonIcon(props.lesson) });
        tabs.push({ id: 'notes', label: 'Notes', icon: StickyNote });

        return tabs;
    }

    if (props.lesson.text_content || props.lesson.summary) {
        tabs.push({ id: 'read', label: 'Read', icon: FileText });
    }

    if (props.lesson.video_url) {
        tabs.push({ id: 'watch', label: 'Watch', icon: Play });
    }

    if (props.lesson.audio_url) {
        tabs.push({ id: 'listen', label: 'Listen', icon: Headphones });
    }

    if (props.lesson.pdf_url || props.lesson.slides_url) {
        tabs.push({ id: 'pdf', label: 'PDF / Slides', icon: File });
    }

    if (props.lesson.scorm_path) {
        tabs.push({ id: 'scorm', label: 'Interactive', icon: Play });
    }

    tabs.push({ id: 'notes', label: 'Notes', icon: StickyNote });

    return tabs;
});

const preferredTab = { pdf: 'pdf', slides: 'pdf', video: 'watch', audio: 'listen' }[props.lesson.content_type];
const activeTab = ref(availableTabs.value.find((tab) => tab.id === preferredTab)?.id || availableTabs.value[0]?.id || 'read');
const creatingFlashcards = ref(false);
const downloadingLesson = ref(false);
const downloadedLesson = ref(false);
const offlineMessage = ref('');
const canSaveOffline = computed(() => props.lesson.is_downloadable && !props.lesson.scorm_path && !props.lesson.is_activity && (props.lesson.text_content || props.lesson.summary));

const hasKeyTerms = computed(() => Array.isArray(props.lesson?.key_terms) && props.lesson.key_terms.length > 0);
const hasStudyTools = computed(() => !props.lesson?.is_activity && (hasKeyTerms.value || props.lesson?.text_content || props.lesson?.video_url || props.lesson?.audio_url));

onMounted(async () => {
    const existing = await getOfflineLesson(props.lesson.id).catch(() => null);
    downloadedLesson.value = !!existing;
});

const expandedModules = ref(
    props.modules
        .filter((module) => module.lessons.some((lessonItem) => lessonItem.id === props.lesson.id))
        .map((module) => module.id)
);

function toggleModule(id) {
    const idx = expandedModules.value.indexOf(id);

    if (idx >= 0) {
        expandedModules.value.splice(idx, 1);
    } else {
        expandedModules.value.push(id);
    }
}

function lessonStatusIcon(lessonId) {
    const lessonState = props.lessonProgress?.[lessonId];

    if (lessonState?.status === 'completed') {
        return 'h-2.5 w-2.5 shrink-0 rounded-full bg-success-500';
    }

    if (lessonState?.status === 'in_progress') {
        return 'h-2.5 w-2.5 shrink-0 rounded-full bg-primary-500 animate-pulse';
    }

    return 'h-2.5 w-2.5 shrink-0 rounded-full bg-surface-300';
}

const overallProgress = computed(() => {
    if (!props.lessonProgress || !props.allLessons.length) {
        return 0;
    }

    const completed = Object.values(props.lessonProgress).filter((item) => item.status === 'completed').length;
    return Math.round((completed / props.allLessons.length) * 100);
});

function toggleBookmark() {
    router.post(`/lessons/${props.lesson.id}/bookmark`, {}, { preserveScroll: true });
}

const completing = ref(false);

function markComplete() {
    completing.value = true;

    router.post(`/lessons/${props.lesson.id}/complete`, {}, {
        preserveScroll: true,
        onFinish: () => {
            completing.value = false;
        },
    });
}

function createFlashcards() {
    if (!hasKeyTerms.value) {
        return;
    }

    creatingFlashcards.value = true;

    router.post(`/lessons/${props.lesson.id}/flashcards`, {}, {
        preserveScroll: true,
        onFinish: () => {
            creatingFlashcards.value = false;
        },
    });
}

async function downloadLesson() {
    downloadingLesson.value = true;
    try {
        await saveLessonOffline(props.lesson.id);
        offlineMessage.value = 'Reading saved in this tab for 24 hours. Open Downloads to read it offline. Media and assessments need a connection.';
        downloadedLesson.value = true;
    } catch (error) {
        offlineMessage.value = error.response?.data?.message || error.message || 'Reading could not be saved.';
    } finally {
        downloadingLesson.value = false;
    }
}

async function removeOffline() {
    downloadingLesson.value = true;
    try {
        await removeOfflineLesson(props.lesson.id);
        downloadedLesson.value = false;
    } finally {
        downloadingLesson.value = false;
    }
}

function refreshLessonProgress() {
    router.reload({
        only: ['progress', 'lessonProgress'],
        preserveScroll: true,
        preserveState: true,
    });
}

function handleScormProgress(payload) {
    if (payload?.completed) {
        refreshLessonProgress();
    }
}

function handleScormCompleted() {
    refreshLessonProgress();
}

let positionDebounce = null;

function onVideoPositionUpdate(position) {
    clearTimeout(positionDebounce);
    positionDebounce = setTimeout(() => {
        router.post(`/lessons/${props.lesson.id}/progress`, {
            video_position_sec: position,
        }, { preserveScroll: true, preserveState: true });
    }, 5000);
}
</script>

<template>
    <Head :title="lesson.title" />

    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <!-- Sidebar: Course curriculum (desktop) -->
        <aside
            :class="[
                'hidden lg:flex flex-col w-80 border-r border-surface-200 bg-white shrink-0',
                'sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto',
            ]"
        >
            <!-- Course title -->
            <div class="p-4 border-b border-surface-100">
                <Link :href="`/courses/${course.slug}`" class="flex items-center gap-2 text-sm text-surface-500 hover:text-primary-600 transition-colors mb-2">
                    <ArrowLeft class="w-4 h-4" />
                    Back to course
                </Link>
                <h2 class="font-semibold text-surface-900 text-sm leading-tight">{{ course.title }}</h2>
            </div>

            <!-- Modules & Lessons -->
            <nav class="flex-1 overflow-y-auto p-3 space-y-2">
                <div v-for="mod in modules" :key="mod.id">
                    <button
                        @click="toggleModule(mod.id)"
                        class="flex items-center justify-between w-full px-3 py-2 text-xs font-semibold text-surface-500 uppercase tracking-wider hover:bg-surface-50 rounded-lg transition-colors"
                    >
                        <span class="truncate">{{ mod.title }}</span>
                        <ChevronDown
                            :class="['w-4 h-4 transition-transform', expandedModules.includes(mod.id) ? 'rotate-180' : '']"
                        />
                    </button>
                    <div v-if="expandedModules.includes(mod.id)" class="mt-1 space-y-0.5">
                        <Link
                            v-for="l in mod.lessons"
                            :key="l.id"
                            :href="`/courses/${course.slug}/lessons/${l.id}`"
                            :class="[
                                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all',
                                l.id === lesson.id
                                    ? 'bg-primary-50 text-primary-700 font-medium'
                                    : 'text-surface-600 hover:bg-surface-50',
                            ]"
                        >
                            <div :class="lessonStatusIcon(l.id)" />
                            <span class="truncate flex-1">{{ l.title }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Overall progress -->
            <div class="p-4 border-t border-surface-100">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-surface-500">Course Progress</span>
                    <span class="text-xs font-semibold text-primary-600">{{ overallProgress }}%</span>
                </div>
                <ProgressBar :percentage="overallProgress" size="sm" color="primary" />
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 min-w-0">
            <!-- Top bar: lesson title + actions -->
            <div class="sticky top-0 z-10 bg-white/80 backdrop-blur-xl border-b border-surface-200/60">
                <div class="flex items-center justify-between px-4 sm:px-6 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Mobile back -->
                        <Link :href="`/courses/${course.slug}`" class="lg:hidden p-1 hover:bg-surface-100 rounded-lg transition-colors">
                            <ArrowLeft class="w-5 h-5 text-surface-500" />
                        </Link>
                        <div class="min-w-0">
                            <p class="text-xs text-surface-400 truncate">{{ lesson.module_title }}</p>
                            <h1 class="font-semibold text-surface-900 text-sm sm:text-base truncate">{{ lesson.title }}</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Bookmark -->
                        <button
                            @click="toggleBookmark"
                            :class="[
                                'p-2 rounded-lg transition-all',
                                isBookmarked
                                    ? 'text-warning-500 bg-warning-50 hover:bg-warning-100'
                                    : 'text-surface-400 hover:bg-surface-100 hover:text-surface-600',
                            ]"
                            title="Bookmark this lesson"
                        >
                            <BookmarkIcon class="w-5 h-5" :fill="isBookmarked ? 'currentColor' : 'none'" />
                        </button>

                        <!-- Complete button -->
                        <Button
                            v-if="progress.status !== 'completed'"
                            variant="success"
                            size="sm"
                            :icon="CheckCircle"
                            @click="markComplete"
                            :loading="completing"
                        >
                            Mark Complete
                        </Button>
                        <Badge v-else variant="success" size="sm">
                            <CheckCircle class="w-3.5 h-3.5 mr-1" />
                            Completed
                        </Badge>
                    </div>
                </div>

                <!-- Progress bar -->
                <ProgressBar :percentage="progress.progress_pct" size="xs" color="primary" class="px-0" />
            </div>

            <!-- Content tabs -->
            <div class="border-b border-surface-200 bg-white">
                <div class="flex items-center gap-1 px-4 sm:px-6 overflow-x-auto scrollbar-hide">
                    <button
                        v-for="tab in availableTabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all whitespace-nowrap',
                            activeTab === tab.id
                                ? 'border-primary-500 text-primary-600'
                                : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300',
                        ]"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Tab content -->
            <div class="p-4 sm:p-6 lg:p-8 max-w-4xl">
                <!-- Read tab -->
                <ReadContent
                    v-if="activeTab === 'read'"
                    :text-content="lesson.text_content"
                    :summary="lesson.summary"
                    :key-terms="lesson.key_terms"
                    :duration-minutes="lesson.duration_minutes"
                />

                <!-- Watch tab -->
                <VideoPlayer
                    v-if="activeTab === 'watch'"
                    :video-url="lesson.video_url"
                    :chapters="lesson.video_chapters"
                    :transcript="lesson.video_transcript"
                    :duration-seconds="lesson.video_duration_seconds"
                    :initial-position="progress.video_position_sec"
                    @position-update="onVideoPositionUpdate"
                />

                <!-- Listen tab -->
                <AudioPlayer
                    v-if="activeTab === 'listen'"
                    :audio-url="lesson.audio_url"
                    :duration-seconds="lesson.audio_duration_seconds"
                    :lesson-title="lesson.title"
                />

                <!-- PDF tab -->
                <PdfViewer
                    v-if="activeTab === 'pdf'"
                    :pdf-url="lesson.pdf_url"
                    :slides-url="lesson.slides_url"
                    :is-downloadable="lesson.is_downloadable"
                    :lesson-id="lesson.id"
                />

                <!-- Notes tab -->
                <LessonNotes
                    v-if="activeTab === 'notes'"
                    :notes="notes"
                    :lesson-id="lesson.id"
                />
            </div>

            <!-- Bottom navigation: Prev / Next -->
            <div class="sticky bottom-0 bg-white/80 backdrop-blur-xl border-t border-surface-200/60 px-4 sm:px-6 py-3">
                <div class="flex items-center justify-between max-w-4xl">
                    <Link
                        v-if="prevLesson"
                        :href="`/courses/${course.slug}/lessons/${prevLesson.id}`"
                        class="flex items-center gap-2 text-sm text-surface-600 hover:text-primary-600 transition-colors"
                    >
                        <ChevronLeft class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ prevLesson.title }}</span>
                        <span class="sm:hidden">Previous</span>
                    </Link>
                    <div v-else />

                    <span class="text-xs text-surface-400 font-medium">
                        {{ currentIndex + 1 }} / {{ allLessons.length }}
                    </span>

                    <Link
                        v-if="nextLesson"
                        :href="`/courses/${course.slug}/lessons/${nextLesson.id}`"
                        class="flex items-center gap-2 text-sm text-surface-600 hover:text-primary-600 transition-colors"
                    >
                        <span class="hidden sm:inline">{{ nextLesson.title }}</span>
                        <span class="sm:hidden">Next</span>
                        <ChevronRight class="w-4 h-4" />
                    </Link>
                    <div v-else />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
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
});

// Tab management
const availableTabs = computed(() => {
    const tabs = [];
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
    tabs.push({ id: 'notes', label: 'Notes', icon: StickyNote });
    return tabs;
});

const activeTab = ref(availableTabs.value[0]?.id || 'read');

// Module expansion
const expandedModules = ref(
    props.modules
        .filter(m => m.lessons.some(l => l.id === props.lesson.id))
        .map(m => m.id)
);

function toggleModule(id) {
    const idx = expandedModules.value.indexOf(id);
    if (idx >= 0) {
        expandedModules.value.splice(idx, 1);
    } else {
        expandedModules.value.push(id);
    }
}

// Lesson progress status icon
function lessonStatusIcon(lessonId) {
    const p = props.lessonProgress?.[lessonId];
    if (p?.status === 'completed') {
        return 'w-2.5 h-2.5 rounded-full bg-success-500 shrink-0';
    }
    if (p?.status === 'in_progress') {
        return 'w-2.5 h-2.5 rounded-full bg-primary-500 shrink-0 animate-pulse';
    }
    return 'w-2.5 h-2.5 rounded-full bg-surface-300 shrink-0';
}

// Overall progress
const overallProgress = computed(() => {
    if (!props.lessonProgress || !props.allLessons.length) return 0;
    const completed = Object.values(props.lessonProgress).filter(p => p.status === 'completed').length;
    return Math.round((completed / props.allLessons.length) * 100);
});

// Bookmark toggle
function toggleBookmark() {
    router.post(`/lessons/${props.lesson.id}/bookmark`, {}, { preserveScroll: true });
}

// Mark complete
const completing = ref(false);

function markComplete() {
    completing.value = true;
    router.post(`/lessons/${props.lesson.id}/complete`, {}, {
        preserveScroll: true,
        onFinish: () => completing.value = false,
    });
}

// Video position tracking
let positionDebounce = null;

function onVideoPositionUpdate(position) {
    clearTimeout(positionDebounce);
    positionDebounce = setTimeout(() => {
        router.post(`/lessons/${props.lesson.id}/progress`, {
            video_position_sec: position,
        }, { preserveScroll: true, preserveState: true });
    }, 5000); // Debounce 5s
}
</script>

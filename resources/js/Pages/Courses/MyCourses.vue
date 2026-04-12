<template>
    <Head title="My Courses" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">My Courses</h1>
                <p class="text-sm text-surface-500 mt-1">Track your learning progress across all enrolled courses.</p>
            </div>
            <Link href="/courses" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
                <Search class="w-4 h-4" />
                Browse more courses
            </Link>
        </div>

        <!-- Status tabs -->
        <div class="flex items-center gap-1 border-b border-surface-200">
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                @click="switchStatus(tab.value)"
                :class="[
                    'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all',
                    currentStatus === tab.value
                        ? 'border-primary-500 text-primary-600'
                        : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300',
                ]"
            >
                {{ tab.label }}
                <span
                    :class="[
                        'text-xs font-semibold px-2 py-0.5 rounded-full',
                        currentStatus === tab.value
                            ? 'bg-primary-100 text-primary-700'
                            : 'bg-surface-100 text-surface-500',
                    ]"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Course grid -->
        <div v-if="enrollments.data.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="enrollment in enrollments.data"
                :key="enrollment.id"
                class="rounded-2xl bg-white border border-surface-200/60 shadow-sm hover:shadow-lg hover:border-primary-200 hover:-translate-y-0.5 transition-all duration-300 overflow-hidden group"
            >
                <!-- Thumbnail -->
                <div class="aspect-video bg-surface-100 overflow-hidden relative">
                    <img
                        v-if="enrollment.course.thumbnail"
                        :src="enrollment.course.thumbnail"
                        :alt="enrollment.course.title"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <BookOpen class="w-12 h-12 text-surface-300" />
                    </div>

                    <!-- Progress overlay -->
                    <div class="absolute bottom-0 left-0 right-0">
                        <div class="h-1 bg-surface-900/20">
                            <div
                                class="h-full bg-primary-500 transition-all duration-500"
                                :style="{ width: `${enrollment.progress_pct}%` }"
                            />
                        </div>
                    </div>

                    <!-- Status badge -->
                    <div class="absolute top-3 right-3">
                        <span
                            :class="[
                                'text-xs font-semibold px-2.5 py-1 rounded-full backdrop-blur-sm',
                                enrollment.status === 'completed'
                                    ? 'bg-success-500/80 text-white'
                                    : 'bg-white/80 text-surface-700',
                            ]"
                        >
                            {{ enrollment.progress_pct }}%
                        </span>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span v-if="enrollment.course.category" class="text-xs font-medium text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">
                            {{ enrollment.course.category.name }}
                        </span>
                        <span class="text-xs text-surface-400 capitalize">{{ enrollment.course.level }}</span>
                    </div>

                    <h3 class="font-semibold text-surface-900 text-sm leading-tight mb-2 line-clamp-2 group-hover:text-primary-600 transition-colors">
                        {{ enrollment.course.title }}
                    </h3>

                    <div class="flex items-center gap-3 text-xs text-surface-400 mb-3">
                        <span class="flex items-center gap-1">
                            <Layers class="w-3.5 h-3.5" />
                            {{ enrollment.course.modules_count }} modules
                        </span>
                        <span class="flex items-center gap-1">
                            <FileText class="w-3.5 h-3.5" />
                            {{ enrollment.course.lessons_count }} lessons
                        </span>
                    </div>

                    <ProgressBar
                        :percentage="enrollment.progress_pct"
                        size="sm"
                        :color="enrollment.status === 'completed' ? 'success' : 'primary'"
                        showValue
                    />

                    <div class="mt-4">
                        <Link
                            :href="`/courses/${enrollment.course.slug}`"
                            :class="[
                                'inline-flex items-center gap-2 w-full justify-center px-4 py-2.5 rounded-xl text-sm font-semibold transition-all',
                                enrollment.status === 'completed'
                                    ? 'bg-success-50 text-success-700 hover:bg-success-100'
                                    : 'bg-primary-600 text-white hover:bg-primary-700 shadow-sm shadow-primary-600/20',
                            ]"
                        >
                            <component :is="enrollment.status === 'completed' ? CheckCircle : Play" class="w-4 h-4" />
                            {{ enrollment.status === 'completed' ? 'Review Course' : 'Continue Learning' }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-16">
            <BookOpen class="w-16 h-16 text-surface-300 mx-auto mb-4" />
            <h3 class="font-semibold text-surface-700 text-lg mb-2">
                {{ currentStatus === 'completed' ? 'No completed courses yet' : 'No enrolled courses' }}
            </h3>
            <p class="text-surface-500 text-sm mb-6">
                {{ currentStatus === 'completed'
                    ? 'Keep learning to complete your first course!'
                    : 'Explore our course catalog and start your learning journey.'
                }}
            </p>
            <Link
                href="/courses"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 shadow-sm transition-all"
            >
                <Search class="w-4 h-4" />
                Browse Courses
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="enrollments.last_page > 1" class="flex justify-center gap-2">
            <Link
                v-for="page in enrollments.last_page"
                :key="page"
                :href="`/my-courses?status=${currentStatus}&page=${page}`"
                :class="[
                    'w-10 h-10 rounded-xl flex items-center justify-center text-sm font-medium transition-all',
                    enrollments.current_page === page
                        ? 'bg-primary-600 text-white'
                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200',
                ]"
            >
                {{ page }}
            </Link>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import {
    BookOpen, Search, Layers, FileText, Play, CheckCircle,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    enrollments: Object,
    statusCounts: Object,
    currentStatus: { type: String, default: 'active' },
});

const statusTabs = computed(() => [
    { value: 'all', label: 'All', count: props.statusCounts.all },
    { value: 'active', label: 'In Progress', count: props.statusCounts.active },
    { value: 'completed', label: 'Completed', count: props.statusCounts.completed },
]);

function switchStatus(status) {
    router.get('/my-courses', { status }, { preserveState: true, preserveScroll: true });
}
</script>

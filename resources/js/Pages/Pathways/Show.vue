<script setup>
import { router } from '@inertiajs/vue3';
import { Map, BookOpen, Check, Lock, ChevronRight, Star, Clock } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ pathway: Object, courses: Array, nextCourse: Object, enrolledCourseIds: Array });

function getStatus(course) {
    if (!props.enrolledCourseIds?.includes(course.id)) return 'not-started';
    const enrollment = course.enrollment;
    if (enrollment?.status === 'completed') return 'completed';
    return 'in-progress';
}

const statusConfig = {
    'completed': { label: 'Completed', color: 'text-success-600 bg-success-50 border-success-200', icon: Check },
    'in-progress': { label: 'In Progress', color: 'text-primary-600 bg-primary-50 border-primary-200', icon: Clock },
    'not-started': { label: 'Not Started', color: 'text-surface-400 bg-surface-50 border-surface-200', icon: Lock },
};
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <button @click="router.visit(route('pathways.index'))" class="text-sm text-primary-600 hover:underline mb-4 block">← Back to Pathways</button>

        <!-- Header -->
        <Card class="p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-500 to-accent-600 flex items-center justify-center flex-shrink-0">
                    <Map class="w-7 h-7 text-white" />
                </div>
                <div class="flex-1">
                    <h1 class="text-2xl font-display font-bold text-surface-900">{{ pathway.title }}</h1>
                    <p class="text-surface-600 mt-1">{{ pathway.description }}</p>
                    <div class="flex items-center gap-3 mt-3 text-sm text-surface-500">
                        <span class="flex items-center gap-1"><BookOpen class="w-4 h-4" />{{ courses.length }} courses</span>
                        <Badge v-if="pathway.level" variant="secondary">{{ pathway.level }}</Badge>
                        <Badge v-if="pathway.estimated_duration" variant="secondary">{{ pathway.estimated_duration }}</Badge>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Next Recommended -->
        <Card v-if="nextCourse" class="p-5 mb-6 border-l-4 border-primary-500 bg-primary-50/30">
            <p class="text-sm font-medium text-primary-600 mb-1">Recommended Next</p>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-surface-900">{{ nextCourse.title }}</h3>
                    <p class="text-sm text-surface-500">{{ nextCourse.description?.substring(0, 80) }}...</p>
                </div>
                <Button @click="router.visit(route('courses.show', nextCourse.slug))" variant="primary" size="sm" class="flex-shrink-0 gap-1">
                    Start <ChevronRight class="w-4 h-4" />
                </Button>
            </div>
        </Card>

        <!-- Course Roadmap -->
        <div class="relative">
            <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-surface-200"></div>

            <div v-for="(course, index) in courses" :key="course.id" class="relative flex items-start gap-4 mb-6 last:mb-0">
                <!-- Step Circle -->
                <div :class="['w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 relative z-10 border-2', statusConfig[getStatus(course)].color]">
                    <component :is="statusConfig[getStatus(course)].icon" class="w-5 h-5" />
                </div>

                <Card :class="['p-4 flex-1 cursor-pointer hover:shadow-md transition', nextCourse?.id === course.id ? 'ring-2 ring-primary-300' : '']" @click="router.visit(route('courses.show', course.slug))">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-semibold text-surface-400">STEP {{ index + 1 }}</span>
                                <Badge v-if="course.pivot?.is_required" variant="warning" size="sm">Required</Badge>
                            </div>
                            <h3 class="font-semibold text-surface-900">{{ course.title }}</h3>
                            <p class="text-sm text-surface-500 mt-0.5 line-clamp-2">{{ course.description }}</p>
                        </div>
                        <ChevronRight class="w-5 h-5 text-surface-400 mt-1 flex-shrink-0" />
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>

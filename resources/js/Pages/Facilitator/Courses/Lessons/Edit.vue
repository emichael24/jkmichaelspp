<template>
    <Head :title="`Edit: ${lesson.title}`" />

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex items-center gap-3">
            <Link aria-label="Back to course" :href="`/facilitator/courses/${course.id}/edit?tab=curriculum`" class="rounded-lg p-2 transition-colors hover:bg-surface-100">
                <ArrowLeft class="h-5 w-5 text-surface-400" />
            </Link>
            <div>
                <p class="text-sm text-surface-400">{{ course.title }} / {{ module.title }}</p>
                <h1 class="text-2xl font-bold text-surface-900">Lesson editor</h1>
            </div>
        </div>

        <LessonEditor workspace="facilitator" :form="form" :course="course" :module="module" :lesson="lesson" :activity="activity" :question-bank="questionBank" editing @save="submit" />
    </div>
</template>

<script setup>
import LessonEditor from '@/Components/Admin/LessonEditor.vue';
import { Head, Link } from '@inertiajs/vue3';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';
import { ArrowLeft } from 'lucide-vue-next';
import { useLessonForm } from '@/lib/lessonForm';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    lesson: { type: Object, required: true },
    module: { type: Object, required: true },
    course: { type: Object, required: true },
    activity: { type: Object, default: null },
    questionBank: { type: Array, default: () => [] },
});

const { form, submit } = useLessonForm({ lesson: props.lesson, activity: props.activity, url: `/facilitator/courses/${props.course.id}/modules/${props.module.id}/lessons/${props.lesson.id}`, method: 'put' });
</script>

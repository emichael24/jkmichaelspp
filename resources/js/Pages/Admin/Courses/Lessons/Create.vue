<template>
    <Head :title="`Add Lesson to ${module.title}`" />

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex items-center gap-3">
            <Link aria-label="Back to course" :href="`/admin/courses/${course.id}/edit?tab=curriculum`" class="rounded-lg p-2 transition-colors hover:bg-surface-100">
                <ArrowLeft class="h-5 w-5 text-surface-400" />
            </Link>
            <div>
                <p class="text-sm text-surface-400">{{ course.title }} / {{ module.title }}</p>
                <h1 class="text-2xl font-bold text-surface-900">Create a lesson</h1>
            </div>
        </div>

        <LessonEditor workspace="admin" :form="form" :course="course" :module="module" :question-bank="questionBank" @save="submit" />
    </div>
</template>

<script setup>
import LessonEditor from '@/Components/Admin/LessonEditor.vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeft } from 'lucide-vue-next';
import { useLessonForm } from '@/lib/lessonForm';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    module: { type: Object, required: true },
    course: { type: Object, required: true },
    initialType: { type: String, default: 'text' },
    questionBank: { type: Array, default: () => [] },
});

const { form, submit } = useLessonForm({ initialType: props.initialType, url: `/admin/modules/${props.module.id}/lessons`, method: 'post' });
</script>

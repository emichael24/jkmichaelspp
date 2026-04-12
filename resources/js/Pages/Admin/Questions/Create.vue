<template>
    <QuestionForm :courses="courses" :lessons="lessons" :preselected-course="preselectedCourse" />
</template>

<script setup>
import QuestionForm from './QuestionForm.vue';

defineProps({
    courses: Array,
    lessons: Array,
    preselectedCourse: { type: Number, default: null },
});
</script>

<template>
    <QuestionForm :question="question" :courses="courses" :lessons="lessons" />
</template>

<script setup>
import QuestionForm from './QuestionForm.vue';

defineProps({
    question: Object,
    courses: Array,
    lessons: Array,
});
</script>

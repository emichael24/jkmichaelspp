<script setup>
import Create from './Create.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
defineProps({ event: Object });
</script>

<template>
    <Create :event="event" />
</template>

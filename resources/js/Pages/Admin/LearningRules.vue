<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
defineOptions({ layout: AdminLayout });
const props = defineProps({ courses: Object, choices: Array, filters: Object });
const search = ref(props.filters.search || ''), choiceSearch = ref(''), selected = ref(null);
const form = useForm({ prerequisite_ids: [] });
const candidates = computed(() => props.choices.filter(c => c.id !== selected.value?.id && c.title.toLowerCase().includes(choiceSearch.value.toLowerCase())));
function edit(course) { selected.value = course; choiceSearch.value = ''; form.prerequisite_ids = [...course.prerequisite_ids]; form.clearErrors(); }
function save() { form.put(`/admin/learning-rules/${selected.value.id}`, { preserveScroll: true, onSuccess: () => selected.value = null }); }
</script>
<template>
    <Head title="Learning prerequisites" />
    <div class="mx-auto max-w-5xl space-y-6">
        <div><h1 class="text-2xl font-bold text-surface-900">Learning prerequisites</h1><p class="mt-2 text-sm leading-relaxed text-surface-600">Set the courses a learner must complete before they can enroll or access course content. Circular dependencies are rejected.</p></div>
        <form @submit.prevent="router.get('/admin/learning-rules', { search }, { preserveState: true })" class="flex items-end gap-3 rounded-2xl border border-surface-200 bg-white p-4"><Input v-model="search" label="Find a course" placeholder="Search course titles" /><Button type="submit" variant="secondary">Search</Button></form>
        <div class="overflow-hidden rounded-2xl border border-surface-200 bg-white">
            <ul class="divide-y divide-surface-100"><li v-for="course in courses.data" :key="course.id" class="flex flex-wrap items-center justify-between gap-4 p-5"><div><h2 class="font-semibold text-surface-900">{{ course.title }}</h2><p class="mt-1 text-sm text-surface-600">{{ course.is_published ? 'Published' : 'Draft' }} · {{ course.prerequisite_ids.length }} prerequisite{{ course.prerequisite_ids.length === 1 ? '' : 's' }}</p></div><Button variant="secondary" :aria-label="`Edit prerequisites for ${course.title}`" @click="edit(course)">Edit requirements</Button></li></ul>
            <p v-if="!courses.data.length" class="p-8 text-center text-surface-500">No courses match your search.</p>
        </div>
        <nav aria-label="Course pages" class="flex justify-between text-sm"><Link v-if="courses.prev_page_url" :href="courses.prev_page_url">Previous</Link><span>Page {{ courses.current_page }} of {{ courses.last_page }}</span><Link v-if="courses.next_page_url" :href="courses.next_page_url">Next</Link></nav>
    </div>
    <Modal :show="!!selected" size="lg" :title="`Prerequisites for ${selected?.title || 'course'}`" :closeable="!form.processing" @close="selected = null">
        <form @submit.prevent="save" class="space-y-5">
            <p class="rounded-xl bg-warning-50 p-4 text-sm leading-relaxed text-warning-800">Changes affect access for current and future learners immediately. Existing learning history and earned credentials are retained. Select no courses to remove the prerequisite requirement.</p>
            <div v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-danger-700"><p v-for="error in form.errors" :key="error">{{ error }}</p></div>
            <Input v-model="choiceSearch" label="Filter available prerequisites" />
            <fieldset class="max-h-72 space-y-3 overflow-y-auto rounded-xl border border-surface-200 p-4"><legend class="px-1 text-sm font-semibold">Published courses</legend><label v-for="course in candidates" :key="course.id" class="flex items-start gap-3 text-sm text-surface-700"><input v-model="form.prerequisite_ids" type="checkbox" :value="course.id" class="mt-0.5 rounded border-surface-300 text-primary-600" />{{ course.title }}</label><p v-if="!candidates.length" class="text-sm text-surface-500">No other published courses match.</p></fieldset>
            <p role="status" class="text-sm text-surface-500">{{ form.prerequisite_ids.length }} selected</p>
            <div class="flex justify-end gap-3"><Button variant="ghost" :disabled="form.processing" @click="selected = null">Cancel</Button><Button type="submit" :loading="form.processing">Save prerequisites</Button></div>
        </form>
    </Modal>
</template>

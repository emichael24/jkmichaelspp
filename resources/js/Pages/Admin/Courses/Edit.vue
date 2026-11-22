<template>
    <Head :title="`Edit: ${course.title}`" />

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link aria-label="Back to courses" href="/admin/courses" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-400" />
                </Link>
                <div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">{{ course.title }}</h1>
                    <div class="flex items-center gap-3 mt-1">
                        <Badge :variant="course.is_published ? 'success' : 'warning'" size="sm">
                            {{ course.is_published ? 'Published' : 'Draft' }}
                        </Badge>
                        <span class="text-sm text-surface-400">
                            {{ course.modules?.length || 0 }} modules · {{ course.lessons_count || 0 }} lessons · {{ course.enrollments_count || 0 }} enrolled
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="`/admin/courses/${course.id}/learners`" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-4 py-2 text-sm font-semibold text-surface-700 hover:bg-surface-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">
                    <Users class="h-4 w-4" /> Learners
                </Link>
                <Button
                    v-if="!course.is_published"
                    variant="primary"
                    size="sm"
                    @click="publishCourse"
                >
                    <Eye class="w-4 h-4 mr-1" /> Publish
                </Button>
                <Button
                    v-else
                    variant="secondary"
                    size="sm"
                    @click="unpublishCourse"
                >
                    <EyeOff class="w-4 h-4 mr-1" /> Unpublish
                </Button>
            </div>
        </div>

        <CourseRevisionBanner :course="course" workspace="admin" />
        <CourseReadiness :issues="readinessIssues" :status="course.approval_status" :published="course.is_published" />

        <!-- Tabs -->
        <div class="border-b border-surface-200">
            <div class="flex gap-1 -mb-px">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    :class="[
                        'px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                        activeTab === tab.key
                            ? 'border-primary-600 text-primary-600'
                            : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300',
                    ]"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <fieldset :disabled="course.is_published || course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" class="space-y-6 min-w-0">
        <!-- Tab: Details -->
        <div v-if="activeTab === 'details'">
            <form @submit.prevent="updateCourse" class="space-y-6">
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-surface-900 mb-5">Basic Information</h2>
                    <div class="grid gap-5">
                        <div>
                            <label for="courseForm-title" class="block text-sm font-medium text-surface-700 mb-1.5">Title *</label>
                    <Input id="courseForm-title" v-model="courseForm.title" :error="courseForm.errors.title" />
                        </div>
                        <div>
                            <label for="courseForm-subtitle" class="block text-sm font-medium text-surface-700 mb-1.5">Subtitle</label>
                    <Input id="courseForm-subtitle" v-model="courseForm.subtitle" />
                        </div>
                        <div>
                            <label for="courseForm-description" class="block text-sm font-medium text-surface-700 mb-1.5">Description</label>
                    <RichTextEditor v-model="courseForm.description" label="Course description" placeholder="Describe the transformation learners can expect, who this course is for, and what makes it valuable." compact :disabled="course.is_published || !!course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" />
                        </div>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label for="courseForm-category_id" class="block text-sm font-medium text-surface-700 mb-1.5">Category</label>
                    <select id="courseForm-category_id" v-model="courseForm.category_id" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="courseForm-level" class="block text-sm font-medium text-surface-700 mb-1.5">Level</label>
                    <select id="courseForm-level" v-model="courseForm.level" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <div>
                                <label for="courseForm-type" class="block text-sm font-medium text-surface-700 mb-1.5">Type</label>
                    <select id="courseForm-type" v-model="courseForm.type" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="self_paced">Self-paced</option>
                                    <option value="live">Live</option>
                                    <option value="certified">Certified</option>
                                    <option value="bootcamp">Bootcamp</option>
                                </select>
                            </div>
                            <div>
                                <label for="courseForm-duration_hours" class="block text-sm font-medium text-surface-700 mb-1.5">Duration (hours)</label>
                    <Input id="courseForm-duration_hours" v-model="courseForm.duration_hours" type="number" step="0.5" min="0" />
                            </div>
                            <div>
                                <label for="courseForm-price" class="block text-sm font-medium text-surface-700 mb-1.5">Price (NGN)</label>
                    <Input id="courseForm-price" v-model="courseForm.price" type="number" min="0" />
                            </div>
                            <div>
                                <label for="courseForm-discount_price" class="block text-sm font-medium text-surface-700 mb-1.5">Discount Price</label>
                    <Input id="courseForm-discount_price" v-model="courseForm.discount_price" type="number" min="0" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Learning Outcomes -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-surface-900 mb-5">Learning Outcomes</h2>
                    <div class="space-y-3">
                        <div v-for="(outcome, i) in courseForm.learning_outcomes" :key="i" class="flex items-center gap-2">
                            <Input :aria-label="`Learning outcome ${i + 1}`" v-model="courseForm.learning_outcomes[i]" class="flex-1" />
                            <button type="button" :aria-label="`Remove outcome ${i + 1}`" @click="courseForm.learning_outcomes.splice(i, 1)" class="p-2 text-surface-400 hover:text-danger-500">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                        <Button type="button" variant="ghost" size="sm" @click="courseForm.learning_outcomes.push('')">
                            <Plus class="w-4 h-4 mr-1" /> Add Outcome
                        </Button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" variant="primary" :disabled="courseForm.processing">
                        {{ courseForm.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>

        <CurriculumBuilder v-if="activeTab === 'curriculum'" :course="course" workspace="admin" :disabled="course.is_published || !!course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" />

        <DripSettings v-if="activeTab === 'drip'" :course="course" :disabled="course.is_published || !!course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" />

        <!-- Tab: Resources -->
        <div v-if="activeTab === 'resources'" class="space-y-4">
            <!-- Upload Resource -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
                <form @submit.prevent="uploadResource" class="space-y-3">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <Input aria-label="Resource title" v-model="resourceForm.title" placeholder="Resource title" />
                        <select aria-label="Resource type" v-model="resourceForm.type" class="rounded-xl border-surface-200 text-sm">
                            <option value="template">Template</option>
                            <option value="checklist">Checklist</option>
                            <option value="cheat_sheet">Cheat Sheet</option>
                            <option value="formula_sheet">Formula Sheet</option>
                            <option value="case_study">Case Study</option>
                            <option value="playbook">Playbook</option>
                        </select>
                        <Input aria-label="Resource description" v-model="resourceForm.description" placeholder="Description (optional)" />
                    </div>
                    <div class="flex items-center gap-3">
                        <input
                            ref="resourceFileInput"
                            type="file"
                            class="text-sm text-surface-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                            @change="handleResourceFile"
                        />
                        <Button type="submit" variant="primary" size="sm" :disabled="resourceForm.processing || !resourceForm.title || !resourceForm.file">
                            Upload
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Resources List -->
            <div v-if="course.resources?.length" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
                <div class="divide-y divide-surface-100">
                    <div v-for="resource in course.resources" :key="resource.id" class="flex items-center justify-between px-5 py-4 hover:bg-surface-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center">
                                <FileText class="w-5 h-5 text-primary-600" />
                            </div>
                            <div>
                                <p class="font-medium text-surface-800 text-sm">{{ resource.title }}</p>
                                <p class="text-xs text-surface-400">{{ resource.type.replace('_', ' ') }} · {{ resource.formatted_size || '' }}</p>
                            </div>
                        </div>
                        <button class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors" @click="deleteResource(resource)">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-12 text-center">
                <FileText class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No resources yet</p>
                <p class="text-sm text-surface-400 mt-1">Upload templates, checklists and other materials.</p>
            </div>
        </div>
        </fieldset>
    </div>
</template>

<script setup>
import CurriculumBuilder from '@/Components/Authoring/CurriculumBuilder.vue';
import DripSettings from '@/Components/Authoring/DripSettings.vue';
import RichTextEditor from '@/Components/Authoring/RichTextEditor.vue';
import CourseRevisionBanner from '@/Components/Admin/CourseRevisionBanner.vue';
import CourseReadiness from '@/Components/Admin/CourseReadiness.vue';
import { ref } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import {
    ArrowLeft, Plus, X, Eye, EyeOff, Pencil, Trash2, Layers, FileText,
    ChevronUp, ChevronDown, Users,
} from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    readinessIssues: { type: Array, default: () => [] },
    course: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'details', label: 'Details' },
    { key: 'curriculum', label: 'Curriculum' },
    { key: 'drip', label: 'Drip' },
    { key: 'resources', label: 'Resources' },
];
const requestedTab = new URL(usePage().url, window.location.origin).searchParams.get('tab');
const activeTab = ref(tabs.some(tab => tab.key === requestedTab) ? requestedTab : 'details');

// Course form
const courseForm = useForm({
    title: props.course.title || '',
    subtitle: props.course.subtitle || '',
    description: props.course.description || '',
    category_id: props.course.category_id || '',
    level: props.course.level || 'beginner',
    type: props.course.type || 'self_paced',
    duration_hours: props.course.duration_hours || '',
    price: props.course.price ?? 0,
    discount_price: props.course.discount_price || '',
    learning_outcomes: props.course.learning_outcomes?.length ? [...props.course.learning_outcomes] : [''],
    prerequisites: props.course.prerequisites?.length ? [...props.course.prerequisites] : [],
    tags: props.course.tags?.length ? [...props.course.tags] : [],
});

const updateCourse = () => {
    courseForm.learning_outcomes = courseForm.learning_outcomes.filter(o => o.trim());
    courseForm.prerequisites = courseForm.prerequisites.filter(p => p.trim());
    courseForm.put(`/admin/courses/${props.course.id}`);
};

const publishCourse = () => router.put(`/admin/courses/${props.course.id}/publish`);
const unpublishCourse = () => router.put(`/admin/courses/${props.course.id}/unpublish`);

// Module form
const moduleForm = useForm({ title: '', description: '' });

const addModule = () => {
    moduleForm.post(`/admin/courses/${props.course.id}/modules`, {
        preserveScroll: true,
        onSuccess: () => moduleForm.reset(),
    });
};

const moveModule = (index, direction) => {
    const modules = [...props.course.modules];
    const newIndex = index + direction;
    [modules[index], modules[newIndex]] = [modules[newIndex], modules[index]];
    const order = modules.map(m => m.id);
    router.post(`/admin/courses/${props.course.id}/modules/reorder`, { order }, { preserveScroll: true });
};

const deleteModule = (mod) => {
    if (confirm(`Delete module "${mod.title}" and all its lessons?`)) {
        router.delete(`/admin/modules/${mod.id}`, { preserveScroll: true });
    }
};

const deleteLesson = (lesson) => {
    if (confirm(`Delete lesson "${lesson.title}"?`)) {
        router.delete(`/admin/lessons/${lesson.id}`, { preserveScroll: true });
    }
};

// Resource form
const resourceForm = useForm({ title: '', type: 'template', description: '', file: null });

const handleResourceFile = (e) => {
    resourceForm.file = e.target.files[0] || null;
};

const uploadResource = () => {
    resourceForm.post(`/admin/courses/${props.course.id}/resources`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resourceForm.reset();
        },
    });
};

const deleteResource = (resource) => {
    if (confirm(`Delete resource "${resource.title}"?`)) {
        router.delete(`/admin/resources/${resource.id}`, { preserveScroll: true });
    }
};
</script>

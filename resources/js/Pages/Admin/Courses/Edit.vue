<template>
    <Head :title="`Edit: ${course.title}`" />

    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link href="/admin/courses" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
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
            <div class="flex gap-2">
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

        <!-- Tab: Details -->
        <div v-if="activeTab === 'details'">
            <form @submit.prevent="updateCourse" class="space-y-6">
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-surface-900 mb-5">Basic Information</h2>
                    <div class="grid gap-5">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Title *</label>
                            <Input v-model="courseForm.title" :error="courseForm.errors.title" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Subtitle</label>
                            <Input v-model="courseForm.subtitle" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Description</label>
                            <textarea v-model="courseForm.description" rows="4" class="w-full rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500 text-sm" />
                        </div>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Category</label>
                                <select v-model="courseForm.category_id" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Level</label>
                                <select v-model="courseForm.level" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Type</label>
                                <select v-model="courseForm.type" class="w-full rounded-xl border-surface-200 text-sm">
                                    <option value="free">Free</option>
                                    <option value="premium">Premium</option>
                                    <option value="certified">Certified</option>
                                    <option value="enterprise">Enterprise</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Duration (hours)</label>
                                <Input v-model="courseForm.duration_hours" type="number" step="0.5" min="0" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Price (NGN)</label>
                                <Input v-model="courseForm.price" type="number" min="0" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Discount Price</label>
                                <Input v-model="courseForm.discount_price" type="number" min="0" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Learning Outcomes -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-surface-900 mb-5">Learning Outcomes</h2>
                    <div class="space-y-3">
                        <div v-for="(outcome, i) in courseForm.learning_outcomes" :key="i" class="flex items-center gap-2">
                            <Input v-model="courseForm.learning_outcomes[i]" class="flex-1" />
                            <button type="button" @click="courseForm.learning_outcomes.splice(i, 1)" class="p-2 text-surface-400 hover:text-danger-500">
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

        <!-- Tab: Curriculum -->
        <div v-if="activeTab === 'curriculum'" class="space-y-4">
            <!-- Add Module -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
                <form @submit.prevent="addModule" class="flex flex-col sm:flex-row gap-3">
                    <Input v-model="moduleForm.title" placeholder="New module title..." class="flex-1" />
                    <Input v-model="moduleForm.description" placeholder="Description (optional)" class="flex-1" />
                    <Button type="submit" variant="primary" size="md" :disabled="moduleForm.processing || !moduleForm.title">
                        <Plus class="w-4 h-4 mr-1" /> Add Module
                    </Button>
                </form>
            </div>

            <!-- Modules List -->
            <div v-for="(mod, mi) in course.modules" :key="mod.id" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
                <!-- Module header -->
                <div class="flex items-center justify-between p-5 border-b border-surface-100">
                    <div class="flex items-center gap-3">
                        <div class="flex flex-col gap-0.5 text-surface-300">
                            <button class="hover:text-surface-600 transition-colors" @click="moveModule(mi, -1)" :disabled="mi === 0">
                                <ChevronUp class="w-4 h-4" />
                            </button>
                            <button class="hover:text-surface-600 transition-colors" @click="moveModule(mi, 1)" :disabled="mi === course.modules.length - 1">
                                <ChevronDown class="w-4 h-4" />
                            </button>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-2 py-0.5 rounded">Module {{ mi + 1 }}</span>
                                <h3 class="font-semibold text-surface-900">{{ mod.title }}</h3>
                            </div>
                            <p v-if="mod.description" class="text-xs text-surface-400 mt-0.5">{{ mod.description }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Link :href="`/admin/modules/${mod.id}/lessons/create`">
                            <Button variant="ghost" size="sm">
                                <Plus class="w-4 h-4 mr-1" /> Lesson
                            </Button>
                        </Link>
                        <button class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors" @click="deleteModule(mod)">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Lessons -->
                <div v-if="mod.lessons?.length" class="divide-y divide-surface-100">
                    <div v-for="(lesson, li) in mod.lessons" :key="lesson.id" class="flex items-center justify-between px-5 py-3 hover:bg-surface-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-surface-100 flex items-center justify-center text-surface-400 text-xs font-semibold">
                                {{ li + 1 }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-surface-800">{{ lesson.title }}</p>
                                <div class="flex items-center gap-2 text-xs text-surface-400 mt-0.5">
                                    <span class="capitalize">{{ lesson.content_type }}</span>
                                    <span v-if="lesson.duration_minutes">· {{ lesson.duration_minutes }} min</span>
                                    <Badge v-if="lesson.is_free_preview" variant="info" size="xs">Preview</Badge>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <Link :href="`/admin/lessons/${lesson.id}/edit`">
                                <button class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors">
                                    <Pencil class="w-3.5 h-3.5" />
                                </button>
                            </Link>
                            <button class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors" @click="deleteLesson(lesson)">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="p-8 text-center text-sm text-surface-400">
                    No lessons yet.
                    <Link :href="`/admin/modules/${mod.id}/lessons/create`" class="text-primary-600 hover:underline ml-1">Add one</Link>
                </div>
            </div>

            <div v-if="!course.modules?.length" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-12 text-center">
                <Layers class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No modules yet</p>
                <p class="text-sm text-surface-400 mt-1">Use the form above to add your first module.</p>
            </div>
        </div>

        <!-- Tab: Resources -->
        <div v-if="activeTab === 'resources'" class="space-y-4">
            <!-- Upload Resource -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
                <form @submit.prevent="uploadResource" class="space-y-3">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <Input v-model="resourceForm.title" placeholder="Resource title" />
                        <select v-model="resourceForm.type" class="rounded-xl border-surface-200 text-sm">
                            <option value="template">Template</option>
                            <option value="checklist">Checklist</option>
                            <option value="cheat_sheet">Cheat Sheet</option>
                            <option value="formula_sheet">Formula Sheet</option>
                            <option value="case_study">Case Study</option>
                            <option value="playbook">Playbook</option>
                        </select>
                        <Input v-model="resourceForm.description" placeholder="Description (optional)" />
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
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import {
    ArrowLeft, Plus, X, Eye, EyeOff, Pencil, Trash2, Layers, FileText,
    ChevronUp, ChevronDown,
} from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    course: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'details', label: 'Details' },
    { key: 'curriculum', label: 'Curriculum' },
    { key: 'resources', label: 'Resources' },
];
const activeTab = ref('details');

// Course form
const courseForm = useForm({
    title: props.course.title || '',
    subtitle: props.course.subtitle || '',
    description: props.course.description || '',
    category_id: props.course.category_id || '',
    level: props.course.level || 'beginner',
    type: props.course.type || 'free',
    duration_hours: props.course.duration_hours || '',
    price: props.course.price || '',
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

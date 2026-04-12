<template>
    <Head :title="`Edit: ${course.title}`" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <Link href="/facilitator/courses" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </Link>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Edit Course</h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <Badge :variant="approvalBadgeVariant" size="sm">{{ approvalLabel }}</Badge>
                        <span v-if="course.is_published" class="text-xs text-success-600 font-medium">Published</span>
                    </div>
                </div>
            </div>

            <!-- Submit for Review -->
            <button
                v-if="['draft', 'rejected'].includes(course.approval_status)"
                @click="submitForReview"
                class="px-4 py-2 bg-amber-500 text-white rounded-xl text-sm font-semibold hover:bg-amber-600 transition-colors flex items-center gap-2"
            >
                <Send class="w-4 h-4" />
                Submit for Review
            </button>
            <span v-else-if="course.approval_status === 'pending_review'"
                  class="px-4 py-2 bg-blue-50 text-blue-700 rounded-xl text-sm font-medium">
                Under Admin Review
            </span>
        </div>

        <!-- Rejection Notice -->
        <div v-if="course.approval_status === 'rejected' && course.rejection_reason"
             class="p-4 bg-danger-50 border border-danger-200 rounded-xl flex items-start gap-3">
            <AlertCircle class="w-5 h-5 text-danger-600 flex-shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold text-danger-800 text-sm">Course was rejected</p>
                <p class="text-sm text-danger-700 mt-0.5">{{ course.rejection_reason }}</p>
                <p class="text-xs text-danger-500 mt-1">Please address the feedback, then re-submit for review.</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 bg-surface-100 p-1 rounded-xl w-fit">
            <button
                v-for="tab in ['Details', 'Modules & Lessons', 'Thumbnail']"
                :key="tab"
                @click="activeTab = tab"
                :class="[
                    'px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                    activeTab === tab ? 'bg-white shadow-sm text-surface-900' : 'text-surface-500 hover:text-surface-700',
                ]"
            >{{ tab }}</button>
        </div>

        <!-- ─── DETAILS TAB ─── -->
        <form v-if="activeTab === 'Details'" @submit.prevent="saveDetails" class="space-y-6">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Basic Information</h2>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Course Title *</label>
                    <Input v-model="detailForm.title" :error="detailForm.errors.title" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Subtitle</label>
                    <Input v-model="detailForm.subtitle" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Description *</label>
                    <textarea v-model="detailForm.description" rows="5" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Category</label>
                        <select v-model="detailForm.category_id" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">Select category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Level</label>
                        <select v-model="detailForm.level" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Type</label>
                        <select v-model="detailForm.type" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="self_paced">Self-paced</option>
                            <option value="cohort_based">Cohort-based</option>
                            <option value="live">Live</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Duration (hours)</label>
                        <Input v-model="detailForm.duration_hours" type="number" min="0" />
                    </div>
                </div>

                <!-- Pricing -->
                <div class="pt-2 border-t border-surface-100">
                    <h3 class="font-medium text-surface-700 mb-3">Pricing</h3>
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" id="edit_free" v-model="isFree" class="rounded text-primary-600" />
                        <label for="edit_free" class="text-sm text-surface-700">Free course</label>
                    </div>
                    <div v-if="!isFree" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Price (₦)</label>
                            <Input v-model="detailForm.price" type="number" min="0" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Discounted Price (₦)</label>
                            <Input v-model="detailForm.discounted_price" type="number" min="0" />
                        </div>
                    </div>
                </div>

                <!-- Learning Outcomes -->
                <div class="pt-2 border-t border-surface-100">
                    <h3 class="font-medium text-surface-700 mb-3">Learning Outcomes</h3>
                    <div v-for="(outcome, i) in detailForm.learning_outcomes" :key="i" class="flex gap-2 mb-2">
                        <Input v-model="detailForm.learning_outcomes[i]" :placeholder="`Outcome ${i + 1}`" class="flex-1" />
                        <button type="button" @click="removeOutcome(i)" class="p-2 text-surface-400 hover:text-danger-600 rounded-lg hover:bg-danger-50">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <button type="button" @click="addOutcome" class="flex items-center gap-2 text-sm text-primary-600 font-medium hover:text-primary-700">
                        <Plus class="w-4 h-4" /> Add outcome
                    </button>
                </div>
            </div>

            <div class="flex justify-end">
                <Button type="submit" variant="primary" :disabled="detailForm.processing">
                    {{ detailForm.processing ? 'Saving…' : 'Save Changes' }}
                </Button>
            </div>
        </form>

        <!-- ─── MODULES & LESSONS TAB ─── -->
        <div v-else-if="activeTab === 'Modules & Lessons'" class="space-y-4">
            <!-- Module list -->
            <div
                v-for="(mod, mi) in localModules"
                :key="mod.id || mod._key"
                class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden"
            >
                <!-- Module Header -->
                <div class="flex items-center gap-3 px-5 py-4 bg-surface-50 border-b border-surface-100">
                    <div class="w-7 h-7 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        {{ mi + 1 }}
                    </div>
                    <input
                        v-model="mod.title"
                        placeholder="Module title"
                        class="flex-1 bg-transparent font-semibold text-surface-900 text-sm focus:outline-none"
                        @blur="saveModule(mod)"
                    />
                    <button @click="toggleModule(mi)" class="p-1.5 rounded-lg hover:bg-surface-200 text-surface-400">
                        <ChevronDown v-if="!mod._open" class="w-4 h-4" />
                        <ChevronUp v-else class="w-4 h-4" />
                    </button>
                    <button @click="deleteModule(mod, mi)" class="p-1.5 rounded-lg hover:bg-danger-50 text-surface-400 hover:text-danger-600">
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>

                <!-- Lessons -->
                <div v-if="mod._open" class="divide-y divide-surface-100">
                    <div
                        v-for="(lesson, li) in mod.lessons"
                        :key="lesson.id || lesson._key"
                        class="flex items-center gap-3 px-5 py-3"
                    >
                        <GripVertical class="w-4 h-4 text-surface-300 flex-shrink-0" />
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <input
                                v-model="lesson.title"
                                placeholder="Lesson title"
                                class="text-sm text-surface-800 focus:outline-none border-b border-transparent focus:border-surface-300"
                                @blur="saveLesson(mod, lesson)"
                            />
                            <select
                                v-model="lesson.content_type"
                                class="text-xs border border-surface-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                @change="saveLesson(mod, lesson)"
                            >
                                <option value="video">Video</option>
                                <option value="text">Text/Article</option>
                                <option value="pdf">PDF</option>
                                <option value="audio">Audio</option>
                                <option value="quiz">Quiz</option>
                            </select>
                            <Input
                                v-model="lesson.content_url"
                                placeholder="Content URL"
                                class="text-xs"
                                @blur="saveLesson(mod, lesson)"
                            />
                        </div>
                        <label class="flex items-center gap-1 text-xs text-surface-500 cursor-pointer">
                            <input type="checkbox" v-model="lesson.is_free_preview" class="rounded text-primary-600" @change="saveLesson(mod, lesson)" />
                            Free preview
                        </label>
                        <button @click="deleteLesson(mod, lesson, li)" class="p-1.5 rounded-lg hover:bg-danger-50 text-surface-400 hover:text-danger-600">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Add Lesson -->
                    <button
                        @click="addLesson(mod)"
                        class="w-full px-5 py-3 flex items-center gap-2 text-sm text-primary-600 hover:bg-primary-50 transition-colors font-medium"
                    >
                        <Plus class="w-4 h-4" /> Add Lesson
                    </button>
                </div>
            </div>

            <!-- Add Module -->
            <button
                @click="addModule"
                class="w-full py-4 border-2 border-dashed border-surface-300 rounded-2xl flex items-center justify-center gap-2 text-sm text-surface-500 hover:border-primary-400 hover:text-primary-600 hover:bg-primary-50 transition-all font-medium"
            >
                <Plus class="w-4 h-4" />
                Add Module
            </button>
        </div>

        <!-- ─── THUMBNAIL TAB ─── -->
        <div v-else-if="activeTab === 'Thumbnail'" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-surface-800">Course Thumbnail</h2>

            <!-- Preview -->
            <div class="w-full max-w-xs mx-auto aspect-video rounded-xl bg-surface-100 overflow-hidden border">
                <img v-if="thumbnailPreview || course.thumbnail" :src="thumbnailPreview || `/storage/${course.thumbnail}`" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex flex-col items-center justify-center text-surface-400 gap-2">
                    <ImageIcon class="w-8 h-8" />
                    <p class="text-sm">No thumbnail</p>
                </div>
            </div>

            <form @submit.prevent="saveThumbnail" class="space-y-4">
                <input
                    type="file"
                    accept="image/*"
                    @change="handleThumbnailChange"
                    class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                />
                <Button type="submit" variant="primary" :disabled="thumbForm.processing || !thumbForm.thumbnail">
                    {{ thumbForm.processing ? 'Uploading…' : 'Upload Thumbnail' }}
                </Button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import {
    ArrowLeft, Plus, X, Trash2, Send, AlertCircle,
    ChevronDown, ChevronUp, GripVertical, Image as ImageIcon,
} from 'lucide-vue-next';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    course: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const activeTab = ref('Details');
const isFree = ref(!props.course.price || Number(props.course.price) === 0);

// ─── Detail Form ───
const detailForm = useForm({
    title: props.course.title,
    subtitle: props.course.subtitle || '',
    description: props.course.description || '',
    category_id: props.course.category_id || '',
    level: props.course.level || 'beginner',
    type: props.course.type || 'self_paced',
    duration_hours: props.course.duration_hours || '',
    price: props.course.price || '',
    discounted_price: props.course.discounted_price || '',
    learning_outcomes: props.course.learning_outcomes?.length ? [...props.course.learning_outcomes] : [''],
});

watch(isFree, v => { if (v) { detailForm.price = ''; detailForm.discounted_price = ''; } });

function addOutcome() { detailForm.learning_outcomes.push(''); }
function removeOutcome(i) { detailForm.learning_outcomes.splice(i, 1); }

function saveDetails() {
    detailForm.learning_outcomes = detailForm.learning_outcomes.filter(o => o.trim());
    detailForm.put(`/facilitator/courses/${props.course.id}`);
}

// ─── Approval ───
const approvalBadgeVariant = computed(() => ({
    draft: 'secondary', pending_review: 'info', approved: 'success', rejected: 'danger',
}[props.course.approval_status] || 'secondary'));

const approvalLabel = computed(() => ({
    draft: 'Draft', pending_review: 'Pending Review', approved: 'Approved', rejected: 'Rejected',
}[props.course.approval_status] || props.course.approval_status));

function submitForReview() {
    if (!confirm('Submit this course for admin review? You will not be able to edit it until reviewed.')) return;
    router.post(`/facilitator/courses/${props.course.id}/submit-for-review`, {}, { preserveScroll: true });
}

// ─── Modules & Lessons ───
let _keyCounter = 0;
const localModules = ref(
    (props.course.modules || []).map(m => ({
        ...m,
        _open: true,
        _key: ++_keyCounter,
        lessons: (m.lessons || []).map(l => ({ ...l, _key: ++_keyCounter })),
    }))
);

function toggleModule(i) { localModules.value[i]._open = !localModules.value[i]._open; }

function addModule() {
    const mod = { id: null, title: 'New Module', _open: true, _key: ++_keyCounter, lessons: [] };
    localModules.value.push(mod);
    // Persist
    router.post(`/facilitator/courses/${props.course.id}/modules`, { title: 'New Module', order: localModules.value.length }, {
        preserveState: false,
        onSuccess: () => { /* Inertia reloads props */ },
    });
}

function saveModule(mod) {
    if (!mod.id) return;
    router.put(`/facilitator/courses/${props.course.id}/modules/${mod.id}`, { title: mod.title }, { preserveState: true });
}

function deleteModule(mod, mi) {
    if (!confirm('Delete this module and all its lessons?')) return;
    localModules.value.splice(mi, 1);
    if (mod.id) router.delete(`/facilitator/courses/${props.course.id}/modules/${mod.id}`, { preserveState: true });
}

function addLesson(mod) {
    const lesson = { id: null, title: 'New Lesson', content_type: 'video', content_url: '', is_free_preview: false, _key: ++_keyCounter };
    if (!mod.lessons) mod.lessons = [];
    mod.lessons.push(lesson);
    if (mod.id) {
        router.post(`/facilitator/courses/${props.course.id}/modules/${mod.id}/lessons`, {
            title: 'New Lesson', content_type: 'video', order: mod.lessons.length,
        }, { preserveState: false });
    }
}

function saveLesson(mod, lesson) {
    if (!lesson.id || !mod.id) return;
    router.put(`/facilitator/courses/${props.course.id}/lessons/${lesson.id}`, {
        title: lesson.title,
        content_type: lesson.content_type,
        content_url: lesson.content_url,
        is_free_preview: lesson.is_free_preview,
    }, { preserveState: true });
}

function deleteLesson(mod, lesson, li) {
    if (!confirm('Delete this lesson?')) return;
    mod.lessons.splice(li, 1);
    if (lesson.id && mod.id) {
        router.delete(`/facilitator/courses/${props.course.id}/lessons/${lesson.id}`, { preserveState: true });
    }
}

// ─── Thumbnail ───
const thumbnailPreview = ref(null);
const thumbForm = useForm({ thumbnail: null });

function handleThumbnailChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    thumbForm.thumbnail = file;
    thumbnailPreview.value = URL.createObjectURL(file);
}

function saveThumbnail() {
    thumbForm.post(`/facilitator/courses/${props.course.id}/thumbnail`, {
        forceFormData: true,
        onSuccess: () => { thumbnailPreview.value = null; },
    });
}
</script>

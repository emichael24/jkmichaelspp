<template>
    <Head :title="`Edit: ${course.title}`" />

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <Link aria-label="Back to courses" href="/facilitator/courses" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
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

        <CourseRevisionBanner :course="course" workspace="facilitator" />
        <CourseReadiness :issues="readinessIssues" :status="course.approval_status" :published="course.is_published" />

        <!-- Tabs -->
        <div class="flex gap-1 bg-surface-100 p-1 rounded-xl w-fit">
            <button
                v-for="tab in ['Details', 'Modules & Lessons', 'Drip', 'Thumbnail']"
                :key="tab"
                @click="activeTab = tab"
                :class="[
                    'px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                    activeTab === tab ? 'bg-white shadow-sm text-surface-900' : 'text-surface-500 hover:text-surface-700',
                ]"
            >{{ tab }}</button>
        </div>

        <fieldset :disabled="course.is_published || course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" class="space-y-6 min-w-0">
        <!-- ─── DETAILS TAB ─── -->
        <form v-if="activeTab === 'Details'" @submit.prevent="saveDetails" class="space-y-6">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Basic Information</h2>

                <div>
                    <label for="detailForm-title" class="block text-sm font-medium text-surface-700 mb-1.5">Course Title *</label>
                    <Input id="detailForm-title" v-model="detailForm.title" :error="detailForm.errors.title" />
                </div>
                <div>
                    <label for="detailForm-subtitle" class="block text-sm font-medium text-surface-700 mb-1.5">Subtitle</label>
                    <Input id="detailForm-subtitle" v-model="detailForm.subtitle" />
                </div>
                <div>
                    <label for="detailForm-description" class="block text-sm font-medium text-surface-700 mb-1.5">Description *</label>
                    <RichTextEditor v-model="detailForm.description" label="Course description" placeholder="Describe the transformation learners can expect, who this course is for, and what makes it valuable." compact :disabled="course.is_published || !!course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="detailForm-category_id" class="block text-sm font-medium text-surface-700 mb-1.5">Category</label>
                    <select id="detailForm-category_id" v-model="detailForm.category_id" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="">Select category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="detailForm-level" class="block text-sm font-medium text-surface-700 mb-1.5">Level</label>
                    <select id="detailForm-level" v-model="detailForm.level" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <div>
                        <label for="detailForm-type" class="block text-sm font-medium text-surface-700 mb-1.5">Type</label>
                    <select id="detailForm-type" v-model="detailForm.type" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="self_paced">Self-paced</option>
                            <option value="certified">Certified</option><option value="bootcamp">Bootcamp</option>
                            <option value="live">Live</option>
                        </select>
                    </div>
                    <div>
                        <label for="detailForm-duration_hours" class="block text-sm font-medium text-surface-700 mb-1.5">Duration (hours)</label>
                    <Input id="detailForm-duration_hours" v-model="detailForm.duration_hours" type="number" min="0" />
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
                            <label for="detailForm-price" class="block text-sm font-medium text-surface-700 mb-1.5">Price (₦)</label>
                    <Input id="detailForm-price" v-model="detailForm.price" type="number" min="0" />
                        </div>
                        <div>
                            <label for="detailForm-discount_price" class="block text-sm font-medium text-surface-700 mb-1.5">Discounted Price (₦)</label>
                    <Input id="detailForm-discount_price" v-model="detailForm.discount_price" type="number" min="0" />
                        </div>
                    </div>
                </div>

                <!-- Learning Outcomes -->
                <div class="pt-2 border-t border-surface-100">
                    <h3 class="font-medium text-surface-700 mb-3">Learning Outcomes</h3>
                    <div v-for="(outcome, i) in detailForm.learning_outcomes" :key="i" class="flex gap-2 mb-2">
                        <Input :aria-label="`Learning outcome ${i + 1}`" v-model="detailForm.learning_outcomes[i]" :placeholder="`Outcome ${i + 1}`" class="flex-1" />
                        <button type="button" :aria-label="`Remove outcome ${i + 1}`" @click="removeOutcome(i)" class="p-2 text-surface-400 hover:text-danger-600 rounded-lg hover:bg-danger-50">
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

        <CurriculumBuilder v-else-if="activeTab === 'Modules & Lessons'" :course="course" workspace="facilitator" :disabled="course.is_published || !!course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" />

        <DripSettings v-else-if="activeTab === 'Drip'" :course="course" :disabled="course.is_published || !!course.published_at || ['approved', 'pending_review'].includes(course.approval_status)" />

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
                    type="file" aria-label="Course thumbnail"
                    accept="image/*"
                    @change="handleThumbnailChange"
                    class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                />
                <Button type="submit" variant="primary" :disabled="thumbForm.processing || !thumbForm.thumbnail">
                    {{ thumbForm.processing ? 'Uploading…' : 'Upload Thumbnail' }}
                </Button>
            </form>
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
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    readinessIssues: { type: Array, default: () => [] },
    course: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const activeTab = ref({ curriculum: 'Modules & Lessons', drip: 'Drip' }[new URL(usePage().url, window.location.origin).searchParams.get('tab')] || 'Details');
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
    price: props.course.price ?? 0,
    discount_price: props.course.discount_price || '',
    learning_outcomes: props.course.learning_outcomes?.length ? [...props.course.learning_outcomes] : [''],
});

watch(isFree, v => { if (v) { detailForm.price = 0; detailForm.discount_price = ''; } });

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
    router.post(`/facilitator/courses/${props.course.id}/submit-review`, {}, { preserveScroll: true });
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
    if (mod.id) router.delete(`/facilitator/courses/${props.course.id}/modules/${mod.id}`, { preserveState: false });
}

function addLesson(mod) {
    router.visit(`/facilitator/courses/${props.course.id}/modules/${mod.id}/lessons/create`);
}

function deleteLesson(mod, lesson, li) {
    if (!confirm('Delete this lesson?')) return;
    if (lesson.id && mod.id) {
        router.delete(`/facilitator/courses/${props.course.id}/modules/${mod.id}/lessons/${lesson.id}`, { preserveState: false });
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

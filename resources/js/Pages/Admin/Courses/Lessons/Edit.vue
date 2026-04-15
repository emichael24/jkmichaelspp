<template>
    <Head :title="`Edit: ${lesson.title}`" />

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center gap-3">
            <Link :href="`/admin/courses/${course.id}/edit`" class="rounded-lg p-2 transition-colors hover:bg-surface-100">
                <ArrowLeft class="h-5 w-5 text-surface-400" />
            </Link>
            <div>
                <p class="text-sm text-surface-400">{{ course.title }} -> {{ module.title }}</p>
                <h1 class="text-2xl font-bold text-surface-900">Edit Lesson</h1>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="rounded-2xl border border-surface-200/60 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-surface-900">Lesson Details</h2>
                <div class="grid gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Title *</label>
                        <Input v-model="form.title" :error="form.errors.title" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Content Type *</label>
                        <select v-model="form.content_type" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="text">Text / Article</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="pdf">PDF</option>
                            <option value="slides">Slides</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Duration (minutes)</label>
                        <Input v-model="form.duration_minutes" type="number" min="0" />
                    </div>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-surface-700">
                            <input v-model="form.is_free_preview" type="checkbox" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                            Free preview
                        </label>
                        <label class="flex items-center gap-2 text-sm text-surface-700">
                            <input v-model="form.is_downloadable" type="checkbox" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                            Downloadable
                        </label>
                        <label class="flex items-center gap-2 text-sm text-surface-700">
                            <input v-model="form.is_published" type="checkbox" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                            Published
                        </label>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/60 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-surface-900">Content</h2>
                <div class="grid gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Summary</label>
                        <textarea v-model="form.summary" rows="3" class="w-full rounded-xl border-surface-200 text-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div v-if="['text', 'mixed'].includes(form.content_type)">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Text Content</label>
                        <textarea v-model="form.text_content" rows="12" class="w-full rounded-xl border-surface-200 text-sm font-mono focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div v-if="['video', 'mixed'].includes(form.content_type)">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Video URL</label>
                        <Input v-model="form.video_url" />
                    </div>
                    <div v-if="['video', 'mixed'].includes(form.content_type)">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Video Transcript</label>
                        <textarea v-model="form.video_transcript" rows="4" class="w-full rounded-xl border-surface-200 text-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div v-if="['audio', 'mixed'].includes(form.content_type)">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Audio URL</label>
                        <Input v-model="form.audio_url" />
                    </div>
                    <div v-if="['pdf', 'mixed'].includes(form.content_type)">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">PDF URL</label>
                        <Input v-model="form.pdf_url" />
                    </div>
                    <div v-if="['slides', 'mixed'].includes(form.content_type)">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Slides URL</label>
                        <Input v-model="form.slides_url" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/60 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-surface-900">Interactive Package</h2>
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">SCORM Entry URL</label>
                        <Input v-model="form.scorm_path" />
                        <p class="mt-1 text-xs text-surface-400">Use the extracted entry file generated by the SCORM uploader.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">SCORM Version</label>
                        <select v-model="form.scorm_version" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="">Select version</option>
                            <option value="1.2">SCORM 1.2</option>
                            <option value="2004">SCORM 2004</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/60 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-surface-900">Access Scheduling</h2>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Unlock Rule</label>
                        <select v-model="form.unlock_type" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="immediate">Immediate</option>
                            <option value="date">Specific date and time</option>
                            <option value="days_after_enrollment">Days after enrollment</option>
                        </select>
                    </div>
                    <div v-if="form.unlock_type === 'date'">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Unlock At</label>
                        <Input v-model="form.unlock_at" type="datetime-local" />
                    </div>
                    <div v-if="form.unlock_type === 'days_after_enrollment'">
                        <label class="mb-1.5 block text-sm font-medium text-surface-700">Days After Enrollment</label>
                        <Input v-model="form.unlock_days_after_enrollment" type="number" min="1" max="365" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/60 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-surface-900">Key Terms</h2>
                <div class="space-y-3">
                    <div v-for="(term, i) in form.key_terms" :key="i" class="flex items-start gap-2">
                        <Input v-model="form.key_terms[i].term" placeholder="Term" class="w-1/3" />
                        <Input v-model="form.key_terms[i].definition" placeholder="Definition" class="flex-1" />
                        <button type="button" class="mt-1 p-2 text-surface-400 hover:text-danger-500" @click="form.key_terms.splice(i, 1)">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <Button type="button" variant="ghost" size="sm" @click="form.key_terms.push({ term: '', definition: '' })">
                        <Plus class="mr-1 h-4 w-4" /> Add Term
                    </Button>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="`/admin/courses/${course.id}/edit`">
                    <Button type="button" variant="ghost">Cancel</Button>
                </Link>
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save Lesson' }}
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    lesson: { type: Object, required: true },
    module: { type: Object, required: true },
    course: { type: Object, required: true },
});

const form = useForm({
    title: props.lesson.title || '',
    content_type: props.lesson.content_type || 'text',
    text_content: props.lesson.text_content || '',
    summary: props.lesson.summary || '',
    key_terms: props.lesson.key_terms?.length ? [...props.lesson.key_terms] : [],
    video_url: props.lesson.video_url || '',
    video_duration_seconds: props.lesson.video_duration_seconds || '',
    video_transcript: props.lesson.video_transcript || '',
    audio_url: props.lesson.audio_url || '',
    audio_duration_seconds: props.lesson.audio_duration_seconds || '',
    pdf_url: props.lesson.pdf_url || '',
    slides_url: props.lesson.slides_url || '',
    scorm_path: props.lesson.scorm_path || '',
    scorm_version: props.lesson.scorm_version || '',
    duration_minutes: props.lesson.duration_minutes || '',
    is_free_preview: props.lesson.is_free_preview || false,
    is_downloadable: props.lesson.is_downloadable || false,
    is_published: props.lesson.is_published ?? true,
    unlock_type: props.lesson.unlock_type || 'immediate',
    unlock_at: toDateTimeLocal(props.lesson.unlock_at),
    unlock_days_after_enrollment: props.lesson.unlock_days_after_enrollment || '',
});

function submit() {
    form.key_terms = form.key_terms.filter((term) => term.term.trim());
    form.put(`/admin/lessons/${props.lesson.id}`);
}

function toDateTimeLocal(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toISOString().slice(0, 16);
}
</script>

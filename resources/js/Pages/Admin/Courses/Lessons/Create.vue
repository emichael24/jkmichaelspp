<template>
    <Head :title="`Add Lesson to ${module.title}`" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <Link :href="`/admin/courses/${course.id}/edit`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-400" />
            </Link>
            <div>
                <p class="text-sm text-surface-400">{{ course.title }} → {{ module.title }}</p>
                <h1 class="text-2xl font-display font-bold text-surface-900">Add Lesson</h1>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Lesson Details</h2>
                <div class="grid gap-5">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Title *</label>
                        <Input v-model="form.title" placeholder="Lesson title" :error="form.errors.title" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Content Type *</label>
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
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Duration (minutes)</label>
                        <Input v-model="form.duration_minutes" type="number" min="0" placeholder="Estimated time" />
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
                    </div>
                </div>
            </div>

            <!-- Text Content -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Content</h2>
                <div class="grid gap-5">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Summary</label>
                        <textarea v-model="form.summary" rows="3" class="w-full rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500 text-sm" placeholder="Brief summary of this lesson" />
                    </div>
                    <div v-if="['text', 'mixed'].includes(form.content_type)">
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Text Content</label>
                        <textarea v-model="form.text_content" rows="12" class="w-full rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500 text-sm font-mono" placeholder="Lesson content (Markdown supported)" />
                    </div>
                    <div v-if="['video', 'mixed'].includes(form.content_type)">
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Video URL</label>
                        <Input v-model="form.video_url" placeholder="https://..." />
                    </div>
                    <div v-if="['video', 'mixed'].includes(form.content_type)">
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Video Transcript</label>
                        <textarea v-model="form.video_transcript" rows="4" class="w-full rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500 text-sm" placeholder="Full transcript..." />
                    </div>
                    <div v-if="['audio', 'mixed'].includes(form.content_type)">
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Audio URL</label>
                        <Input v-model="form.audio_url" placeholder="https://..." />
                    </div>
                    <div v-if="['pdf', 'mixed'].includes(form.content_type)">
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">PDF URL</label>
                        <Input v-model="form.pdf_url" placeholder="URL or storage path" />
                    </div>
                    <div v-if="['slides', 'mixed'].includes(form.content_type)">
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Slides URL</label>
                        <Input v-model="form.slides_url" placeholder="URL or storage path" />
                    </div>
                </div>
            </div>

            <!-- Key Terms -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Key Terms</h2>
                <div class="space-y-3">
                    <div v-for="(term, i) in form.key_terms" :key="i" class="flex items-start gap-2">
                        <Input v-model="form.key_terms[i].term" placeholder="Term" class="w-1/3" />
                        <Input v-model="form.key_terms[i].definition" placeholder="Definition" class="flex-1" />
                        <button type="button" @click="form.key_terms.splice(i, 1)" class="p-2 text-surface-400 hover:text-danger-500 mt-1">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <Button type="button" variant="ghost" size="sm" @click="form.key_terms.push({ term: '', definition: '' })">
                        <Plus class="w-4 h-4 mr-1" /> Add Term
                    </Button>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3">
                <Link :href="`/admin/courses/${course.id}/edit`">
                    <Button type="button" variant="ghost">Cancel</Button>
                </Link>
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Create Lesson' }}
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
    module: { type: Object, required: true },
    course: { type: Object, required: true },
});

const form = useForm({
    title: '',
    content_type: 'text',
    text_content: '',
    summary: '',
    key_terms: [],
    video_url: '',
    video_duration_seconds: '',
    video_transcript: '',
    audio_url: '',
    audio_duration_seconds: '',
    pdf_url: '',
    slides_url: '',
    duration_minutes: '',
    is_free_preview: false,
    is_downloadable: false,
});

const submit = () => {
    // Filter empty key terms
    form.key_terms = form.key_terms.filter(t => t.term.trim());
    form.post(`/admin/modules/${props.module.id}/lessons`);
};
</script>

<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ event: { type: Object, default: null } });

const isEdit = !!props.event;

const form = useForm({
    title: props.event?.title || '',
    description: props.event?.description || '',
    type: props.event?.type || 'webinar',
    platform: props.event?.platform || 'zoom',
    scheduled_at: props.event?.scheduled_at ? props.event.scheduled_at.substring(0, 16) : '',
    duration_minutes: props.event?.duration_minutes || 60,
    max_registrations: props.event?.max_registrations || '',
    price: props.event?.price || 0,
    meeting_url: props.event?.meeting_url || '',
    replay_url: props.event?.replay_url || '',
    speaker_name: props.event?.speaker_name || '',
    speaker_bio: props.event?.speaker_bio || '',
    is_published: props.event?.is_published ?? false,
});

function save() {
    if (isEdit) {
        form.put(route('admin.events.update', props.event.id));
    } else {
        form.post(route('admin.events.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Event' : 'Create Event'" />
    <div class="space-y-6">
        <div>
            <button @click="router.visit(route('admin.events.index'))" class="text-sm text-primary-600 hover:underline mb-2 block">← Back to Events</button>
            <h1 class="text-2xl font-display font-bold text-surface-900">{{ isEdit ? 'Edit Event' : 'Create Event' }}</h1>
        </div>

        <form @submit.prevent="save" class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <Card class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Event Title</label>
                        <input v-model="form.title" type="text" required class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="5" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                            <select v-model="form.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="webinar">Webinar</option>
                                <option value="bootcamp">Bootcamp</option>
                                <option value="industry_session">Industry Session</option>
                                <option value="workshop">Workshop</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Platform</label>
                            <select v-model="form.platform" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="zoom">Zoom</option>
                                <option value="teams">Microsoft Teams</option>
                                <option value="google_meet">Google Meet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Scheduled At</label>
                            <input v-model="form.scheduled_at" type="datetime-local" required class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Duration (minutes)</label>
                            <input v-model="form.duration_minutes" type="number" min="15" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                        </div>
                    </div>
                </Card>

                <Card title="Speaker" class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Speaker Name</label>
                        <input v-model="form.speaker_name" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Speaker Bio</label>
                        <textarea v-model="form.speaker_bio" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                </Card>
            </div>

            <div class="space-y-6">
                <Card class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Price (₦)</label>
                        <input v-model="form.price" type="number" min="0" step="0.01" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Max Registrations</label>
                        <input v-model="form.max_registrations" type="number" min="0" placeholder="Unlimited" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Meeting URL</label>
                        <input v-model="form.meeting_url" type="url" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Replay URL</label>
                        <input v-model="form.replay_url" type="url" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="form.is_published" type="checkbox" id="published" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        <label for="published" class="text-sm text-surface-700">Published</label>
                    </div>
                </Card>

                <Button type="submit" variant="primary" class="w-full" :disabled="form.processing">
                    {{ isEdit ? 'Update Event' : 'Create Event' }}
                </Button>
            </div>
        </form>
    </div>
</template>

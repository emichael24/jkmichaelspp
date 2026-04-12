<template>
    <Head :title="`Announcements – ${cohort.name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Announcements</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }}</p>
                </div>
            </div>
            <button @click="showCreate = true" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                New Announcement
            </button>
        </div>

        <!-- Announcements List -->
        <div v-if="announcements.data && announcements.data.length" class="space-y-4">
            <div
                v-for="announcement in announcements.data"
                :key="announcement.id"
                class="bg-white rounded-xl border border-surface-200/60 p-6"
                :class="announcement.is_pinned ? 'ring-2 ring-warning-200' : ''"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-start gap-2">
                        <Pin v-if="announcement.is_pinned" class="w-4 h-4 text-warning-500 shrink-0 mt-0.5" />
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ announcement.title }}</h3>
                            <p class="text-xs text-surface-400 mt-0.5">
                                {{ announcement.author?.first_name }} {{ announcement.author?.last_name }}
                                · {{ formatDate(announcement.published_at || announcement.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button @click="editAnnouncement(announcement)" class="p-1.5 rounded-lg text-surface-400 hover:text-primary-600 hover:bg-primary-50">
                            <Pencil class="w-3.5 h-3.5" />
                        </button>
                        <button @click="deleteAnnouncement(announcement)" class="p-1.5 rounded-lg text-surface-400 hover:text-danger-600 hover:bg-danger-50">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
                <p class="text-sm text-surface-700 whitespace-pre-line">{{ announcement.body }}</p>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Megaphone class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500">No announcements yet.</p>
        </div>

        <!-- Pagination -->
        <div v-if="announcements.links && announcements.last_page > 1" class="flex justify-center gap-1">
            <a
                v-for="link in announcements.links"
                :key="link.label"
                :href="link.url"
                :class="[
                    'px-3 py-1.5 rounded-lg text-sm',
                    link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100',
                    !link.url ? 'opacity-50 pointer-events-none' : '',
                ]"
                v-html="link.label"
            />
        </div>

        <!-- Create / Edit Modal -->
        <div v-if="showCreate || editTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">
                    {{ editTarget ? 'Edit Announcement' : 'New Announcement' }}
                </h3>
                <form @submit.prevent="submitAnnouncement">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                            <input v-model="form.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500" placeholder="Announcement title..." />
                            <p v-if="form.errors.title" class="text-xs text-danger-600 mt-1">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Body</label>
                            <textarea v-model="form.body" rows="5" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500" placeholder="Write your announcement..."></textarea>
                            <p v-if="form.errors.body" class="text-xs text-danger-600 mt-1">{{ form.errors.body }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_pinned" type="checkbox" class="rounded border-surface-300 text-warning-600" />
                            Pin this announcement
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="closeModal" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ editTarget ? 'Update' : 'Post' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, Pin, Pencil, Trash2, Megaphone,
} from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    announcements: { type: Object, default: () => ({ data: [] }) },
});

const showCreate = ref(false);
const editTarget = ref(null);

const form = useForm({
    title: '',
    body: '',
    is_pinned: false,
});

function editAnnouncement(announcement) {
    editTarget.value = announcement;
    form.title = announcement.title;
    form.body = announcement.body;
    form.is_pinned = announcement.is_pinned;
}

function closeModal() {
    showCreate.value = false;
    editTarget.value = null;
    form.reset();
}

function submitAnnouncement() {
    if (editTarget.value) {
        form.put(`/facilitator/cohorts/${props.cohort.id}/announcements/${editTarget.value.id}`, {
            onSuccess: closeModal,
        });
    } else {
        form.post(`/facilitator/cohorts/${props.cohort.id}/announcements`, {
            onSuccess: closeModal,
        });
    }
}

function deleteAnnouncement(announcement) {
    if (confirm('Delete this announcement?')) {
        router.delete(`/facilitator/cohorts/${props.cohort.id}/announcements/${announcement.id}`);
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>

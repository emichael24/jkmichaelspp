<template>
    <Head title="Meeting Hub" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Meeting Hub</h1>
                <p class="text-sm text-surface-500 mt-1">Schedule, track, and manage intern meetings</p>
            </div>
            <button @click="showScheduleModal = true" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition-colors shadow-sm">
                <Plus class="w-4 h-4" />
                Schedule Meeting
            </button>
        </div>

        <!-- Upcoming Meetings -->
        <div>
            <h2 class="text-lg font-display font-semibold text-surface-900 mb-4">Upcoming Meetings</h2>
            <div v-if="upcoming.length" class="space-y-4">
                <div v-for="meeting in upcoming" :key="meeting.id" class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <!-- Date tile -->
                        <div class="text-center shrink-0">
                            <div class="w-14 h-16 rounded-xl bg-primary-50 flex flex-col items-center justify-center">
                                <span class="text-[10px] font-medium text-primary-500 uppercase">{{ meeting.date.split(' ')[0] }}</span>
                                <span class="text-xl font-bold text-primary-700 leading-none">{{ meeting.date.split(' ')[1].replace(',','') }}</span>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-semibold text-surface-900">{{ meeting.title }}</h3>
                                    <p class="text-sm text-surface-500 mt-0.5">{{ meeting.time }} · {{ meeting.duration }} min</p>
                                </div>
                                <span :class="['px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase shrink-0', platformBadge(meeting.platform)]">
                                    {{ platformLabel(meeting.platform) }}
                                </span>
                            </div>

                            <p v-if="meeting.description" class="text-sm text-surface-600 mt-2">{{ meeting.description }}</p>

                            <!-- Attendees -->
                            <div class="flex items-center gap-2 mt-3">
                                <UsersIcon class="w-3.5 h-3.5 text-surface-400" />
                                <div class="flex -space-x-2">
                                    <div v-for="att in meeting.attendees.slice(0, 4)" :key="att.id" class="w-7 h-7 rounded-full bg-primary-100 border-2 border-white flex items-center justify-center" :title="att.name">
                                        <span class="text-[10px] font-bold text-primary-700">{{ att.name.charAt(0) }}</span>
                                    </div>
                                    <div v-if="meeting.attendees.length > 4" class="w-7 h-7 rounded-full bg-surface-100 border-2 border-white flex items-center justify-center">
                                        <span class="text-[10px] font-medium text-surface-500">+{{ meeting.attendees.length - 4 }}</span>
                                    </div>
                                </div>
                                <span class="text-xs text-surface-400">{{ meeting.attendees.length }} attendees</span>
                            </div>

                            <!-- Action buttons -->
                            <div class="flex items-center gap-2 mt-3">
                                <a v-if="meeting.url" :href="meeting.url" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-lg hover:bg-primary-100 transition-colors">
                                    <ExternalLink class="w-3.5 h-3.5" />
                                    Join Meeting
                                </a>
                                <button v-if="meeting.agenda" @click="viewDetails = meeting" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface-50 text-surface-600 text-xs font-medium rounded-lg hover:bg-surface-100 transition-colors">
                                    <FileText class="w-3.5 h-3.5" />
                                    View Agenda
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="bg-white rounded-xl border border-surface-200/60 p-10 text-center">
                <Calendar class="w-10 h-10 text-surface-300 mx-auto mb-3" />
                <p class="text-surface-500">No upcoming meetings scheduled.</p>
                <button @click="showScheduleModal = true" class="mt-3 text-sm text-primary-600 font-medium hover:text-primary-700">Schedule one now</button>
            </div>
        </div>

        <!-- Past Meetings -->
        <div v-if="past.length">
            <h2 class="text-lg font-display font-semibold text-surface-900 mb-4">Past Meetings</h2>
            <div class="space-y-3">
                <div v-for="meeting in past" :key="meeting.id" class="bg-white rounded-xl border border-surface-200/60 p-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-surface-100 flex items-center justify-center shrink-0">
                            <Check v-if="meeting.status === 'completed'" class="w-5 h-5 text-success-600" />
                            <Clock v-else class="w-5 h-5 text-surface-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-surface-800 truncate">{{ meeting.title }}</p>
                            <p class="text-xs text-surface-500">{{ meeting.date }} · {{ meeting.time }} · {{ meeting.attendees.length }} attendees</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="meeting.status !== 'completed'" @click="editNotes = meeting" class="px-3 py-1.5 text-xs font-medium bg-accent-50 text-accent-700 rounded-lg hover:bg-accent-100 transition-colors">
                                Add Notes
                            </button>
                            <span v-if="meeting.notes" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-success-50 text-success-700">
                                Notes ✓
                            </span>
                            <span v-if="meeting.action_items?.length" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary-50 text-primary-700">
                                {{ meeting.action_items.length }} actions
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Schedule Meeting Modal ═══ -->
        <div v-if="showScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="showScheduleModal = false" />
            <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl max-h-[85vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-display font-bold text-surface-900">Schedule Meeting</h2>
                        <button @click="showScheduleModal = false" class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center hover:bg-surface-200 transition-colors">
                            <X class="w-4 h-4 text-surface-500" />
                        </button>
                    </div>

                    <form @submit.prevent="scheduleMeeting" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-surface-700 mb-1">Title</label>
                            <input v-model="form.title" type="text" required class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g., Weekly Check-in" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-surface-700 mb-1">Description (optional)</label>
                            <textarea v-model="form.description" rows="2" class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500" placeholder="Brief description..." />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-surface-700 mb-1">Date & Time</label>
                                <input v-model="form.scheduled_at" type="datetime-local" required class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-surface-700 mb-1">Duration</label>
                                <select v-model="form.duration_minutes" class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500">
                                    <option :value="15">15 min</option>
                                    <option :value="30">30 min</option>
                                    <option :value="45">45 min</option>
                                    <option :value="60">1 hour</option>
                                    <option :value="90">1.5 hours</option>
                                    <option :value="120">2 hours</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-surface-700 mb-1">Platform</label>
                                <select v-model="form.platform" class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500">
                                    <option value="zoom">Zoom</option>
                                    <option value="teams">Microsoft Teams</option>
                                    <option value="google_meet">Google Meet</option>
                                    <option value="in_person">In Person</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-surface-700 mb-1">Meeting Link</label>
                                <input v-model="form.meeting_url" type="url" class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500" placeholder="https://..." />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-surface-700 mb-1">Agenda (optional)</label>
                            <textarea v-model="form.agenda" rows="3" class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500" placeholder="Meeting agenda points..." />
                        </div>

                        <!-- Intern selection -->
                        <div>
                            <label class="block text-sm font-semibold text-surface-700 mb-2">Attendees</label>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                <label v-for="intern in interns" :key="intern.id" class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-surface-50 cursor-pointer">
                                    <input type="checkbox" :value="intern.id" v-model="form.attendee_ids" class="rounded text-primary-600 focus:ring-primary-500" />
                                    <div>
                                        <p class="text-sm font-medium text-surface-800">{{ intern.name }}</p>
                                        <p class="text-xs text-surface-400">{{ intern.email }}</p>
                                    </div>
                                </label>
                            </div>
                            <p v-if="interns.length === 0" class="text-xs text-surface-400 mt-1">No active interns assigned to you.</p>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showScheduleModal = false" class="flex-1 px-4 py-2.5 border border-surface-200 text-surface-700 text-sm font-medium rounded-xl hover:bg-surface-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saving" class="flex-1 px-4 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition-colors disabled:opacity-50">
                                {{ saving ? 'Scheduling...' : 'Schedule Meeting' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══ Meeting Details / Notes Modal ═══ -->
        <div v-if="viewDetails || editNotes" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="viewDetails = null; editNotes = null" />
            <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl max-h-[85vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-display font-bold text-surface-900">{{ (viewDetails || editNotes).title }}</h2>
                        <button @click="viewDetails = null; editNotes = null" class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center hover:bg-surface-200 transition-colors">
                            <X class="w-4 h-4 text-surface-500" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center gap-4 text-sm text-surface-600">
                            <span class="flex items-center gap-1"><Calendar class="w-4 h-4" /> {{ (viewDetails || editNotes).date }}</span>
                            <span class="flex items-center gap-1"><Clock class="w-4 h-4" /> {{ (viewDetails || editNotes).time }}</span>
                            <span>{{ (viewDetails || editNotes).duration }} min</span>
                        </div>

                        <!-- Agenda -->
                        <div v-if="(viewDetails || editNotes).agenda">
                            <h3 class="text-sm font-semibold text-surface-700 mb-1">Agenda</h3>
                            <p class="text-sm text-surface-600 whitespace-pre-line bg-surface-50 rounded-lg p-3">{{ (viewDetails || editNotes).agenda }}</p>
                        </div>

                        <!-- Attendees -->
                        <div>
                            <h3 class="text-sm font-semibold text-surface-700 mb-2">Attendees</h3>
                            <div class="space-y-2">
                                <div v-for="att in (viewDetails || editNotes).attendees" :key="att.id" class="flex items-center gap-3 text-sm">
                                    <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
                                        <span class="text-[10px] font-bold text-primary-700">{{ att.name.charAt(0) }}</span>
                                    </div>
                                    <span class="text-surface-700">{{ att.name }}</span>
                                    <span :class="['ml-auto px-2 py-0.5 rounded-full text-[10px] font-semibold', att.rsvp === 'accepted' ? 'bg-success-50 text-success-700' : att.rsvp === 'declined' ? 'bg-danger-50 text-danger-700' : 'bg-surface-100 text-surface-500']">
                                        {{ att.rsvp }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Notes (editable for past meetings) -->
                        <div v-if="editNotes">
                            <h3 class="text-sm font-semibold text-surface-700 mb-1">Meeting Notes</h3>
                            <textarea v-model="notesForm.notes" rows="4" class="w-full px-3 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500" placeholder="Add meeting notes..." />

                            <h3 class="text-sm font-semibold text-surface-700 mt-3 mb-1">Action Items</h3>
                            <div class="space-y-2">
                                <div v-for="(item, i) in notesForm.action_items" :key="i" class="flex items-center gap-2">
                                    <input v-model="notesForm.action_items[i]" type="text" class="flex-1 px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500" />
                                    <button @click="notesForm.action_items.splice(i, 1)" class="text-danger-500 hover:text-danger-700">
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                                <button @click="notesForm.action_items.push('')" class="text-sm text-primary-600 hover:text-primary-700 font-medium">+ Add action item</button>
                            </div>

                            <button @click="saveNotesForMeeting" :disabled="saving" class="w-full mt-4 px-4 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition-colors disabled:opacity-50">
                                {{ saving ? 'Saving...' : 'Save Notes' }}
                            </button>
                        </div>

                        <!-- Existing notes (view mode) -->
                        <div v-if="viewDetails?.notes">
                            <h3 class="text-sm font-semibold text-surface-700 mb-1">Notes</h3>
                            <p class="text-sm text-surface-600 whitespace-pre-line bg-surface-50 rounded-lg p-3">{{ viewDetails.notes }}</p>
                        </div>
                        <div v-if="viewDetails?.action_items?.length">
                            <h3 class="text-sm font-semibold text-surface-700 mb-1">Action Items</h3>
                            <ul class="space-y-1">
                                <li v-for="(item, i) in viewDetails.action_items" :key="i" class="flex items-start gap-2 text-sm text-surface-600">
                                    <span class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">{{ i + 1 }}</span>
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Plus, Calendar, Clock, ExternalLink, FileText, X, Check,
    Users as UsersIcon, Video,
} from 'lucide-vue-next';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';

defineOptions({ layout: SponsorLayout });

const props = defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
    interns: { type: Array, default: () => [] },
});

const showScheduleModal = ref(false);
const viewDetails = ref(null);
const editNotes = ref(null);
const saving = ref(false);

const form = reactive({
    title: '',
    description: '',
    scheduled_at: '',
    duration_minutes: 30,
    platform: 'zoom',
    meeting_url: '',
    agenda: '',
    attendee_ids: [],
});

const notesForm = reactive({
    notes: '',
    action_items: [''],
});

function scheduleMeeting() {
    saving.value = true;
    router.post(route('sponsor.meetings.store'), form, {
        onSuccess: () => {
            showScheduleModal.value = false;
            Object.assign(form, { title: '', description: '', scheduled_at: '', duration_minutes: 30, platform: 'zoom', meeting_url: '', agenda: '', attendee_ids: [] });
        },
        onFinish: () => saving.value = false,
    });
}

function saveNotesForMeeting() {
    if (!editNotes.value) return;
    saving.value = true;
    router.post(route('sponsor.meetings.notes', editNotes.value.id), {
        notes: notesForm.notes,
        action_items: notesForm.action_items.filter(Boolean),
    }, {
        onSuccess: () => editNotes.value = null,
        onFinish: () => saving.value = false,
    });
}

function platformBadge(platform) {
    const map = {
        zoom: 'bg-blue-50 text-blue-700',
        teams: 'bg-indigo-50 text-indigo-700',
        google_meet: 'bg-success-50 text-success-700',
        in_person: 'bg-warning-50 text-warning-700',
        other: 'bg-surface-100 text-surface-600',
    };
    return map[platform] || map.other;
}

function platformLabel(platform) {
    const map = {
        zoom: 'Zoom',
        teams: 'Teams',
        google_meet: 'Google Meet',
        in_person: 'In Person',
        other: 'Other',
    };
    return map[platform] || platform;
}
</script>

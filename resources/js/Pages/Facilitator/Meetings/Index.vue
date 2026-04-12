<template>
    <Head :title="`Live Sessions – ${cohort.name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Live Sessions & Meetings</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }}</p>
                </div>
            </div>
            <button @click="showCreate = true" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                Schedule Meeting
            </button>
        </div>

        <!-- Upcoming -->
        <div>
            <h2 class="text-sm font-semibold text-surface-500 uppercase tracking-wider mb-3">Upcoming</h2>
            <div v-if="upcoming.length" class="space-y-3">
                <MeetingCard
                    v-for="meeting in upcoming"
                    :key="meeting.id"
                    :meeting="meeting"
                    :cohort="cohort"
                    :members="members"
                    @edit="openEdit(meeting)"
                    @cancel="cancelMeeting(meeting)"
                    @attendance="openAttendance(meeting)"
                    @notes="openNotes(meeting)"
                />
            </div>
            <p v-else class="text-sm text-surface-400 italic">No upcoming sessions scheduled.</p>
        </div>

        <!-- Past -->
        <div>
            <h2 class="text-sm font-semibold text-surface-500 uppercase tracking-wider mb-3">Past</h2>
            <div v-if="past.length" class="space-y-3">
                <MeetingCard
                    v-for="meeting in past"
                    :key="meeting.id"
                    :meeting="meeting"
                    :cohort="cohort"
                    :members="members"
                    @edit="openEdit(meeting)"
                    @attendance="openAttendance(meeting)"
                    @notes="openNotes(meeting)"
                />
            </div>
            <p v-else class="text-sm text-surface-400 italic">No past sessions.</p>
        </div>

        <!-- Pagination -->
        <div v-if="meetings.links && meetings.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in meetings.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>

        <!-- Create Meeting Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showCreate = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Schedule Meeting</h3>
                <form @submit.prevent="submitCreate">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                            <input v-model="createForm.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="e.g., Weekly Review Session" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                                <select v-model="createForm.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option v-for="t in meetingTypes" :key="t" :value="t">{{ t.replace(/_/g, ' ') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Platform</label>
                                <select v-model="createForm.platform" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="zoom">Zoom</option>
                                    <option value="teams">Teams</option>
                                    <option value="google_meet">Google Meet</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Meeting Link</label>
                            <input v-model="createForm.meeting_url" type="url" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="https://zoom.us/j/..." />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Date & Time</label>
                                <input v-model="createForm.scheduled_at" type="datetime-local" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Duration (min)</label>
                                <input v-model.number="createForm.duration_minutes" type="number" min="5" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Agenda</label>
                            <textarea v-model="createForm.agenda" rows="2" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Invite Learners</label>
                            <div class="max-h-32 overflow-y-auto border border-surface-200 rounded-lg p-2 space-y-1">
                                <label v-for="m in members" :key="m.id" class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" :value="m.id" v-model="createForm.attendee_ids" class="rounded border-surface-300 text-primary-600" />
                                    {{ m.first_name }} {{ m.last_name }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Modal -->
        <div v-if="attendanceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="attendanceModal = null">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Mark Attendance</h3>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <div v-for="m in members" :key="m.id" class="flex items-center justify-between p-2 hover:bg-surface-50 rounded-lg">
                        <span class="text-sm text-surface-700">{{ m.first_name }} {{ m.last_name }}</span>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="attendanceList[m.id]" class="rounded border-surface-300 text-success-600" />
                            <span class="text-xs text-surface-500">Present</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="attendanceModal = null" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Close</button>
                    <button @click="submitAttendance" class="px-4 py-2 bg-success-600 text-white rounded-lg text-sm font-medium hover:bg-success-700">
                        Save Attendance
                    </button>
                </div>
            </div>
        </div>

        <!-- Notes Modal -->
        <div v-if="notesModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="notesModal = null">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Session Notes</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Notes</label>
                        <textarea v-model="notesForm.notes" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Replay URL</label>
                        <input v-model="notesForm.replay_url" type="url" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="https://..." />
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="notesModal = null" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Close</button>
                    <button @click="submitNotes" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        Save Notes
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';
import MeetingCard from './MeetingCard.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    meetings: { type: Object, default: () => ({ data: [] }) },
    members: { type: Array, default: () => [] },
});

const meetingTypes = ['live_session', 'coaching', 'standup', 'review', 'webinar', 'bootcamp'];

const showCreate = ref(false);
const attendanceModal = ref(null);
const notesModal = ref(null);
const attendanceList = reactive({});
const notesForm = reactive({ notes: '', replay_url: '' });

const allMeetings = computed(() => props.meetings.data || []);
const upcoming = computed(() => allMeetings.value.filter(m => m.status === 'scheduled'));
const past = computed(() => allMeetings.value.filter(m => m.status !== 'scheduled'));

const createForm = useForm({
    title: '',
    type: 'live_session',
    platform: 'zoom',
    meeting_url: '',
    scheduled_at: '',
    duration_minutes: 60,
    agenda: '',
    attendee_ids: [],
});

function submitCreate() {
    createForm.post(`/facilitator/cohorts/${props.cohort.id}/meetings`, {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
}

function openEdit(meeting) {
    // Inline edit not implemented — users can update via the modal
}

function cancelMeeting(meeting) {
    if (!confirm('Cancel this meeting?')) return;
    router.delete(`/facilitator/cohorts/${props.cohort.id}/meetings/${meeting.id}`);
}

function openAttendance(meeting) {
    attendanceModal.value = meeting;
    // Pre-fill from existing attendees
    props.members.forEach(m => { attendanceList[m.id] = false; });
    (meeting.attendees || []).forEach(a => {
        attendanceList[a.user_id] = a.attended;
    });
}

function submitAttendance() {
    const attendees = Object.entries(attendanceList).map(([user_id, attended]) => ({
        user_id: parseInt(user_id),
        attended,
    }));
    router.post(`/facilitator/cohorts/${props.cohort.id}/meetings/${attendanceModal.value.id}/attendance`, { attendees }, {
        onSuccess: () => { attendanceModal.value = null; },
    });
}

function openNotes(meeting) {
    notesModal.value = meeting;
    notesForm.notes = meeting.notes || '';
    notesForm.replay_url = meeting.replay_url || '';
}

function submitNotes() {
    router.post(`/facilitator/cohorts/${props.cohort.id}/meetings/${notesModal.value.id}/notes`, notesForm, {
        onSuccess: () => { notesModal.value = null; },
    });
}
</script>

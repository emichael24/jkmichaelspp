<template>
    <div class="bg-white rounded-xl border border-surface-200/60 p-5">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
                <div :class="['w-10 h-10 rounded-lg flex items-center justify-center shrink-0', typeBg]">
                    <component :is="typeIcon" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="font-semibold text-surface-900 text-sm">{{ meeting.title }}</h3>
                    <div class="flex items-center gap-3 mt-1 text-xs text-surface-400">
                        <span class="flex items-center gap-1">
                            <Calendar class="w-3.5 h-3.5" />
                            {{ formatDateTime(meeting.scheduled_at) }}
                        </span>
                        <span class="flex items-center gap-1">
                            <Clock class="w-3.5 h-3.5" />
                            {{ meeting.duration_minutes }} min
                        </span>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium capitalize', statusBadge]">
                            {{ meeting.status }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span :class="['px-2 py-0.5 rounded text-xs font-medium capitalize', platformBadge]">
                            {{ meeting.platform.replace(/_/g, ' ') }}
                        </span>
                        <a v-if="meeting.meeting_url" :href="meeting.meeting_url" target="_blank" class="text-xs text-primary-600 hover:underline">
                            Join Link
                        </a>
                    </div>
                    <p v-if="meeting.agenda" class="text-sm text-surface-500 mt-2 line-clamp-2">{{ meeting.agenda }}</p>

                    <!-- Attendance summary -->
                    <div v-if="meeting.attendees?.length" class="flex items-center gap-2 mt-2">
                        <Users class="w-3.5 h-3.5 text-surface-400" />
                        <span class="text-xs text-surface-500">
                            {{ attendedCount }} / {{ meeting.attendees.length }} attended
                        </span>
                    </div>

                    <!-- Notes preview -->
                    <p v-if="meeting.notes" class="text-xs text-surface-400 mt-1 line-clamp-1 italic">Notes: {{ meeting.notes }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1 ml-2">
                <button v-if="meeting.status === 'scheduled'" @click="$emit('cancel', meeting)" class="p-1.5 text-surface-400 hover:text-danger-600 rounded-lg hover:bg-danger-50" title="Cancel">
                    <XCircle class="w-4 h-4" />
                </button>
                <button @click="$emit('attendance', meeting)" class="p-1.5 text-surface-400 hover:text-success-600 rounded-lg hover:bg-success-50" title="Attendance">
                    <ClipboardCheck class="w-4 h-4" />
                </button>
                <button @click="$emit('notes', meeting)" class="p-1.5 text-surface-400 hover:text-primary-600 rounded-lg hover:bg-primary-50" title="Notes">
                    <FileText class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    Video, Phone, Users, Monitor, Mic, BookOpen,
    Calendar, Clock, XCircle, ClipboardCheck, FileText,
} from 'lucide-vue-next';

const props = defineProps({
    meeting: { type: Object, required: true },
    cohort: { type: Object, required: true },
    members: { type: Array, default: () => [] },
});

defineEmits(['edit', 'cancel', 'attendance', 'notes']);

const typeIconMap = {
    live_session: Video,
    coaching: Phone,
    standup: Users,
    review: Monitor,
    webinar: Mic,
    bootcamp: BookOpen,
};

const typeColorMap = {
    live_session: 'bg-primary-500',
    coaching: 'bg-accent-500',
    standup: 'bg-success-500',
    review: 'bg-warning-500',
    webinar: 'bg-xp-500',
    bootcamp: 'bg-danger-500',
};

const typeIcon = computed(() => typeIconMap[props.meeting.type] || Video);
const typeBg = computed(() => typeColorMap[props.meeting.type] || 'bg-surface-400');

const statusBadge = computed(() => {
    const map = {
        scheduled: 'bg-primary-50 text-primary-700',
        in_progress: 'bg-warning-50 text-warning-700',
        completed: 'bg-success-50 text-success-700',
        cancelled: 'bg-surface-100 text-surface-500',
    };
    return map[props.meeting.status] || map.scheduled;
});

const platformBadge = computed(() => {
    const map = {
        zoom: 'bg-blue-50 text-blue-700',
        teams: 'bg-purple-50 text-purple-700',
        google_meet: 'bg-green-50 text-green-700',
        other: 'bg-surface-100 text-surface-600',
    };
    return map[props.meeting.platform] || map.other;
});

const attendedCount = computed(() => {
    return (props.meeting.attendees || []).filter(a => a.attended).length;
});

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

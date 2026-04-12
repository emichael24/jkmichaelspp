<script setup>
import { router } from '@inertiajs/vue3';
import { Calendar, Clock, Users, Video, MapPin, ExternalLink, Play } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ event: Object, isRegistered: Boolean, spotsLeft: Number });

function register() { router.post(route('events.register', props.event.id)); }
function cancelRegistration() { if (confirm('Cancel your registration?')) router.delete(route('events.cancelRegistration', props.event.id)); }

function formatDate(date) {
    return new Date(date).toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}
function formatTime(date) {
    return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

const typeLabels = { webinar: 'Webinar', bootcamp: 'Bootcamp', industry_session: 'Industry Session', workshop: 'Workshop' };
const platformLabels = { zoom: 'Zoom', teams: 'Microsoft Teams', google_meet: 'Google Meet', other: 'Other' };
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <button @click="router.visit(route('events.index'))" class="text-sm text-primary-600 hover:underline mb-4 block">← Back to Events</button>

        <!-- Hero -->
        <div class="rounded-2xl overflow-hidden bg-gradient-to-br from-primary-600 to-accent-700 text-white p-8 mb-6 relative">
            <Badge variant="primary" class="mb-3">{{ typeLabels[event.type] }}</Badge>
            <h1 class="text-3xl font-display font-bold mb-2">{{ event.title }}</h1>
            <div class="flex flex-wrap items-center gap-4 text-white/80">
                <span class="flex items-center gap-1.5"><Calendar class="w-4 h-4" />{{ formatDate(event.scheduled_at) }}</span>
                <span class="flex items-center gap-1.5"><Clock class="w-4 h-4" />{{ formatTime(event.scheduled_at) }} · {{ event.duration_minutes }}min</span>
                <span class="flex items-center gap-1.5"><Video class="w-4 h-4" />{{ platformLabels[event.platform] }}</span>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <Card class="p-6">
                    <h2 class="text-lg font-semibold text-surface-800 mb-3">About This Event</h2>
                    <div class="text-surface-600 whitespace-pre-wrap">{{ event.description }}</div>
                </Card>

                <Card v-if="event.speaker_name" class="p-6">
                    <h2 class="text-lg font-semibold text-surface-800 mb-3">Speaker</h2>
                    <div class="flex items-start gap-4">
                        <div class="w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                            <Users class="w-8 h-8 text-primary-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ event.speaker_name }}</h3>
                            <p class="text-sm text-surface-500 mt-1">{{ event.speaker_bio }}</p>
                        </div>
                    </div>
                </Card>

                <Card v-if="event.replay_url" class="p-6">
                    <h2 class="text-lg font-semibold text-surface-800 mb-3">Replay Available</h2>
                    <a :href="event.replay_url" target="_blank" class="inline-flex items-center gap-2 text-primary-600 hover:underline">
                        <Play class="w-5 h-5" /> Watch Replay <ExternalLink class="w-4 h-4" />
                    </a>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <Card class="p-6">
                    <div class="text-center mb-4">
                        <p class="text-3xl font-bold" :class="event.price > 0 ? 'text-primary-600' : 'text-success-600'">
                            {{ event.price > 0 ? '₦' + Number(event.price).toLocaleString() : 'Free' }}
                        </p>
                    </div>

                    <div class="space-y-2 mb-4 text-sm text-surface-600">
                        <p>{{ event.registrations_count }} registered</p>
                        <p v-if="spotsLeft !== null">{{ spotsLeft }} spots remaining</p>
                    </div>

                    <div v-if="new Date(event.scheduled_at) > new Date()">
                        <Button v-if="!isRegistered" @click="register" variant="primary" class="w-full" :disabled="spotsLeft === 0">
                            {{ spotsLeft === 0 ? 'Event Full' : 'Register Now' }}
                        </Button>
                        <div v-else class="space-y-2">
                            <Badge variant="success" class="w-full justify-center py-2">✓ Registered</Badge>
                            <Button @click="cancelRegistration" variant="secondary" class="w-full" size="sm">Cancel Registration</Button>
                        </div>
                    </div>
                    <Badge v-else variant="secondary" class="w-full justify-center py-2">Event has ended</Badge>

                    <div v-if="isRegistered && event.meeting_url && new Date(event.scheduled_at) <= new Date(Date.now() + 5 * 60000)" class="mt-3">
                        <a :href="event.meeting_url" target="_blank" class="block"><Button variant="primary" class="w-full gap-2"><ExternalLink class="w-4 h-4" />Join Meeting</Button></a>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Calendar, MapPin, Clock, Users, Video, ExternalLink, Play } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ events: Object, registeredIds: Array, tab: String, type: String });

const typeColors = {
    webinar: 'bg-blue-100 text-blue-700',
    bootcamp: 'bg-orange-100 text-orange-700',
    industry_session: 'bg-purple-100 text-purple-700',
    workshop: 'bg-green-100 text-green-700',
};

const typeLabels = { webinar: 'Webinar', bootcamp: 'Bootcamp', industry_session: 'Industry Session', workshop: 'Workshop' };

function setTab(t) { router.get(route('events.index'), { tab: t, type: props.type }, { preserveState: true }); }
function setType(t) { router.get(route('events.index'), { tab: props.tab, type: t === props.type ? null : t }, { preserveState: true }); }

function formatDate(date) {
    return new Date(date).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}
function formatTime(date) {
    return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-display font-bold text-surface-900">Events & Webinars</h1>
            <p class="text-surface-500 mt-1">Attend live sessions, bootcamps, and industry events</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-4">
            <Button @click="setTab('upcoming')" :variant="tab === 'upcoming' ? 'primary' : 'secondary'" size="sm">Upcoming</Button>
            <Button @click="setTab('past')" :variant="tab === 'past' ? 'primary' : 'secondary'" size="sm">Past Events</Button>
        </div>

        <!-- Type Filters -->
        <div class="flex flex-wrap gap-2 mb-6">
            <button v-for="(label, key) in typeLabels" :key="key" @click="setType(key)" :class="['px-3 py-1.5 rounded-full text-sm font-medium transition', type === key ? typeColors[key] : 'bg-surface-100 text-surface-600 hover:bg-surface-200']">
                {{ label }}
            </button>
        </div>

        <!-- Events Grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card v-for="event in events.data" :key="event.id" class="overflow-hidden hover:shadow-lg transition cursor-pointer" @click="router.visit(route('events.show', event.id))">
                <div class="h-36 bg-gradient-to-br from-primary-500 to-accent-600 relative flex items-center justify-center">
                    <img v-if="event.thumbnail" :src="event.thumbnail" class="w-full h-full object-cover absolute inset-0" />
                    <Video v-else class="w-12 h-12 text-white/50" />
                    <span :class="['absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-semibold', typeColors[event.type]]">{{ typeLabels[event.type] }}</span>
                    <span v-if="registeredIds.includes(event.id)" class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-semibold bg-success-500 text-white">Registered</span>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-surface-900 line-clamp-2">{{ event.title }}</h3>
                    <div class="mt-3 space-y-1.5 text-sm text-surface-500">
                        <p class="flex items-center gap-2"><Calendar class="w-4 h-4" />{{ formatDate(event.scheduled_at) }}</p>
                        <p class="flex items-center gap-2"><Clock class="w-4 h-4" />{{ formatTime(event.scheduled_at) }} · {{ event.duration_minutes }}min</p>
                        <p v-if="event.speaker_name" class="flex items-center gap-2"><Users class="w-4 h-4" />{{ event.speaker_name }}</p>
                    </div>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-surface-100">
                        <span class="text-sm font-medium" :class="event.price > 0 ? 'text-primary-600' : 'text-success-600'">
                            {{ event.price > 0 ? '₦' + Number(event.price).toLocaleString() : 'Free' }}
                        </span>
                        <span class="text-xs text-surface-400">{{ event.registrations_count }} registered</span>
                    </div>
                </div>
            </Card>
        </div>

        <EmptyState v-if="!events.data?.length" title="No events found" description="Check back later for upcoming events." :icon="Calendar" />
    </div>
</template>

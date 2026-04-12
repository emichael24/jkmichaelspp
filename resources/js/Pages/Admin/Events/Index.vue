<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { Calendar, Plus, Edit, Trash2, Users, Eye } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ events: Object });

const typeLabels = { webinar: 'Webinar', bootcamp: 'Bootcamp', industry_session: 'Industry', workshop: 'Workshop' };
const typeColors = { webinar: 'primary', bootcamp: 'warning', industry_session: 'accent', workshop: 'success' };

function deleteEvent(id) {
    if (confirm('Delete this event?')) router.delete(route('admin.events.destroy', id));
}
</script>

<template>
    <Head title="Manage Events" />
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Events</h1>
                <p class="text-surface-500 mt-1 text-sm">Manage platform events and webinars</p>
            </div>
            <Button @click="router.visit(route('admin.events.create'))" variant="primary" class="gap-2"><Plus class="w-4 h-4" />Create Event</Button>
        </div>

        <Card noPadding>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Event</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Type</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Date</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Registrations</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Status</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="event in events?.data" :key="event.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6">
                                <p class="font-medium text-surface-900">{{ event.title }}</p>
                                <p class="text-xs text-surface-500">{{ event.speaker_name }}</p>
                            </td>
                            <td class="py-3 px-6"><Badge :variant="typeColors[event.type]" size="sm">{{ typeLabels[event.type] }}</Badge></td>
                            <td class="py-3 px-6 text-surface-600 whitespace-nowrap">{{ new Date(event.scheduled_at).toLocaleDateString() }}</td>
                            <td class="py-3 px-6">
                                <span class="text-surface-700">{{ event.registrations_count }}{{ event.max_registrations ? '/' + event.max_registrations : '' }}</span>
                            </td>
                            <td class="py-3 px-6">
                                <Badge :variant="event.is_published ? 'success' : 'secondary'" size="sm">{{ event.is_published ? 'Published' : 'Draft' }}</Badge>
                            </td>
                            <td class="py-3 px-6">
                                <div class="flex items-center gap-2">
                                    <button @click="router.visit(route('admin.events.registrations', event.id))" class="text-primary-500 hover:text-primary-700"><Users class="w-4 h-4" /></button>
                                    <button @click="router.visit(route('admin.events.edit', event.id))" class="text-surface-500 hover:text-primary-600"><Edit class="w-4 h-4" /></button>
                                    <button @click="deleteEvent(event.id)" class="text-surface-400 hover:text-error-600"><Trash2 class="w-4 h-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>

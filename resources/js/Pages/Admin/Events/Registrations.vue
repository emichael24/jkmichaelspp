<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { ArrowLeft, CheckSquare } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ event: Object, registrations: Array });

function markAttendance() {
    const attendedIds = props.registrations.filter(r => r.attended).map(r => r.user_id);
    router.post(route('admin.events.markAttendance', props.event.id), { user_ids: attendedIds });
}
</script>

<template>
    <Head :title="`Registrations - ${event.title}`" />
    <div class="space-y-6">
        <button @click="router.visit(route('admin.events.index'))" class="text-sm text-primary-600 hover:underline flex items-center gap-1"><ArrowLeft class="w-4 h-4" />Back to Events</button>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">{{ event.title }}</h1>
                <p class="text-surface-500 text-sm">{{ registrations.length }} registered attendees</p>
            </div>
            <Button @click="markAttendance" variant="primary" class="gap-2"><CheckSquare class="w-4 h-4" />Save Attendance</Button>
        </div>

        <Card noPadding>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Attended</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Name</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Email</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="reg in registrations" :key="reg.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6">
                                <input type="checkbox" v-model="reg.attended" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                            </td>
                            <td class="py-3 px-6 font-medium text-surface-900">{{ reg.user?.first_name }} {{ reg.user?.last_name }}</td>
                            <td class="py-3 px-6 text-surface-600">{{ reg.user?.email }}</td>
                            <td class="py-3 px-6 text-surface-500">{{ new Date(reg.created_at).toLocaleDateString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>

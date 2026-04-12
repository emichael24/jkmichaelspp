<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import { ArrowLeft, FileText } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ log: Object });

const actionColors = {
    created: 'success', updated: 'primary', deleted: 'danger', login: 'accent', logout: 'secondary',
};
</script>

<template>
    <Head title="Audit Log Detail" />
    <div class="space-y-6">
        <button @click="router.visit(route('admin.audit-logs.index'))" class="text-sm text-primary-600 hover:underline flex items-center gap-1"><ArrowLeft class="w-4 h-4" />Back to Audit Logs</button>

        <h1 class="text-2xl font-display font-bold text-surface-900">Audit Log #{{ log.id }}</h1>

        <div class="grid lg:grid-cols-2 gap-6">
            <Card title="Details" class="p-5">
                <dl class="space-y-3">
                    <div class="flex justify-between"><dt class="text-surface-500">Action</dt><dd><Badge :variant="actionColors[log.action] || 'secondary'">{{ log.action }}</Badge></dd></div>
                    <div class="flex justify-between"><dt class="text-surface-500">User</dt><dd class="font-medium text-surface-900">{{ log.user?.first_name }} {{ log.user?.last_name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-surface-500">Entity</dt><dd class="text-surface-700">{{ log.auditable_type?.split('\\').pop() }} #{{ log.auditable_id }}</dd></div>
                    <div class="flex justify-between"><dt class="text-surface-500">IP Address</dt><dd class="text-surface-700">{{ log.ip_address }}</dd></div>
                    <div class="flex justify-between"><dt class="text-surface-500">User Agent</dt><dd class="text-surface-700 text-xs truncate max-w-xs">{{ log.user_agent }}</dd></div>
                    <div class="flex justify-between"><dt class="text-surface-500">Time</dt><dd class="text-surface-700">{{ new Date(log.created_at).toLocaleString() }}</dd></div>
                </dl>
            </Card>

            <div class="space-y-6">
                <Card v-if="log.old_values" title="Old Values" class="p-5">
                    <pre class="text-xs bg-surface-50 rounded-lg p-3 overflow-auto max-h-48 text-surface-700">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                </Card>
                <Card v-if="log.new_values" title="New Values" class="p-5">
                    <pre class="text-xs bg-surface-50 rounded-lg p-3 overflow-auto max-h-48 text-surface-700">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                </Card>
            </div>
        </div>
    </div>
</template>

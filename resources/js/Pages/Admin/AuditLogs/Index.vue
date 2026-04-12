<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { FileText, Download, Search, Filter, Eye } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ logs: Object, filters: Object, stats: Object, actions: Array, entityTypes: Array });

const localFilters = ref({ ...props.filters });

function applyFilters() {
    router.get(route('admin.audit-logs.index'), localFilters.value, { preserveState: true });
}

function exportCSV() {
    window.location.href = route('admin.audit-logs.export') + '?' + new URLSearchParams(localFilters.value).toString();
}

const actionColors = {
    created: 'success', updated: 'primary', deleted: 'danger', login: 'accent', logout: 'secondary',
};
</script>

<template>
    <Head title="Audit Logs" />
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Audit Logs</h1>
                <p class="text-surface-500 mt-1 text-sm">Track all system activity and changes</p>
            </div>
            <Button @click="exportCSV" variant="secondary" class="gap-2"><Download class="w-4 h-4" />Export CSV</Button>
        </div>

        <!-- Filters -->
        <Card class="p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-surface-500 mb-1">Action</label>
                    <select v-model="localFilters.action" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm">
                        <option value="">All Actions</option>
                        <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-surface-500 mb-1">Entity Type</label>
                    <select v-model="localFilters.entity_type" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm">
                        <option value="">All Types</option>
                        <option v-for="t in entityTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-surface-500 mb-1">From</label>
                    <input v-model="localFilters.from" type="date" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-surface-500 mb-1">To</label>
                    <input v-model="localFilters.to" type="date" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm" />
                </div>
                <Button @click="applyFilters" variant="primary" size="sm" class="gap-1"><Filter class="w-3.5 h-3.5" />Filter</Button>
            </div>
        </Card>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div v-for="(count, action) in stats?.byAction" :key="action" class="bg-surface-50 rounded-xl p-3 text-center">
                <p class="text-lg font-bold text-surface-900">{{ count }}</p>
                <p class="text-xs text-surface-500 capitalize">{{ action }}</p>
            </div>
        </div>

        <!-- Logs Table -->
        <Card noPadding>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Time</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">User</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Action</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Entity</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">IP</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="log in logs?.data" :key="log.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6 text-surface-500 text-xs whitespace-nowrap">{{ new Date(log.created_at).toLocaleString() }}</td>
                            <td class="py-3 px-6 font-medium text-surface-900">{{ log.user?.first_name || 'System' }}</td>
                            <td class="py-3 px-6"><Badge :variant="actionColors[log.action] || 'secondary'" size="sm">{{ log.action }}</Badge></td>
                            <td class="py-3 px-6 text-surface-600">{{ log.auditable_type?.split('\\').pop() }} #{{ log.auditable_id }}</td>
                            <td class="py-3 px-6 text-xs text-surface-400">{{ log.ip_address }}</td>
                            <td class="py-3 px-6">
                                <button @click="router.visit(route('admin.audit-logs.show', log.id))" class="text-primary-500 hover:text-primary-700"><Eye class="w-4 h-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>

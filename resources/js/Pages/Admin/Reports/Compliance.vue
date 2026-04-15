<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ enrollments: Object, filters: Object, auditLogs: Object });
const selectedIds = ref([]);
const bulkStatus = ref('');
const bulkDeadline = ref('');
const clearDeadline = ref(false);

const allSelected = computed(() => {
    const ids = props.enrollments?.data?.map(e => e.id) || [];
    return ids.length > 0 && ids.every(id => selectedIds.value.includes(id));
});

function applyFilter(key, value) {
    const params = { ...props.filters, [key]: value };
    router.get(route('admin.reports.compliance'), params, { preserveState: true });
}

function updateStatus(enrollment, status) {
    router.put(route('admin.enrollments.status', enrollment.id), { status }, {
        preserveScroll: true,
    });
}

function autoExpire() {
    router.post(route('admin.enrollments.auto-expire'), {}, { preserveScroll: true });
}

function statusVariant(status) {
    if (status === 'completed') return 'success';
    if (status === 'active') return 'primary';
    if (status === 'expired') return 'danger';
    return 'warning';
}

function toggleSelectAll() {
    const ids = props.enrollments?.data?.map(e => e.id) || [];
    selectedIds.value = allSelected.value ? [] : ids;
}

function toggleSelection(id) {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter(x => x !== id)
        : [...selectedIds.value, id];
}

function applyBulk() {
    router.put(route('admin.enrollments.bulk'), {
        enrollment_ids: selectedIds.value,
        status: bulkStatus.value || null,
        deadline: bulkDeadline.value || null,
        clear_deadline: clearDeadline.value,
    }, { preserveScroll: true });
}

function auditFor(id) {
    return props.auditLogs?.[id] || null;
}

function formatAudit(log) {
    if (!log) return '-';
    const who = log.user ? `${log.user.first_name || ''} ${log.user.last_name || ''}`.trim() : 'System';
    const when = log.created_at ? new Date(log.created_at).toLocaleString() : '';
    return `${who} - ${when}`;
}
</script>

<template>
    <Head title="Compliance Report" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Compliance Report</h1>
            <p class="text-surface-500 mt-1 text-sm">Track enrollment completion and compliance status</p>
        </div>

        <Card noPadding>
            <div class="p-4 border-b border-surface-100 flex flex-wrap gap-3">
                <select @change="applyFilter('status', $event.target.value)" :value="filters?.status" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="expired">Expired</option>
                    <option value="suspended">Suspended</option>
                </select>
                <div class="flex items-center gap-2">
                    <select v-model="bulkStatus" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm">
                        <option value="">Bulk status</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <input v-model="bulkDeadline" type="date" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm" />
                    <label class="inline-flex items-center gap-2 text-sm text-surface-600">
                        <input v-model="clearDeadline" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                        Clear deadline
                    </label>
                    <button
                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
                        :disabled="selectedIds.length === 0"
                        @click="applyBulk"
                    >
                        Apply to {{ selectedIds.length }}
                    </button>
                </div>
                <button
                    class="ml-auto inline-flex items-center gap-2 rounded-lg border border-surface-200 bg-white px-3 py-1.5 text-sm font-medium text-surface-700 hover:bg-surface-50"
                    @click="autoExpire"
                >
                    Auto-expire by deadline
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left font-medium text-surface-500 py-3 px-6">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    class="rounded border-surface-300 text-primary-600"
                                    @change="toggleSelectAll"
                                >
                            </th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Learner</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Course</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Progress</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Status</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Deadline</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Last Audit</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Enrolled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="e in enrollments?.data" :key="e.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(e.id)"
                                    class="rounded border-surface-300 text-primary-600"
                                    @change="toggleSelection(e.id)"
                                >
                            </td>
                            <td class="py-3 px-6 font-medium text-surface-900">{{ e.user?.first_name }} {{ e.user?.last_name }}</td>
                            <td class="py-3 px-6 text-surface-600">{{ e.course?.title }}</td>
                            <td class="py-3 px-6">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-surface-100 rounded-full h-2 overflow-hidden">
                                        <div class="bg-primary-500 h-full rounded-full" :style="{ width: (e.progress_pct || 0) + '%' }"></div>
                                    </div>
                                    <span class="text-xs text-surface-500">{{ e.progress_pct || 0 }}%</span>
                                </div>
                            </td>
                            <td class="py-3 px-6">
                                <div class="flex items-center gap-2">
                                    <Badge :variant="statusVariant(e.status)" size="sm">{{ e.status }}</Badge>
                                    <select
                                        class="rounded-md border border-surface-200 px-2 py-1 text-xs"
                                        :value="e.status"
                                        @change="updateStatus(e, $event.target.value)"
                                    >
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="expired">Expired</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </td>
                            <td class="py-3 px-6 text-surface-500">
                                {{ e.deadline ? new Date(e.deadline).toLocaleDateString() : '-' }}
                            </td>
                            <td class="py-3 px-6 text-surface-500">
                                {{ formatAudit(auditFor(e.id)) }}
                            </td>
                            <td class="py-3 px-6 text-surface-500">{{ new Date(e.enrolled_at).toLocaleDateString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>



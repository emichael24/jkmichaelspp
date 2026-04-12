<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ enrollments: Object, filters: Object });

function applyFilter(key, value) {
    const params = { ...props.filters, [key]: value };
    router.get(route('admin.reports.compliance'), params, { preserveState: true });
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
                    <option value="dropped">Dropped</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Learner</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Course</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Progress</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Status</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Enrolled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="e in enrollments?.data" :key="e.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6 font-medium text-surface-900">{{ e.user?.first_name }} {{ e.user?.last_name }}</td>
                            <td class="py-3 px-6 text-surface-600">{{ e.course?.title }}</td>
                            <td class="py-3 px-6">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-surface-100 rounded-full h-2 overflow-hidden">
                                        <div class="bg-primary-500 h-full rounded-full" :style="{ width: (e.progress_percentage || 0) + '%' }"></div>
                                    </div>
                                    <span class="text-xs text-surface-500">{{ e.progress_percentage || 0 }}%</span>
                                </div>
                            </td>
                            <td class="py-3 px-6"><Badge :variant="e.status === 'completed' ? 'success' : e.status === 'active' ? 'primary' : 'warning'" size="sm">{{ e.status }}</Badge></td>
                            <td class="py-3 px-6 text-surface-500">{{ new Date(e.enrolled_at).toLocaleDateString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>

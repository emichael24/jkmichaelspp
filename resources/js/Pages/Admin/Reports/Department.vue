<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import { Building2, Users, TrendingUp, Award } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ departments: Array });
</script>

<template>
    <Head title="Department Report" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Department Analytics</h1>
            <p class="text-surface-500 mt-1 text-sm">Performance metrics by department</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card v-for="dept in departments" :key="dept.id" class="p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-primary-100 flex items-center justify-center">
                        <Building2 class="w-5 h-5 text-primary-600" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-900">{{ dept.name }}</h3>
                        <p class="text-xs text-surface-500">{{ dept.users_count }} members</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-surface-500">Avg Completion</span>
                            <span class="font-medium text-surface-900">{{ dept.avg_completion || 0 }}%</span>
                        </div>
                        <ProgressBar :percentage="dept.avg_completion || 0" color="auto" size="sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="bg-surface-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-surface-900">{{ dept.enrollments_count || 0 }}</p>
                            <p class="text-xs text-surface-500">Enrollments</p>
                        </div>
                        <div class="bg-surface-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-surface-900">{{ dept.completions_count || 0 }}</p>
                            <p class="text-xs text-surface-500">Completions</p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

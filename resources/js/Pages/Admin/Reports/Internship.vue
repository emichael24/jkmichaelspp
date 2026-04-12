<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import { Briefcase, Users, CheckCircle } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ tracks: Array });
</script>

<template>
    <Head title="Internship Report" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Internship Report</h1>
            <p class="text-surface-500 mt-1 text-sm">Internship track progress and outcomes</p>
        </div>

        <div class="space-y-4">
            <Card v-for="track in tracks" :key="track.id" class="p-5">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-warning-100 flex items-center justify-center">
                            <Briefcase class="w-5 h-5 text-warning-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ track.title }}</h3>
                            <p class="text-sm text-surface-500">{{ track.duration_weeks }} weeks</p>
                        </div>
                    </div>
                    <Badge :variant="track.is_active ? 'success' : 'secondary'" size="sm">{{ track.is_active ? 'Active' : 'Inactive' }}</Badge>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-surface-50 rounded-lg p-3">
                        <Users class="w-5 h-5 text-primary-500 mx-auto mb-1" />
                        <p class="text-lg font-bold text-surface-900">{{ track.enrollments_count || 0 }}</p>
                        <p class="text-xs text-surface-500">Interns</p>
                    </div>
                    <div class="bg-surface-50 rounded-lg p-3">
                        <CheckCircle class="w-5 h-5 text-success-500 mx-auto mb-1" />
                        <p class="text-lg font-bold text-surface-900">{{ track.milestones_count || 0 }}</p>
                        <p class="text-xs text-surface-500">Milestones</p>
                    </div>
                    <div class="bg-surface-50 rounded-lg p-3">
                        <p class="text-lg font-bold text-surface-900">{{ track.tasks_count || 0 }}</p>
                        <p class="text-xs text-surface-500">Tasks</p>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { FileText, TrendingUp, Shield, Building2, Briefcase } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AdminLayout });

const reports = [
    { title: 'Executive Summary', desc: 'High-level platform metrics and KPIs', icon: TrendingUp, route: 'admin.reports.executive', color: 'bg-primary-100 text-primary-600' },
    { title: 'Compliance Report', desc: 'Enrollment completion and compliance tracking', icon: Shield, route: 'admin.reports.compliance', color: 'bg-success-100 text-success-600' },
    { title: 'Department Analytics', desc: 'Per-department performance breakdown', icon: Building2, route: 'admin.reports.department', color: 'bg-accent-100 text-accent-600' },
    { title: 'Internship Report', desc: 'Internship program progress and outcomes', icon: Briefcase, route: 'admin.reports.internship', color: 'bg-warning-100 text-warning-600' },
];
</script>

<template>
    <Head title="Reports" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Reports</h1>
            <p class="text-surface-500 mt-1 text-sm">Generate and view platform reports</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <Card v-for="report in reports" :key="report.title" class="p-5 hover:shadow-md transition cursor-pointer" @click="router.visit(route(report.route))">
                <div class="flex items-start gap-4">
                    <div :class="['w-12 h-12 rounded-xl flex items-center justify-center', report.color]">
                        <component :is="report.icon" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-900">{{ report.title }}</h3>
                        <p class="text-sm text-surface-500 mt-1">{{ report.desc }}</p>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

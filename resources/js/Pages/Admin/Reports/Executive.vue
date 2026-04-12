<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import { Users, BookOpen, DollarSign, ClipboardCheck } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ users: Object, courses: Object, revenue: Object, assessments: Object });

const formatNumber = (v) => Number(v).toLocaleString();
const formatCurrency = (v) => `₦${Number(v).toLocaleString()}`;
</script>

<template>
    <Head title="Executive Report" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Executive Summary</h1>
            <p class="text-surface-500 mt-1 text-sm">Platform-wide performance overview</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total Users" :value="users?.total || 0" :icon="Users" color="primary" :format="formatNumber" />
            <StatCard label="Total Courses" :value="courses?.total || 0" :icon="BookOpen" color="success" />
            <StatCard label="Total Revenue" :value="revenue?.total || 0" :icon="DollarSign" color="accent" :format="formatCurrency" />
            <StatCard label="Avg Quiz Score" :value="(assessments?.averageScore || 0) + '%'" :icon="ClipboardCheck" color="xp" />
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <Card title="Users by Role">
                <div class="space-y-3">
                    <div v-for="(count, role) in users?.byRole" :key="role" class="flex items-center justify-between">
                        <span class="text-sm font-medium text-surface-700 capitalize">{{ role }}</span>
                        <span class="text-sm font-bold text-surface-900">{{ count }}</span>
                    </div>
                </div>
            </Card>
            <Card title="Revenue by Gateway">
                <div class="space-y-3">
                    <div v-for="(amount, gateway) in revenue?.byGateway" :key="gateway" class="flex items-center justify-between">
                        <span class="text-sm font-medium text-surface-700 capitalize">{{ gateway || 'Unknown' }}</span>
                        <span class="text-sm font-bold text-surface-900">{{ formatCurrency(amount) }}</span>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

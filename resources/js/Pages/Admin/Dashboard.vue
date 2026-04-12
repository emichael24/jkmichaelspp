<template>
    <Head title="Admin Dashboard" />

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 via-slate-900 to-slate-950 p-6 sm:p-8">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-500/10 rounded-full blur-3xl -translate-y-1/3 translate-x-1/3" />
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-accent-500/10 rounded-full blur-3xl" />
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-display font-bold text-white mb-1">Admin Dashboard</h1>
                    <p class="text-slate-400 text-sm">Platform health &amp; activity overview</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center">
                        <p class="text-lg font-bold text-white leading-none">{{ stats.dau }}</p>
                        <p class="text-white/50 text-[10px] font-medium">DAU</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center">
                        <p class="text-lg font-bold text-white leading-none">{{ stats.wau }}</p>
                        <p class="text-white/50 text-[10px] font-medium">WAU</p>
                    </div>
                    <div v-if="stats.atRiskCount > 0" class="bg-danger-500/20 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-danger-500/20 text-center">
                        <p class="text-lg font-bold text-danger-300 leading-none">{{ stats.atRiskCount }}</p>
                        <p class="text-danger-300/60 text-[10px] font-medium">At Risk</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total Users" :value="stats.totalUsers || 0" :icon="Users" color="primary" :format="formatNumber" />
            <StatCard label="Weekly Active" :value="stats.wau || 0" :icon="UserCheck" color="success" :format="formatNumber" />
            <StatCard label="Revenue (MTD)" :value="stats.monthlyRevenue || 0" :icon="DollarSign" color="accent" :format="formatCurrency" />
            <StatCard label="Completion Rate" :value="`${stats.completionRate || 0}%`" :icon="GraduationCap" color="xp" />
        </div>

        <!-- Charts row -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Enrollment trend chart -->
            <Card title="Enrollment Trend" subtitle="Last 30 days">
                <div class="h-56">
                    <div v-if="enrollmentTrendData.length" class="h-full flex items-end gap-1">
                        <div v-for="(point, i) in enrollmentTrendData" :key="i" class="flex-1 flex flex-col items-center justify-end gap-1">
                            <span v-if="point.count > 0" class="text-[9px] text-surface-500">{{ point.count }}</span>
                            <div
                                class="w-full rounded-t-sm bg-primary-400 hover:bg-primary-500 transition-colors min-h-[2px]"
                                :style="{ height: `${Math.max(2, (point.count / maxEnrollment) * 180)}px` }"
                                :title="`${point.date}: ${point.count}`"
                            />
                        </div>
                    </div>
                    <div v-else class="h-full flex items-center justify-center text-surface-400">
                        <div class="text-center">
                            <BarChart3 class="w-10 h-10 mx-auto mb-2 text-surface-300" />
                            <p class="text-sm">No enrollment data yet</p>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Platform Stats -->
            <Card title="Platform Stats" subtitle="Key metrics">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-primary-50 text-center">
                        <BookOpen class="w-6 h-6 text-primary-600 mx-auto mb-2" />
                        <p class="text-2xl font-bold text-primary-700">{{ stats.totalCourses }}</p>
                        <p class="text-xs text-primary-500">Published Courses</p>
                    </div>
                    <div class="p-4 rounded-xl bg-success-50 text-center">
                        <UserCheck class="w-6 h-6 text-success-600 mx-auto mb-2" />
                        <p class="text-2xl font-bold text-success-700">{{ stats.totalEnrollments }}</p>
                        <p class="text-xs text-success-500">Total Enrollments</p>
                    </div>
                    <div class="p-4 rounded-xl bg-accent-50 text-center">
                        <TrendingUp class="w-6 h-6 text-accent-600 mx-auto mb-2" />
                        <p class="text-2xl font-bold text-accent-700">{{ formatCurrency(stats.totalRevenue) }}</p>
                        <p class="text-xs text-accent-500">Lifetime Revenue</p>
                    </div>
                    <div class="p-4 rounded-xl bg-danger-50 text-center">
                        <AlertTriangle class="w-6 h-6 text-danger-600 mx-auto mb-2" />
                        <p class="text-2xl font-bold text-danger-700">{{ stats.atRiskCount }}</p>
                        <p class="text-xs text-danger-500">At-Risk Users</p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Tables row -->
        <div class="grid lg:grid-cols-5 gap-6">
            <Card title="Recent Registrations" class="lg:col-span-3" noPadding>
                <template #header-actions>
                    <Link href="/admin/users" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</Link>
                </template>
                <div v-if="recentUsers.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-surface-100">
                                <th class="text-left font-medium text-surface-500 py-3 px-6">User</th>
                                <th class="text-left font-medium text-surface-500 py-3 px-6">Role</th>
                                <th class="text-left font-medium text-surface-500 py-3 px-6">Joined</th>
                                <th class="text-left font-medium text-surface-500 py-3 px-6">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100">
                            <tr v-for="user in recentUsers" :key="user.id" class="hover:bg-surface-50 transition-colors">
                                <td class="py-3 px-6">
                                    <div class="flex items-center gap-3">
                                        <Avatar :name="user.name" size="sm" />
                                        <div>
                                            <p class="font-medium text-surface-900">{{ user.name }}</p>
                                            <p class="text-xs text-surface-400">{{ user.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-6">
                                    <Badge :variant="roleVariant(user.role)" size="sm">{{ user.role }}</Badge>
                                </td>
                                <td class="py-3 px-6 text-surface-500 text-xs">{{ user.date }}</td>
                                <td class="py-3 px-6">
                                    <Badge :variant="user.verified ? 'success' : 'warning'" size="sm" dot>
                                        {{ user.verified ? 'Verified' : 'Pending' }}
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-6 text-center text-sm text-surface-400">No recent registrations</div>
            </Card>

            <Card title="Top Courses" class="lg:col-span-2">
                <template #header-actions>
                    <Link href="/admin/courses" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all</Link>
                </template>
                <div v-if="topCourses.length" class="space-y-4">
                    <div v-for="(course, i) in topCourses" :key="course.id" class="flex items-center gap-3">
                        <span :class="[
                            'w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0',
                            i === 0 ? 'bg-warning-100 text-warning-700' :
                            i === 1 ? 'bg-surface-200 text-surface-600' :
                            i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-surface-100 text-surface-500',
                        ]">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-surface-900 truncate">{{ course.title }}</p>
                            <p class="text-xs text-surface-400">{{ course.enrollments_count }} enrollments</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-400 text-center py-4">No courses yet</p>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Avatar from '@/Components/UI/Avatar.vue';
import {
    Users, UserCheck, DollarSign, GraduationCap, BarChart3, TrendingUp,
    BookOpen, AlertTriangle,
} from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    recentUsers: { type: Array, default: () => [] },
    topCourses: { type: Array, default: () => [] },
    enrollmentTrend: { type: [Object, Array], default: () => ({}) },
});

// Controller passes 'metrics', normalize to 'stats'
const stats = computed(() => props.metrics);

const enrollmentTrendData = computed(() => {
    if (Array.isArray(props.enrollmentTrend)) return props.enrollmentTrend;
    return Object.entries(props.enrollmentTrend).map(([date, count]) => ({ date, count }));
});
const maxEnrollment = computed(() => Math.max(1, ...enrollmentTrendData.value.map(p => p.count)));

const formatNumber = (val) => Number(val || 0).toLocaleString();
const formatCurrency = (val) => `₦${Number(val || 0).toLocaleString()}`;
const roleVariant = (role) => ({ platform_admin: 'danger', org_admin: 'warning', facilitator: 'primary', sme_reviewer: 'accent', sponsor: 'success' }[role] || 'default');
</script>

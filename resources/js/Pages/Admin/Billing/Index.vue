<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { DollarSign, TrendingUp, CreditCard, Users, RotateCcw } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ totalRevenue: Number, monthlyRevenue: Number, revenueByGateway: Object, monthlyTrend: Array, recentPayments: Array, activeSeats: Number });

const fmt = (v) => `₦${Number(v || 0).toLocaleString()}`;

function refund(paymentId) {
    if (confirm('Issue refund for this payment?')) {
        router.post(route('admin.billing.refund', paymentId));
    }
}
</script>

<template>
    <Head title="Billing" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Billing & Revenue</h1>
            <p class="text-surface-500 mt-1 text-sm">Revenue tracking and payment management</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total Revenue" :value="totalRevenue || 0" :icon="DollarSign" color="accent" :format="fmt" />
            <StatCard label="This Month" :value="monthlyRevenue || 0" :icon="TrendingUp" color="success" :format="fmt" />
            <StatCard label="Active Seats" :value="activeSeats || 0" :icon="Users" color="primary" />
            <StatCard label="Gateways" :value="Object.keys(revenueByGateway || {}).length" :icon="CreditCard" color="xp" />
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <Card title="Revenue by Gateway">
                <div class="space-y-3">
                    <div v-for="(amount, gw) in revenueByGateway" :key="gw" class="flex items-center justify-between p-3 bg-surface-50 rounded-lg">
                        <span class="font-medium text-surface-700 capitalize">{{ gw || 'Unknown' }}</span>
                        <span class="font-bold text-surface-900">{{ fmt(amount) }}</span>
                    </div>
                </div>
            </Card>

            <Card title="Monthly Trend">
                <div class="space-y-2">
                    <div v-for="m in monthlyTrend" :key="m.month" class="flex items-center gap-3">
                        <span class="text-sm text-surface-500 w-20">{{ m.month }}</span>
                        <div class="flex-1 bg-surface-100 rounded-full h-3 overflow-hidden">
                            <div class="bg-accent-500 h-full rounded-full" :style="{ width: Math.min(100, (m.revenue / (monthlyRevenue || 1)) * 100) + '%' }"></div>
                        </div>
                        <span class="text-sm font-medium text-surface-700 w-24 text-right">{{ fmt(m.revenue) }}</span>
                    </div>
                </div>
            </Card>
        </div>

        <Card title="Recent Payments" noPadding>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="text-left font-medium text-surface-500 py-3 px-6">User</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Amount</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Gateway</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Status</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Date</th>
                            <th class="text-left font-medium text-surface-500 py-3 px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="p in recentPayments" :key="p.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6 font-medium text-surface-900">{{ p.user?.first_name }} {{ p.user?.last_name }}</td>
                            <td class="py-3 px-6 text-surface-700">{{ fmt(p.amount) }}</td>
                            <td class="py-3 px-6 capitalize text-surface-500">{{ p.gateway }}</td>
                            <td class="py-3 px-6"><Badge :variant="p.status === 'completed' ? 'success' : p.status === 'refunded' ? 'warning' : 'secondary'" size="sm">{{ p.status }}</Badge></td>
                            <td class="py-3 px-6 text-surface-500">{{ new Date(p.created_at).toLocaleDateString() }}</td>
                            <td class="py-3 px-6">
                                <button v-if="p.status === 'completed'" @click="refund(p.id)" class="text-warning-600 hover:text-warning-700"><RotateCcw class="w-4 h-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>

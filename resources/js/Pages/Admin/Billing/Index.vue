<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import { DollarSign, TrendingUp, CreditCard, Users, RotateCcw } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({ totalRevenue: Number, monthlyRevenue: Number, revenueByGateway: Object, monthlyTrend: Array, recentPayments: Object, activeSeats: Number, currency: String, currencies: Array });

const fmt = (v) => new Intl.NumberFormat(undefined, { style: 'currency', currency: props.currency || 'NGN', maximumFractionDigits: 2 }).format(Number(v || 0));

const selectedPayment = ref(null);
const refundForm = useForm({ reason: '', confirmed: false });
const statusForm = useForm({});
function openRefund(payment) {
    refundForm.reset();
    refundForm.clearErrors();
    selectedPayment.value = payment;
}
function submitRefund() {
    refundForm.post(route('admin.billing.refund', selectedPayment.value.id), { preserveScroll: true, onSuccess: () => { selectedPayment.value = null; } });
}
</script>

<template>
    <Head title="Billing" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Billing & Revenue</h1>
            <p class="text-surface-500 mt-1 text-sm">Revenue tracking and payment management</p>
            <div class="mt-4 flex items-center gap-3"><label for="billing-currency" class="text-sm text-surface-600">Reporting currency</label><select id="billing-currency" :value="currency" @change="router.get('/admin/billing', { currency: $event.target.value })" class="rounded-lg border border-surface-200 bg-white px-3 text-sm"><option v-for="code in currencies" :key="code">{{ code }}</option></select></div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total Revenue" :value="totalRevenue || 0" :icon="DollarSign" color="accent" :format="fmt" />
            <StatCard label="Last 30 days" :value="monthlyRevenue || 0" :icon="TrendingUp" color="success" :format="fmt" />
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
            <p v-for="error in statusForm.errors" :key="error" role="alert" class="p-4 text-sm text-error-700">{{ error }}</p>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Recent payments">
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
                        <tr v-for="p in recentPayments.data" :key="p.id" class="hover:bg-surface-50">
                            <td class="py-3 px-6 font-medium text-surface-900">{{ p.user?.first_name }} {{ p.user?.last_name }}</td>
                            <td class="py-3 px-6 text-surface-700">{{ fmt(p.amount) }}</td>
                            <td class="py-3 px-6 capitalize text-surface-500">{{ p.gateway }}</td>
                            <td class="py-3 px-6"><Badge :variant="p.status === 'success' ? 'success' : p.status === 'refunded' ? 'warning' : 'secondary'" size="sm">{{ p.status }}</Badge></td>
                            <td class="py-3 px-6 text-surface-500">{{ new Date(p.created_at).toLocaleDateString() }}</td>
                            <td class="py-3 px-6">
                                <div v-if="p.refund" class="space-y-2">
                                    <p class="text-xs font-medium text-surface-700">Refund: {{ p.refund.status }}</p>
                                    <Button v-if="p.refund.status !== 'processed'" size="sm" variant="secondary" :disabled="statusForm.processing" @click="statusForm.post(route('admin.billing.refund.reconcile', p.id), { preserveScroll: true })">Check refund</Button>
                                </div>
                                <Button v-else-if="p.status === 'success' && p.gateway === 'paystack' && Number(p.amount) > 0" size="sm" variant="secondary" @click="openRefund(p)">Request refund</Button>
                                <span v-else class="text-xs text-surface-500">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-surface-100 px-6 py-4 text-xs text-surface-500"><span>{{ recentPayments.total }} payments</span><div class="flex gap-4"><Link v-if="recentPayments.prev_page_url" :href="recentPayments.prev_page_url">Previous</Link><Link v-if="recentPayments.next_page_url" :href="recentPayments.next_page_url">Next</Link></div></div>
        </Card>
    </div>
    <Modal :show="!!selectedPayment" title="Request a full refund" @close="selectedPayment = null">
        <form v-if="selectedPayment" class="space-y-5" @submit.prevent="submitRefund">
            <p class="text-sm text-surface-600">Return {{ fmt(selectedPayment.amount) }} to {{ selectedPayment.user?.first_name }} {{ selectedPayment.user?.last_name }} through Paystack. This submits a financial request to the provider.</p>
            <p class="rounded-lg bg-surface-50 p-3 text-sm text-surface-600">Payment is marked refunded only after verification. Access granted by this order then ends. Progress and earned credentials are retained; historical and independently granted enrollments require a separate access review.</p>
            <div><label for="refund-reason" class="mb-2 block text-sm font-medium text-surface-700">Reason for the refund</label><textarea id="refund-reason" v-model="refundForm.reason" required minlength="5" maxlength="1000" rows="3" class="w-full rounded-lg border-surface-300" /><p v-if="refundForm.errors.reason" role="alert" class="mt-1 text-sm text-error-700">{{ refundForm.errors.reason }}</p></div>
            <label class="flex items-start gap-3 text-sm text-surface-700"><input v-model="refundForm.confirmed" type="checkbox" required class="mt-1 rounded border-surface-300" /><span>I reviewed this payment and authorize its full refund.</span></label>
            <p v-if="refundForm.errors.confirmed" role="alert" class="text-sm text-error-700">{{ refundForm.errors.confirmed }}</p>
            <div class="flex justify-end gap-3"><Button type="button" variant="secondary" @click="selectedPayment = null">Cancel</Button><Button type="submit" :disabled="refundForm.processing || !refundForm.confirmed">{{ refundForm.processing ? 'Submitting…' : 'Submit refund request' }}</Button></div>
        </form>
    </Modal>
</template>

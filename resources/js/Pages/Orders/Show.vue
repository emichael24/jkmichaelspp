<template>
    <Head :title="`Order ${order.uuid.substring(0, 8)}`" />

    <div class="max-w-3xl mx-auto space-y-6">
        <a href="/orders" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to orders
        </a>

        <!-- Status banner -->
        <div v-if="gatewayReturn && order.status === 'pending'"
             class="bg-warning-50 border border-warning-200 rounded-xl p-4 flex items-center gap-3">
            <Loader2 class="w-5 h-5 text-warning-600 animate-spin" />
            <div>
                <p class="font-semibold text-warning-900">Payment Processing</p>
                <p class="text-sm text-warning-700">Your payment is being confirmed. This page will update automatically.</p>
            </div>
        </div>

        <div v-if="order.status === 'paid'"
             class="bg-success-50 border border-success-200 rounded-xl p-4 flex items-center gap-3">
            <CheckCircle class="w-5 h-5 text-success-600" />
            <div>
                <p class="font-semibold text-success-900">Payment Confirmed</p>
                <p class="text-sm text-success-700">You've been automatically enrolled. Start learning now!</p>
            </div>
        </div>

        <div v-if="order.status === 'failed'"
             class="bg-error-50 border border-error-200 rounded-xl p-4 flex items-center gap-3">
            <XCircle class="w-5 h-5 text-error-600" />
            <div>
                <p class="font-semibold text-error-900">Payment Failed</p>
                <p class="text-sm text-error-700">Please try again or use a different payment method.</p>
            </div>
        </div>

        <!-- Order summary -->
        <Card :title="`Order #${order.uuid.substring(0, 8).toUpperCase()}`" :subtitle="order.created_at">
            <div class="space-y-4">
                <!-- Items -->
                <div class="space-y-2">
                    <div v-for="(item, idx) in order.items" :key="idx"
                         class="flex items-center justify-between py-2 border-b border-surface-100 last:border-0">
                        <div>
                            <p class="font-medium text-sm text-surface-900">{{ item.product_name }}</p>
                            <p class="text-xs text-surface-400">Qty: {{ item.quantity }}</p>
                        </div>
                        <p class="font-semibold text-sm text-surface-900">
                            {{ formatCurrency(item.total_price, order.currency) }}
                        </p>
                    </div>
                </div>

                <!-- Totals -->
                <div class="border-t border-surface-200 pt-3 space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-surface-500">Subtotal</span>
                        <span class="text-surface-700">{{ formatCurrency(order.subtotal, order.currency) }}</span>
                    </div>
                    <div v-if="order.discount_amount > 0" class="flex justify-between text-sm">
                        <span class="text-surface-500">
                            Discount <span v-if="order.discount_code" class="text-xs">({{ order.discount_code }})</span>
                        </span>
                        <span class="text-success-600">-{{ formatCurrency(order.discount_amount, order.currency) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold border-t border-surface-200 pt-2 mt-2">
                        <span class="text-surface-900">Total</span>
                        <span class="text-surface-900">{{ formatCurrency(order.total, order.currency) }}</span>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Payment details -->
        <Card title="Payment Details">
            <div class="space-y-2">
                <div v-for="(payment, idx) in order.payments" :key="idx"
                     class="flex items-center justify-between py-2 border-b border-surface-100 last:border-0">
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-600 capitalize">
                            {{ payment.gateway }}
                        </span>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', paymentStatusBadge(payment.status)]">
                            {{ payment.status }}
                        </span>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-semibold text-surface-900">{{ formatCurrency(payment.amount, order.currency) }}</p>
                        <p v-if="payment.paid_at" class="text-xs text-surface-400">{{ payment.paid_at }}</p>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Actions -->
        <div class="flex gap-3">
            <a href="/storefront"
               class="flex-1 text-center py-2 rounded-xl bg-primary-600 text-white font-medium hover:bg-primary-700 transition-colors">
                Continue Shopping
            </a>
            <a v-if="order.status === 'paid'" href="/courses"
               class="flex-1 text-center py-2 rounded-xl bg-success-600 text-white font-medium hover:bg-success-700 transition-colors">
                Go to My Courses
            </a>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, XCircle, Loader2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AppLayout });

defineProps({
    order: { type: Object, required: true },
    gatewayReturn: { type: Boolean, default: false },
});

function statusBadge(s) {
    return { pending: 'bg-warning-50 text-warning-700', paid: 'bg-success-50 text-success-700', failed: 'bg-error-50 text-error-700' }[s] || 'bg-surface-100 text-surface-600';
}

function paymentStatusBadge(s) {
    return { pending: 'bg-warning-50 text-warning-700', success: 'bg-success-50 text-success-700', failed: 'bg-error-50 text-error-700', refunded: 'bg-purple-50 text-purple-700' }[s] || 'bg-surface-100 text-surface-600';
}

function formatCurrency(amount, currency = 'NGN') {
    const symbols = { NGN: '₦', USD: '$', GBP: '£', EUR: '€' };
    const symbol = symbols[currency] || currency + ' ';
    return symbol + Number(amount).toLocaleString('en', { minimumFractionDigits: 0 });
}
</script>

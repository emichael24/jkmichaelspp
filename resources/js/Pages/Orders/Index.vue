<template>
    <Head title="Order History" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Order History</h1>
            <p class="text-surface-500 mt-1 text-sm">View your past purchases and receipts.</p>
        </div>

        <div class="space-y-4">
            <div v-for="order in orders.data" :key="order.uuid"
                 class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-sm transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(order.status)]">
                                {{ order.status }}
                            </span>
                            <span class="text-xs text-surface-400">{{ order.created_at }}</span>
                        </div>
                        <p class="text-sm font-semibold text-surface-900">
                            {{ order.items.map(i => i.product_name).join(', ') }}
                        </p>
                        <p class="text-xs text-surface-400 mt-1">{{ order.items_count }} item(s) · {{ order.gateway }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-surface-900">{{ formatCurrency(order.total, order.currency) }}</p>
                        <a :href="`/orders/${order.uuid}`"
                           class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                            View Details →
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState v-if="!orders.data?.length" title="No Orders" description="You haven't made any purchases yet." />

        <!-- Pagination -->
        <div v-if="orders.links && orders.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in orders.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AppLayout });

defineProps({
    orders: { type: Object, default: () => ({ data: [] }) },
});

function statusBadge(s) {
    const map = {
        pending: 'bg-warning-50 text-warning-700',
        paid: 'bg-success-50 text-success-700',
        failed: 'bg-error-50 text-error-700',
        refunded: 'bg-purple-50 text-purple-700',
        cancelled: 'bg-surface-100 text-surface-600',
    };
    return map[s] || 'bg-surface-100 text-surface-600';
}

function formatCurrency(amount, currency = 'NGN') {
    const symbols = { NGN: '₦', USD: '$', GBP: '£', EUR: '€' };
    const symbol = symbols[currency] || currency + ' ';
    return symbol + Number(amount).toLocaleString('en', { minimumFractionDigits: 0 });
}
</script>

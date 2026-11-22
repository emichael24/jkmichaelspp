<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Receipt, ArrowRight } from 'lucide-vue-next';
import WorkspaceShell from '@/Layouts/WorkspaceShell.vue';
import SimplePagination from '@/Components/UI/SimplePagination.vue';
defineProps({ organization: Object, orders: Object });
const money = (amount, currency) => `${currency} ${Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
</script>
<template>
    <Head title="Organization billing" />
    <WorkspaceShell workspace="organization"><div class="space-y-7">
        <header class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-widest text-primary-700">{{ organization.name }}</p><h1 class="mt-2 text-3xl font-display font-bold">Company billing</h1><p class="mt-2 text-sm text-surface-500">Review course purchases, recover pending payments and print your company receipts.</p></div><Link href="/organization/courses/catalog" class="rounded-xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white">Buy course places</Link></header>
        <div v-if="orders.data.length" class="space-y-3"><Link v-for="order in orders.data" :key="order.uuid" :href="`/organization/billing/${order.uuid}`" class="flex flex-wrap items-center gap-5 rounded-2xl border border-surface-200 bg-white p-5 shadow-sm hover:border-primary-300"><div class="rounded-xl bg-surface-50 p-3 text-primary-700"><Receipt class="h-6 w-6" /></div><div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ order.places }} course {{ order.places === 1 ? 'place' : 'places' }}<span v-if="order.test_mode" class="ml-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-900">Test payment</span></p><p class="mt-1 break-all text-xs text-surface-500">{{ order.uuid }}</p><p class="mt-1 text-xs text-surface-500">{{ order.created_at }}</p></div><div class="text-right"><p class="font-bold">{{ money(order.total, order.currency) }}</p><p class="mt-1 text-sm capitalize" :class="order.status === 'paid' ? 'text-emerald-700' : 'text-surface-500'">{{ order.status }}</p></div><ArrowRight class="h-4 w-4 text-surface-400" /></Link></div>
        <div v-else class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center"><Receipt class="mx-auto h-10 w-10 text-surface-300" /><h2 class="mt-4 text-lg font-semibold">Your company purchases will appear here</h2><p class="mx-auto mt-2 max-w-md text-sm text-surface-500">Platform-admin allocations are available under Courses & licenses. Company orders appear here when you buy course places.</p></div><SimplePagination :page="orders" />
    </div></WorkspaceShell>
</template>

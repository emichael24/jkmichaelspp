<template>
    <Head title="CRM Dashboard" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">CRM Dashboard</h1>
                <p class="text-surface-500 mt-1 text-sm">Manage contacts, deals, and follow-up activities.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/erp/contacts">
                    <Button variant="secondary" size="sm">
                        <Users class="w-4 h-4 mr-1.5" /> All Contacts
                    </Button>
                </Link>
                <Link href="/admin/erp/deals">
                    <Button variant="secondary" size="sm">
                        <Briefcase class="w-4 h-4 mr-1.5" /> Pipeline
                    </Button>
                </Link>
                <Link href="/admin/erp/messaging">
                    <Button variant="primary" size="sm">
                        <MessageSquare class="w-4 h-4 mr-1.5" /> Messaging
                    </Button>
                </Link>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-surface-500 font-medium">{{ stat.label }}</p>
                    <div :class="['w-7 h-7 rounded-lg flex items-center justify-center', stat.iconBg]">
                        <component :is="stat.icon" :class="['w-3.5 h-3.5', stat.iconColor]" />
                    </div>
                </div>
                <p class="text-xl font-bold text-surface-900">{{ stat.value }}</p>
                <p v-if="stat.sub" class="text-xs text-surface-400 mt-0.5">{{ stat.sub }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Contacts -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-surface-100">
                    <h2 class="font-semibold text-surface-900">Recent Contacts</h2>
                    <Link href="/admin/erp/contacts" class="text-xs text-primary-600 hover:underline">View all</Link>
                </div>
                <div class="divide-y divide-surface-100">
                    <div v-for="contact in recentContacts" :key="contact.id" class="flex items-center gap-4 px-5 py-4">
                        <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-sm font-bold text-primary-700 flex-shrink-0">
                            {{ initials(contact) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <Link :href="`/admin/erp/contacts/${contact.id}`">
                                <p class="font-medium text-surface-900 text-sm hover:text-primary-600 truncate">{{ contact.first_name }} {{ contact.last_name }}</p>
                            </Link>
                            <p class="text-xs text-surface-400 truncate">{{ contact.email }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :variant="typeBadgeVariant(contact.contact_type)" size="sm">{{ contact.contact_type }}</Badge>
                            <span class="text-xs text-surface-400">{{ formatDate(contact.created_at) }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="!recentContacts.length" class="p-10 text-center">
                    <Users class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                    <p class="text-sm text-surface-400">No contacts yet</p>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100">
                    <h2 class="font-semibold text-surface-900">Today's Activity</h2>
                </div>
                <div class="divide-y divide-surface-100 max-h-80 overflow-y-auto">
                    <div v-for="activity in recentActivities" :key="activity.id" class="flex items-start gap-3 px-5 py-3">
                        <div :class="['w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5', activityBg(activity.activity_type)]">
                            <component :is="activityIcon(activity.activity_type)" class="w-3.5 h-3.5 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-surface-800 font-medium">{{ activity.subject }}</p>
                            <p class="text-xs text-surface-400 truncate">{{ activity.contact?.first_name }} {{ activity.contact?.last_name }}</p>
                            <p class="text-xs text-surface-400">{{ formatTime(activity.created_at) }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="!recentActivities.length" class="p-8 text-center">
                    <ClipboardList class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                    <p class="text-sm text-surface-400">No activity today</p>
                </div>
            </div>
        </div>

        <!-- Open Deals Summary -->
        <div v-if="openDeals.length" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-surface-100">
                <h2 class="font-semibold text-surface-900">Open Deals</h2>
                <Link href="/admin/erp/deals" class="text-xs text-primary-600 hover:underline">View pipeline</Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-2.5 font-semibold text-surface-600 text-xs">Deal</th>
                            <th class="text-left px-5 py-2.5 font-semibold text-surface-600 text-xs">Contact</th>
                            <th class="text-left px-5 py-2.5 font-semibold text-surface-600 text-xs">Stage</th>
                            <th class="text-right px-5 py-2.5 font-semibold text-surface-600 text-xs">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="deal in openDeals.slice(0, 5)" :key="deal.id" class="hover:bg-surface-50">
                            <td class="px-5 py-3 font-medium text-surface-900">{{ deal.name }}</td>
                            <td class="px-5 py-3 text-surface-600">{{ deal.contact?.first_name }} {{ deal.contact?.last_name }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full text-xs font-medium">{{ deal.stage }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-surface-900">₦{{ Number(deal.amount).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import {
    Users, Briefcase, MessageSquare, Phone, Mail, ClipboardList,
    TrendingUp, DollarSign, UserCheck, Activity,
} from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    statsData: { type: Object, default: () => ({}) },
    recentContacts: { type: Array, default: () => [] },
    recentActivities: { type: Array, default: () => [] },
    openDeals: { type: Array, default: () => [] },
});

const stats = computed(() => [
    { label: 'Total Contacts', value: props.statsData.total_contacts ?? 0, icon: Users, iconBg: 'bg-primary-100', iconColor: 'text-primary-700' },
    { label: 'Leads', value: props.statsData.leads ?? 0, icon: UserCheck, iconBg: 'bg-amber-100', iconColor: 'text-amber-700' },
    { label: 'Prospects', value: props.statsData.prospects ?? 0, icon: TrendingUp, iconBg: 'bg-blue-100', iconColor: 'text-blue-700' },
    { label: 'Open Deals', value: props.statsData.open_deals ?? 0, icon: Briefcase, iconBg: 'bg-success-100', iconColor: 'text-success-700' },
    { label: 'Pipeline Value', value: '₦' + Number(props.statsData.pipeline_value ?? 0).toLocaleString(), icon: DollarSign, iconBg: 'bg-purple-100', iconColor: 'text-purple-700' },
    { label: 'Activities Today', value: props.statsData.activities_today ?? 0, icon: Activity, iconBg: 'bg-orange-100', iconColor: 'text-orange-700' },
]);

function initials(c) {
    return (c.first_name?.[0] || '') + (c.last_name?.[0] || '');
}

function typeBadgeVariant(type) {
    return { lead: 'warning', prospect: 'info', customer: 'success', partner: 'secondary' }[type] || 'secondary';
}

function activityBg(type) {
    return { call: 'bg-blue-500', email: 'bg-purple-500', whatsapp: 'bg-green-500', meeting: 'bg-amber-500', note: 'bg-surface-500' }[type] || 'bg-surface-400';
}

function activityIcon(type) {
    return { call: Phone, email: Mail, whatsapp: MessageSquare, meeting: Users, note: ClipboardList }[type] || Activity;
}

function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function formatTime(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}
</script>

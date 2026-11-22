<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Plus, Search, ArrowUpRight, Users, ShieldCheck, Armchair } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ organizations: Object, filters: Object, summary: Object });
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const loading = ref(false);
const filter = () => router.get('/admin/organizations', { search: search.value || undefined, status: status.value || undefined }, {
    preserveState: true, replace: true, onStart: () => loading.value = true, onFinish: () => loading.value = false,
});
const clear = () => { search.value = ''; status.value = ''; filter(); };
const committed = org => Number(org.active_members_count) + Number(org.reserved_seats_count);
const percentage = org => org.max_seats ? Math.min(100, Math.round(committed(org) / org.max_seats * 100)) : 0;
</script>

<template>
    <Head title="Organizations" />
    <div class="space-y-6">
        <header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
            <div class="flex items-start gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-primary-100 bg-primary-50 text-primary-700"><Building2 class="h-6 w-6" aria-hidden="true" /></span>
                <div><h1 class="text-2xl font-display font-bold tracking-tight text-surface-900 sm:text-3xl">Organizations</h1><p class="mt-1 max-w-xl text-sm text-surface-500">Set up company workspaces, allocate seats and give the right people access.</p></div>
            </div>
            <Button :icon="Plus" @click="router.visit('/admin/organizations/create')">Create organization</Button>
        </header>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="metric in [{ label: 'Organizations', value: summary.total, icon: Building2 }, { label: 'Active workspaces', value: summary.active, icon: ShieldCheck }, { label: 'Active members', value: summary.members, icon: Users }, { label: 'Seats in active workspaces', value: summary.seats, icon: Armchair }]" :key="metric.label" class="rounded-2xl border border-surface-200 bg-white p-4 sm:p-5">
                <div class="flex items-center justify-between gap-2"><p class="text-xs font-medium text-surface-500">{{ metric.label }}</p><component :is="metric.icon" class="h-4 w-4 shrink-0 text-surface-400" aria-hidden="true" /></div>
                <p class="mt-3 text-2xl font-semibold tabular-nums text-surface-900">{{ Number(metric.value).toLocaleString() }}</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white" aria-label="Organization directory" :aria-busy="loading">
            <form @submit.prevent="filter" class="flex flex-col gap-3 border-b border-surface-200 p-4 sm:flex-row sm:items-end sm:p-5">
                <Input v-model="search" label="Search organizations" placeholder="Company name or contact email" :icon="Search" class="sm:flex-1" maxlength="100" />
                <div class="sm:w-44"><label for="organization-status" class="mb-1.5 block text-sm font-medium text-surface-700">Status</label><select id="organization-status" v-model="status" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"><option value="">All organizations</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="flex gap-2"><Button type="submit" variant="secondary" :loading="loading">Search</Button><Button v-if="search || status" variant="ghost" @click="clear">Clear</Button></div>
            </form>

            <div v-if="organizations.data.length" class="divide-y divide-surface-100">
                <article v-for="org in organizations.data" :key="org.id" class="grid items-center gap-5 p-5 transition hover:bg-surface-50/60 md:grid-cols-[minmax(0,1fr)_220px_160px]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2"><Link :href="`/admin/organizations/${org.id}/edit`" class="break-words text-base font-semibold text-surface-900 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">{{ org.name }}</Link><Badge :variant="org.is_active ? 'success' : 'neutral'" dot>{{ org.is_active ? 'Active' : 'Inactive' }}</Badge></div>
                        <p class="mt-1 break-all text-sm text-surface-500">{{ org.contact_email }}</p>
                        <p class="mt-2 text-xs text-surface-500">{{ org.industry || 'Industry not specified' }} · {{ org.country }}</p>
                    </div>
                    <div>
                        <div class="flex justify-between gap-2 text-xs"><span class="font-medium text-surface-700">Seat usage</span><span class="tabular-nums text-surface-500">{{ committed(org) }} / {{ org.max_seats }}</span></div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-100" role="meter" :aria-label="`${org.name} allocated seats in use`" :aria-valuenow="committed(org)" :aria-valuemin="0" :aria-valuemax="Math.max(org.max_seats, committed(org))"><div :class="['h-full rounded-full', percentage(org) >= 90 ? 'bg-amber-500' : 'bg-primary-500']" :style="{ width: percentage(org) + '%' }" /></div>
                        <p class="mt-2 text-xs text-surface-500">{{ org.active_members_count }} active · {{ org.reserved_seats_count }} invited</p>
                    </div>
                    <div class="flex items-center justify-between gap-3 md:flex-col md:items-end">
                        <span :class="['text-xs font-medium', Number(org.administrators_count) ? 'text-surface-500' : 'text-amber-700']">{{ Number(org.administrators_count) ? `${org.administrators_count} administrator${Number(org.administrators_count) === 1 ? '' : 's'}` : 'Administrator needed' }}</span>
                        <Link :href="`/admin/organizations/${org.id}/edit`" :aria-label="`Manage ${org.name}`" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-surface-200 px-3 text-sm font-semibold text-surface-700 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500">Manage <ArrowUpRight class="h-4 w-4" aria-hidden="true" /></Link>
                    </div>
                </article>
            </div>
            <div v-else class="px-6 py-14 text-center">
                <Building2 class="mx-auto h-9 w-9 text-surface-300" aria-hidden="true" /><h2 class="mt-4 text-lg font-semibold text-surface-900">{{ filters.search || filters.status ? 'No matching organizations' : 'Your first company workspace starts here' }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-surface-500">{{ filters.search || filters.status ? 'Try another company name or clear the filters.' : 'Create an organization, set its seat allocation and appoint an administrator to manage its people.' }}</p>
                <Button v-if="filters.search || filters.status" variant="secondary" class="mt-5" @click="clear">Clear filters</Button><Button v-else :icon="Plus" class="mt-5" @click="router.visit('/admin/organizations/create')">Create organization</Button>
            </div>
            <nav v-if="organizations.last_page > 1" aria-label="Organization pages" class="flex items-center justify-between gap-3 border-t border-surface-200 p-4 text-sm">
                <span class="text-surface-500">Page {{ organizations.current_page }} of {{ organizations.last_page }}</span><div class="flex gap-2"><Button variant="secondary" :disabled="!organizations.prev_page_url" @click="router.visit(organizations.prev_page_url)">Previous</Button><Button variant="secondary" :disabled="!organizations.next_page_url" @click="router.visit(organizations.next_page_url)">Next</Button></div>
            </nav>
        </section>
    </div>
</template>

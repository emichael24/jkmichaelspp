<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, ArrowRight, ShoppingBag, Users } from 'lucide-vue-next';
import WorkspaceShell from '@/Layouts/WorkspaceShell.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import SimplePagination from '@/Components/UI/SimplePagination.vue';
const props = defineProps({ organization: Object, licenses: Object, filters: Object });
const search = ref(props.filters.search || '');
const filter = () => router.get('/organization/courses', { search: search.value }, { preserveState: true });
</script>
<template>
    <Head title="Organization courses" />
    <WorkspaceShell workspace="organization">
        <div class="space-y-7">
            <header class="flex flex-wrap items-start justify-between gap-5"><div><p class="text-xs font-semibold uppercase tracking-widest text-primary-700">{{ organization.name }}</p><h1 class="mt-2 text-3xl font-display font-bold">Learning for your team</h1><p class="mt-2 max-w-2xl text-sm leading-relaxed text-surface-500">Assign your purchased or allocated course places to people and departments. Track who is learning and keep control of your available places.</p></div><Link href="/organization/courses/catalog" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-3 font-semibold text-white"><ShoppingBag class="h-4 w-4" /> Buy course places</Link></header>
            <div class="flex gap-3 rounded-2xl border border-primary-100 bg-primary-50 p-5 text-sm leading-relaxed text-primary-900"><Users class="mt-0.5 h-5 w-5 shrink-0" /><p>Organization seats let people join your workspace. Course places give them access to a specific course. Each assignment keeps its place until you release it, including after completion.</p></div>
            <form @submit.prevent="filter" class="flex flex-wrap items-end gap-3"><Input v-model="search" label="Find an allocated course" placeholder="Course title" class="w-full sm:max-w-sm" maxlength="100" /><Button type="submit" variant="secondary">Search</Button></form>
            <div v-if="licenses.data.length" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="license in licenses.data" :key="license.id" class="flex flex-col rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3"><span class="rounded-xl bg-surface-50 p-3 text-primary-700"><BookOpen class="h-6 w-6" /></span><span :class="license.active ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'" class="rounded-full px-3 py-1 text-xs font-semibold">{{ license.active ? 'Active' : 'Unavailable' }}</span></div>
                    <p class="mt-5 text-xs text-surface-500">{{ license.source }} · License #{{ license.id }}</p><h2 class="mt-1 text-lg font-semibold text-surface-900">{{ license.course?.title || 'Archived course' }}</h2>
                    <div class="mt-6 flex items-end justify-between"><p><strong class="text-3xl font-bold">{{ license.available }}</strong><span class="ml-2 text-sm text-surface-500">places available</span></p><p class="text-xs text-surface-500">{{ license.used }} / {{ license.quantity }} assigned</p></div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-100" role="meter" :aria-valuenow="license.used" :aria-valuemax="license.quantity" :aria-valuemin="0" aria-label="Assigned course places"><div class="h-full bg-primary-500" :style="{ width: `${Math.min(100, license.used / license.quantity * 100)}%` }" /></div>
                    <p class="mt-3 text-xs text-surface-500">{{ license.expires_at ? `Access through ${license.expires_at}` : 'No scheduled expiry' }}</p>
                    <Link :href="`/organization/licenses/${license.id}`" class="mt-6 flex items-center justify-between rounded-xl border border-surface-200 px-4 py-3 text-sm font-semibold text-primary-700">Manage learners <ArrowRight class="h-4 w-4" /></Link>
                </article>
            </div>
            <div v-else class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center"><BookOpen class="mx-auto h-10 w-10 text-surface-300" /><h2 class="mt-4 text-lg font-semibold">{{ search ? 'No courses match your search' : 'Your team’s learning starts here' }}</h2><p class="mx-auto mt-2 max-w-md text-sm text-surface-500">{{ search ? 'Try a different course title.' : 'Buy course places or ask your platform administrator to allocate a course to your organization.' }}</p><Link href="/organization/courses/catalog" class="mt-6 inline-block font-semibold text-primary-700">Browse courses →</Link></div>
            <SimplePagination :page="licenses" />
        </div>
    </WorkspaceShell>
</template>

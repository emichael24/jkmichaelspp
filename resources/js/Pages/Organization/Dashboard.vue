<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import WorkspaceShell from '@/Layouts/WorkspaceShell.vue';
import { Building2, Users, BookOpen, CheckCircle2, Search, ArrowLeft, ArrowRight } from 'lucide-vue-next';
const props = defineProps({ organization: Object, stats: Object, people: Object, filters: Object });
const search = ref(props.filters.search || '');
const searchPeople = () => router.get('/organization', { search: search.value }, { preserveState: true, replace: true });
</script>
<template>
    <Head title="Organization overview" />
    <WorkspaceShell workspace="organization">
        <div class="space-y-7">
            <header class="flex flex-wrap items-center justify-between gap-4"><div><p class="mb-2 text-xs font-semibold uppercase tracking-widest text-primary-700">Organization overview</p><h1 class="text-2xl font-display font-bold sm:text-3xl">{{ organization.name }}</h1><p class="mt-2 text-sm text-surface-500">Your people and their learning, in one place.</p></div><span class="rounded-full border border-surface-200 bg-white px-4 py-2 text-xs text-surface-600">{{ stats.activePeople }} / {{ organization.max_seats }} active seats</span></header>
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <div v-for="metric in [{ label: 'People', value: stats.people, icon: Users }, { label: 'Active accounts', value: stats.activePeople, icon: Building2 }, { label: 'Enrollments', value: stats.enrollments, icon: BookOpen }, { label: 'Completions', value: stats.completed, icon: CheckCircle2 }]" :key="metric.label" class="rounded-2xl border border-surface-200 bg-white p-5"><component :is="metric.icon" class="mb-4 h-5 w-5 text-surface-400" /><p class="text-3xl font-semibold tracking-tight">{{ metric.value }}</p><p class="mt-1 text-xs text-surface-500">{{ metric.label }}</p></div>
            </div>
            <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-surface-100 p-5"><div><h2 class="font-semibold">People directory</h2><p class="mt-1 text-xs text-surface-500">Learning activity within your organization</p></div><form @submit.prevent="searchPeople" class="flex w-full gap-2 sm:w-auto"><label for="people-search" class="sr-only">Find people by name or email</label><input id="people-search" v-model="search" type="search" placeholder="Name or email" class="min-w-0 flex-1 rounded-lg border border-surface-200 px-3 text-sm" /><button class="rounded-lg bg-surface-900 px-4 text-sm font-medium text-white">Search</button></form></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Organization people and course completion counts</caption><thead class="bg-surface-50 text-xs text-surface-500"><tr><th scope="col" class="px-5 py-3 font-medium">Person</th><th scope="col" class="px-5 py-3 font-medium">Account</th><th scope="col" class="px-5 py-3 font-medium">Enrolled</th><th scope="col" class="px-5 py-3 font-medium">Completed</th></tr></thead><tbody class="divide-y divide-surface-100"><tr v-for="person in people.data" :key="person.id"><td class="px-5 py-4"><p class="font-medium">{{ person.full_name }}</p><p class="mt-1 text-xs text-surface-500">{{ person.email }}</p></td><td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs', person.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-surface-100 text-surface-600']">{{ person.is_active ? 'Active' : 'Inactive' }}</span></td><td class="px-5 py-4 tabular-nums">{{ person.enrollments_count }}</td><td class="px-5 py-4 tabular-nums">{{ person.completed_count }}</td></tr></tbody></table></div>
                <p v-if="!people.data.length" class="p-12 text-center text-sm text-surface-500">No people match your search.</p>
                <div class="flex items-center justify-between border-t border-surface-100 px-5 py-4 text-xs text-surface-500"><span>{{ people.total }} people · Page {{ people.current_page }} of {{ people.last_page }}</span><div class="flex gap-4"><Link v-if="people.prev_page_url" :href="people.prev_page_url" class="py-2 font-medium text-surface-700">Previous</Link><Link v-if="people.next_page_url" :href="people.next_page_url" class="py-2 font-medium text-surface-700">Next</Link></div></div>
            </section>
        </div>
    </WorkspaceShell>
</template>

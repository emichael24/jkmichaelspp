<template>
    <Head title="Cohorts" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Cohorts</h1>
                <p class="text-sm text-surface-500 mt-1">Manage your learning cohorts</p>
            </div>
            <a href="/facilitator/cohorts/create" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                New Cohort
            </a>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search cohorts..."
                    class="w-full pl-9 pr-4 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    @input="applyFilters"
                />
            </div>
            <select
                v-model="statusFilter"
                class="px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                @change="applyFilters"
            >
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <!-- Cohort Grid -->
        <div v-if="cohorts.data && cohorts.data.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <a
                v-for="cohort in cohorts.data"
                :key="cohort.id"
                :href="`/facilitator/cohorts/${cohort.id}`"
                class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md hover:border-primary-200 transition-all group"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-surface-900 truncate group-hover:text-primary-700">{{ cohort.name }}</h3>
                        <p class="text-xs text-surface-500 mt-0.5">{{ cohort.course?.title }}</p>
                    </div>
                    <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', statusClass(cohort.status)]">
                        {{ cohort.status }}
                    </span>
                </div>

                <div class="flex items-center justify-between text-xs text-surface-500 mt-4">
                    <span class="flex items-center gap-1">
                        <Users class="w-3.5 h-3.5" />
                        {{ cohort.members_count }} learners
                    </span>
                    <span>{{ formatDate(cohort.start_date) }} – {{ formatDate(cohort.end_date) }}</span>
                </div>
            </a>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Users class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500">No cohorts found.</p>
        </div>

        <!-- Pagination -->
        <div v-if="cohorts.links && cohorts.last_page > 1" class="flex justify-center gap-1">
            <a
                v-for="link in cohorts.links"
                :key="link.label"
                :href="link.url"
                :class="[
                    'px-3 py-1.5 rounded-lg text-sm',
                    link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100',
                    !link.url ? 'opacity-50 pointer-events-none' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Users, Plus, Search } from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohorts: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

let debounce = null;
function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/facilitator/cohorts', {
            search: searchQuery.value || undefined,
            status: statusFilter.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

function statusClass(status) {
    const map = {
        draft: 'bg-surface-100 text-surface-600',
        active: 'bg-success-50 text-success-700',
        completed: 'bg-primary-50 text-primary-700',
        archived: 'bg-surface-100 text-surface-400',
    };
    return map[status] || map.draft;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

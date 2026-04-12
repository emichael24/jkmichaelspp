<template>
    <Head title="Internship Tracks" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-display font-bold text-surface-900">Internship Tracks</h1>
            <a href="/admin/internships/create" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                New Track
            </a>
        </div>

        <!-- Search -->
        <div>
            <input v-model="search" @input="debouncedSearch" type="text" placeholder="Search tracks..." class="w-full max-w-sm px-3 py-2 border border-surface-200 rounded-lg text-sm" />
        </div>

        <!-- Track cards -->
        <div v-if="tracks.data && tracks.data.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a
                v-for="track in tracks.data"
                :key="track.id"
                :href="`/admin/internships/${track.id}`"
                class="bg-white rounded-xl border border-surface-200/60 p-5 hover:shadow-md transition-shadow block"
            >
                <h3 class="font-semibold text-surface-900 mb-1">{{ track.title }}</h3>
                <p v-if="track.description" class="text-sm text-surface-500 line-clamp-2 mb-3">{{ track.description }}</p>

                <div class="flex items-center gap-4 text-xs text-surface-400">
                    <span class="flex items-center gap-1"><Clock class="w-3.5 h-3.5" /> {{ track.duration_weeks }} weeks</span>
                    <span class="flex items-center gap-1"><Users class="w-3.5 h-3.5" /> {{ track.enrollments_count || 0 }} enrolled</span>
                    <span class="flex items-center gap-1"><Milestone class="w-3.5 h-3.5" /> {{ track.milestones_count || 0 }} milestones</span>
                </div>

                <div class="flex items-center justify-between mt-3 pt-3 border-t border-surface-100">
                    <span class="text-sm font-bold text-surface-900">{{ formatPrice(track.price, track.currency) }}</span>
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', track.is_published ? 'bg-success-50 text-success-700' : 'bg-surface-100 text-surface-500']">
                        {{ track.is_published ? 'Published' : 'Draft' }}
                    </span>
                </div>
            </a>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Briefcase class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500">No internship tracks yet.</p>
        </div>

        <!-- Pagination -->
        <div v-if="tracks.links && tracks.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in tracks.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Clock, Users, Milestone, Briefcase } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    tracks: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');
let timer;
function debouncedSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/internships', { search: search.value }, { preserveState: true, replace: true });
    }, 300);
}

function formatPrice(price, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(price || 0);
}
</script>

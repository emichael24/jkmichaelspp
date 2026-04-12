<template>
    <Head title="Export Portfolio" />

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="/portfolio" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <h1 class="text-xl font-display font-bold text-surface-900">Portfolio Preview</h1>
        </div>

        <div class="bg-white rounded-xl border border-surface-200/60 p-8 print:border-0 print:shadow-none" id="portfolio-content">
            <!-- Header -->
            <div class="text-center mb-8 border-b border-surface-200 pb-6">
                <h1 class="text-2xl font-display font-bold text-surface-900">{{ user.first_name }} {{ user.last_name }}</h1>
                <p class="text-surface-500 mt-1">Professional Portfolio</p>
                <p class="text-xs text-surface-400 mt-2">Generated {{ new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
            </div>

            <!-- Items -->
            <div class="space-y-6">
                <div v-for="(item, idx) in items" :key="item.id">
                    <div class="flex items-start gap-4">
                        <span class="text-sm font-bold text-primary-600 bg-primary-50 w-8 h-8 rounded-lg flex items-center justify-center shrink-0">{{ idx + 1 }}</span>
                        <div class="flex-1">
                            <h3 class="font-semibold text-surface-900">{{ item.title }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-surface-500 capitalize">{{ item.type.replace(/_/g, ' ') }}</span>
                                <span v-if="item.enrollment?.track" class="text-xs text-surface-400">· {{ item.enrollment.track.title }}</span>
                                <span v-if="item.sponsor_score != null" class="text-xs font-medium text-accent-600">· Score: {{ item.sponsor_score }}/5</span>
                            </div>
                            <p v-if="item.description" class="text-sm text-surface-600 mt-2">{{ item.description }}</p>
                            <div v-if="item.skills_demonstrated?.length" class="flex flex-wrap gap-1 mt-2">
                                <span v-for="(s, i) in item.skills_demonstrated" :key="i"
                                      class="px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full text-[10px] font-medium">{{ s }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="idx < items.length - 1" class="border-b border-surface-100 mt-4"></div>
                </div>
            </div>

            <div v-if="!items.length" class="text-center py-12 text-surface-400">
                No portfolio items to export.
            </div>
        </div>

        <div class="flex justify-center print:hidden">
            <button @click="printPortfolio" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                <Printer class="w-4 h-4 inline mr-2" /> Print / Save as PDF
            </button>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    items: { type: Array, default: () => [] },
    user: { type: Object, required: true },
});

function printPortfolio() {
    window.print();
}
</script>

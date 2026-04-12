<template>
    <Head title="My Certificates" />

    <div class="space-y-6">
        <h1 class="text-xl font-display font-bold text-surface-900">My Certificates</h1>

        <div v-if="certificates.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="cert in certificates" :key="cert.id" class="bg-white rounded-xl border border-surface-200/60 overflow-hidden">
                <!-- Certificate header with gradient -->
                <div class="bg-gradient-to-r from-primary-600 to-accent-500 p-4 text-white">
                    <div class="flex items-center gap-2 mb-2">
                        <Award class="w-5 h-5" />
                        <span class="text-xs font-medium opacity-80">{{ cert.certificate_number }}</span>
                    </div>
                    <h3 class="font-semibold text-sm">{{ cert.title }}</h3>
                </div>

                <div class="p-4 space-y-3">
                    <div class="flex items-center justify-between text-xs text-surface-500">
                        <span>Issued: {{ formatDate(cert.issued_at) }}</span>
                        <span v-if="cert.expires_at">Expires: {{ formatDate(cert.expires_at) }}</span>
                    </div>

                    <div v-if="cert.metadata?.skills?.length" class="flex flex-wrap gap-1">
                        <span v-for="(s, i) in cert.metadata.skills" :key="i"
                              class="px-1.5 py-0.5 bg-primary-50 text-primary-700 rounded text-[10px] font-medium">{{ s }}</span>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-surface-100">
                        <a :href="`/certificates/${cert.uuid}`" class="flex-1 text-center px-3 py-2 bg-surface-100 text-surface-700 rounded-lg text-xs font-medium hover:bg-surface-200">
                            View
                        </a>
                        <a :href="`/certificates/${cert.uuid}/download`" class="flex-1 text-center px-3 py-2 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700">
                            <Download class="w-3.5 h-3.5 inline mr-1" /> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Award class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <h2 class="text-lg font-semibold text-surface-700 mb-2">No Certificates Yet</h2>
            <p class="text-sm text-surface-500">Complete a course or internship to earn your first certificate.</p>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { Award, Download } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    certificates: { type: Array, default: () => [] },
});

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

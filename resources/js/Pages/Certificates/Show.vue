<template>
    <Head :title="certificate.title" />

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="/certificates" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </a>
            <h1 class="text-xl font-display font-bold text-surface-900">Certificate</h1>
        </div>

        <!-- Certificate Card -->
        <div class="bg-white rounded-2xl border-2 border-primary-200 overflow-hidden shadow-lg">
            <div class="bg-gradient-to-r from-primary-600 via-accent-500 to-primary-600 p-6 text-center text-white">
                <Award class="w-10 h-10 mx-auto mb-2 opacity-80" />
                <p class="text-xs font-medium opacity-70 uppercase tracking-wider">JK Michaels Professional Mastery</p>
                <h2 class="text-lg font-display font-bold mt-1">Certificate of Completion</h2>
            </div>

            <div class="p-8 text-center space-y-4">
                <p class="text-sm text-surface-500">This certifies that</p>
                <h3 class="text-xl font-display font-bold text-surface-900">
                    {{ certificate.user?.first_name }} {{ certificate.user?.last_name }}
                </h3>
                <p class="text-sm text-surface-500">has successfully completed</p>
                <h4 class="text-lg font-semibold text-primary-700">{{ certificate.title }}</h4>

                <div v-if="certificate.metadata?.skills?.length" class="flex flex-wrap justify-center gap-1 pt-2">
                    <span v-for="(s, i) in certificate.metadata.skills" :key="i"
                          class="px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full text-xs font-medium">{{ s }}</span>
                </div>

                <div class="pt-4 border-t border-surface-100 flex items-center justify-between text-xs text-surface-400">
                    <span>{{ certificate.certificate_number }}</span>
                    <span>Issued: {{ formatDate(certificate.issued_at) }}</span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-center gap-3">
            <a :href="`/certificates/${certificate.uuid}/download`"
               class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                <Download class="w-4 h-4 inline mr-2" /> Download Certificate
            </a>
            <a :href="`/certificates/${certificate.uuid}/verify`"
               class="px-6 py-2 bg-surface-100 text-surface-700 rounded-lg text-sm font-medium hover:bg-surface-200">
                <ShieldCheck class="w-4 h-4 inline mr-2" /> Verify
            </a>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Award, Download, ShieldCheck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    certificate: { type: Object, required: true },
});

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
</script>

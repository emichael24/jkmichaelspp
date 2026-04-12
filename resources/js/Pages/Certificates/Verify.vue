<template>
    <Head title="Verify Certificate" />

    <div class="max-w-md mx-auto mt-12 space-y-6">
        <div class="text-center">
            <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4', isValid ? 'bg-success-100' : 'bg-danger-100']">
                <ShieldCheck v-if="isValid" class="w-8 h-8 text-success-600" />
                <ShieldX v-else class="w-8 h-8 text-danger-600" />
            </div>
            <h1 class="text-xl font-display font-bold" :class="isValid ? 'text-success-700' : 'text-danger-700'">
                {{ isValid ? 'Certificate Verified' : 'Certificate Invalid' }}
            </h1>
        </div>

        <div class="bg-white rounded-xl border border-surface-200/60 p-6 space-y-3">
            <div>
                <p class="text-xs text-surface-400 uppercase tracking-wider">Certificate Number</p>
                <p class="text-sm font-medium text-surface-900">{{ certificate.certificate_number }}</p>
            </div>
            <div>
                <p class="text-xs text-surface-400 uppercase tracking-wider">Holder</p>
                <p class="text-sm font-medium text-surface-900">{{ certificate.user?.first_name }} {{ certificate.user?.last_name }}</p>
            </div>
            <div>
                <p class="text-xs text-surface-400 uppercase tracking-wider">Program</p>
                <p class="text-sm font-medium text-surface-900">{{ certificate.title }}</p>
            </div>
            <div>
                <p class="text-xs text-surface-400 uppercase tracking-wider">Issued</p>
                <p class="text-sm font-medium text-surface-900">{{ formatDate(certificate.issued_at) }}</p>
            </div>
            <div v-if="certificate.expires_at">
                <p class="text-xs text-surface-400 uppercase tracking-wider">Expires</p>
                <p class="text-sm font-medium text-surface-900">{{ formatDate(certificate.expires_at) }}</p>
            </div>
        </div>

        <p class="text-center text-xs text-surface-400">
            Verified by JK Michaels Professional Mastery Platform
        </p>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ShieldCheck, ShieldX } from 'lucide-vue-next';

defineProps({
    certificate: { type: Object, required: true },
    isValid: { type: Boolean, default: true },
});

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
</script>

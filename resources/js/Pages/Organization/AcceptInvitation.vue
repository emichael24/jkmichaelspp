<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, ShieldCheck } from 'lucide-vue-next';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
const props = defineProps({ invitation: Object, existingAccount: Boolean, signedInEmail: String, acceptUrl: String });
const form = useForm({ first_name: props.invitation.first_name || '', last_name: props.invitation.last_name || '', password: '', password_confirmation: '' });
const matchingAccount = computed(() => props.signedInEmail?.toLowerCase() === props.invitation.email.toLowerCase());
const submit = () => form.post(props.acceptUrl, { onFinish: () => form.reset('password', 'password_confirmation') });
const switchAccount = () => router.post('/logout', {}, { onSuccess: () => router.visit(props.acceptUrl) });
</script>
<template>
    <Head title="Your organization invitation" />
    <main class="flex min-h-screen items-center justify-center bg-surface-50 px-4 py-12"><div class="w-full max-w-lg"><Link href="/" class="mb-8 block text-center font-display text-xl font-bold tracking-tight text-primary-800">JK MICHAELS</Link><section class="rounded-3xl border border-surface-200 bg-white p-6 shadow-sm sm:p-9"><span class="inline-flex rounded-2xl bg-primary-50 p-3 text-primary-700"><Building2 class="h-7 w-7" /></span><p class="mt-6 text-xs font-semibold uppercase tracking-widest text-surface-400">You’re invited</p><h1 class="mt-2 text-2xl font-display font-bold">Join {{ invitation.organization }}</h1><p class="mt-3 text-sm leading-relaxed text-surface-500">Your learning workspace is ready. This invitation is for <strong class="break-all font-medium text-surface-700">{{ invitation.email }}</strong>.</p>
        <div v-if="signedInEmail && !matchingAccount" class="mt-6 space-y-4"><p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">You are signed in as {{ signedInEmail }}. Switch to the invited account to continue.</p><Button class="w-full" @click="switchAccount">Switch account</Button></div>
        <div v-else-if="existingAccount && !signedInEmail" class="mt-6 space-y-4"><p class="text-sm text-surface-600">Sign in with your existing account to accept. You will return to this invitation.</p><Link href="/login" class="block rounded-xl bg-primary-600 px-4 py-3 text-center text-sm font-semibold text-white">Sign in to continue</Link></div>
        <form v-else @submit.prevent="submit" class="mt-6 space-y-5"><div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800"><p v-for="error in form.errors" :key="error">{{ error }}</p></div><template v-if="!existingAccount"><div class="grid gap-4 sm:grid-cols-2"><Input v-model="form.first_name" label="First name" autocomplete="given-name" required /><Input v-model="form.last_name" label="Last name" autocomplete="family-name" required /></div><Input v-model="form.password" label="Create a password" type="password" autocomplete="new-password" hint="Use at least 12 characters, including letters and numbers." required /><Input v-model="form.password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required /></template><p v-if="existingAccount" class="text-sm text-surface-600">You will join as a learner using your current account.</p><Button type="submit" class="w-full" :loading="form.processing">{{ existingAccount ? 'Accept invitation' : 'Create account & join' }}</Button></form>
        <div class="mt-7 flex gap-2 border-t border-surface-100 pt-5 text-xs leading-relaxed text-surface-500"><ShieldCheck class="h-4 w-4 shrink-0" /><p>Your organization administrator can manage your account access and see learning progress associated with your membership.</p></div>
    </section></div></main>
</template>

<script setup>
import { computed, watch, nextTick, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CreditCard, ShieldCheck, ExternalLink } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ paystack: Object });
const errorsPanel = ref(null);
const form = useForm({ mode: props.paystack.mode, version: props.paystack.version, paystack_public_key: '', paystack_secret_key: '', current_password: '', activate: false, confirm_live: false });
const profile = computed(() => props.paystack.profiles[form.mode]);
const switchingLive = computed(() => form.activate && form.mode === 'live' && props.paystack.mode !== 'live');
watch(() => form.mode, () => { form.reset('paystack_public_key', 'paystack_secret_key', 'current_password', 'activate', 'confirm_live'); form.clearErrors(); });
function save() {
    form.put(route('admin.settings.payments.update'), {
        preserveScroll: true,
        onSuccess: () => { form.reset('paystack_public_key', 'paystack_secret_key', 'current_password', 'activate', 'confirm_live'); form.version = props.paystack.version; },
        onError: async () => { form.reset('paystack_secret_key', 'paystack_public_key', 'current_password'); await nextTick(); errorsPanel.value?.focus(); },
    });
}
</script>

<template>
    <Head title="Payment settings" />
    <div class="max-w-5xl space-y-6">
        <Link :href="route('admin.settings.index')" class="inline-flex items-center gap-2 text-sm font-medium text-surface-600 hover:text-primary-700"><ArrowLeft class="h-4 w-4" /> Platform settings</Link>
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div><h1 class="text-2xl font-display font-bold text-surface-900">Payments</h1><p class="mt-1 text-sm text-surface-600">Manage Paystack credentials and the mode used for new checkouts.</p></div>
            <span class="rounded-full px-4 py-2 text-sm font-semibold" :class="paystack.configured && paystack.mode === 'live' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900'">{{ !paystack.configured ? 'Not configured' : paystack.mode === 'live' ? 'Live payments active' : 'Test payments active' }}</span>
        </header>

        <div v-if="!paystack.configured || paystack.mode === 'test'" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">{{ paystack.configured ? 'Customers are currently using test checkout. No real money is collected.' : 'Add Paystack keys to enable checkout.' }}</div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <form @submit.prevent="save" class="min-w-0 rounded-2xl border border-surface-200 bg-white p-5 sm:p-7 shadow-sm space-y-6" autocomplete="off">
                <div class="flex items-center gap-3"><div class="rounded-xl bg-primary-50 p-3 text-primary-700"><CreditCard class="h-6 w-6" /></div><div><h2 class="text-lg font-semibold text-surface-900">Paystack configuration</h2><p class="text-sm text-surface-500">Only platform administrators can change these settings.</p></div></div>
                <div v-if="Object.keys(form.errors).length" ref="errorsPanel" tabindex="-1" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500"><p class="font-semibold">Settings were not saved</p><ul class="mt-2 list-disc pl-5"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul></div>
                <fieldset :disabled="form.processing" class="space-y-5">
                    <legend class="mb-3 text-sm font-semibold text-surface-800">Choose the keys to manage</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <label v-for="mode in ['test', 'live']" :key="mode" class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors" :class="form.mode === mode ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500' : 'border-surface-200 hover:bg-surface-50'">
                            <input v-model="form.mode" type="radio" name="payment-mode" :value="mode" class="mt-1 accent-orange-600" />
                            <span><span class="block text-sm font-semibold text-surface-900">{{ mode === 'test' ? 'Test mode' : 'Live mode' }}</span><span class="mt-1 block text-xs text-surface-600">{{ mode === 'test' ? 'Sandbox transactions' : 'Real customer payments' }}</span></span>
                        </label>
                    </div>
                    <p class="text-sm text-surface-600">{{ profile.configured ? 'Keys are already configured. Leave both fields blank to keep them, or enter both to replace them.' : 'Paste the public and secret keys from the same Paystack account.' }}</p>
                    <div><label for="paystack-public" class="block text-sm font-medium text-surface-800">Public key <span v-if="profile.public_hint" class="font-normal text-surface-500">· saved {{ profile.public_hint }}</span></label><input id="paystack-public" v-model="form.paystack_public_key" type="password" :placeholder="'pk_' + form.mode + '_…'" autocomplete="new-password" spellcheck="false" autocapitalize="off" :aria-invalid="!!form.errors.paystack_public_key" class="mt-2 block w-full rounded-xl border border-surface-300 px-3 py-2.5 font-mono text-sm focus:ring-2 focus:ring-primary-500" /></div>
                    <div><label for="paystack-secret" class="block text-sm font-medium text-surface-800">Secret key <span v-if="profile.secret_hint" class="font-normal text-surface-500">· saved {{ profile.secret_hint }}</span></label><input id="paystack-secret" v-model="form.paystack_secret_key" type="password" :placeholder="'sk_' + form.mode + '_…'" autocomplete="new-password" spellcheck="false" autocapitalize="off" :aria-invalid="!!form.errors.paystack_secret_key" class="mt-2 block w-full rounded-xl border border-surface-300 px-3 py-2.5 font-mono text-sm focus:ring-2 focus:ring-primary-500" /><p class="mt-2 text-xs text-surface-500">Saved keys are encrypted and never displayed in full. Saving checks the secret key with Paystack without creating a charge.</p></div>
                    <div class="rounded-xl border border-surface-200 bg-surface-50 p-4 space-y-3">
                        <label class="flex items-start gap-3"><input v-model="form.activate" type="checkbox" class="mt-1 accent-orange-600" /><span class="text-sm text-surface-800">Use <strong>{{ form.mode }} mode</strong> for new checkouts after saving<span class="mt-1 block text-xs text-surface-500">Leave unchecked to save credentials without changing the active mode.</span></span></label>
                        <label v-if="switchingLive" class="flex items-start gap-3 border-t border-amber-200 pt-3 text-sm text-amber-950"><input v-model="form.confirm_live" type="checkbox" required class="mt-1 accent-orange-600" /><span>I confirm that new checkouts will charge customers real money.</span></label>
                    </div>
                    <div><label for="payment-admin-password" class="block text-sm font-medium text-surface-800">Your administrator password</label><input id="payment-admin-password" v-model="form.current_password" type="password" autocomplete="current-password" required :aria-invalid="!!form.errors.current_password" class="mt-2 block w-full rounded-xl border border-surface-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500" /><p class="mt-2 text-xs text-surface-500">Required to authorize changes to payment settings.</p></div>
                </fieldset>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-surface-100 pt-5"><span class="text-xs text-surface-500">Changes are recorded in the audit trail.</span><Button type="submit" :loading="form.processing">{{ form.activate ? 'Verify & activate ' + form.mode + ' mode' : 'Verify & save keys' }}</Button></div>
            </form>
            <aside class="space-y-5">
                <section class="rounded-2xl border border-surface-200 bg-white p-5 space-y-3"><h2 class="flex items-center gap-2 text-sm font-semibold text-surface-900"><ShieldCheck class="h-5 w-5 text-primary-600" /> Before accepting payments</h2><ol class="space-y-3 list-decimal pl-4 text-sm text-surface-600"><li>Get your keys from Paystack’s <strong>API Keys & Webhooks</strong> settings.</li><li>Configure this webhook URL for both environments:</li></ol><code class="block break-all rounded-lg bg-surface-50 p-3 text-xs text-surface-800 select-all">{{ paystack.webhook_url }}</code><p class="text-sm text-surface-600">Paystack must activate your business before you can accept live payments. Subscription plan codes must also belong to the selected environment.</p><a href="https://dashboard.paystack.com/#/settings/developers" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-primary-700">Open Paystack dashboard <ExternalLink class="h-4 w-4" /></a></section>
                <section class="rounded-2xl bg-surface-100 p-5 text-sm text-surface-600 space-y-2"><h2 class="font-semibold text-surface-800">Existing payments</h2><p>Changing modes applies to new checkouts. Earlier payments and refunds continue using their original environment.</p><p>Keep both key sets configured. If you rotate a key, use the replacement from the same Paystack account.</p></section>
            </aside>
        </div>
    </div>
</template>

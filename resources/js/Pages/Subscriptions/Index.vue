<template>
    <Head title="Subscriptions" />

    <div class="space-y-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Subscription Plans</h1>
                <p class="text-sm text-surface-500">Choose a plan to unlock premium learning features.</p>
            </div>
        </div>

        <div v-if="subscription" class="rounded-2xl border border-surface-200/60 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-surface-400">Current Plan</p>
                    <p class="mt-2 text-lg font-semibold text-surface-900">{{ subscription.name }}</p>
                    <p class="mt-1 text-sm text-surface-500 capitalize">Status: {{ subscription.status }}</p>
                </div>
                <div class="text-right text-xs text-surface-400">
                    <p v-if="subscription.trial_ends_at">Trial ends {{ formatDate(subscription.trial_ends_at) }}</p>
                    <p v-else-if="subscription.ends_at">{{ subscription.cancels_at ? 'Access ends' : 'Paid through' }} {{ formatDate(subscription.ends_at) }}</p>
                </div>
            </div>
        </div>

        <div v-if="subscription?.payment_provider === 'paystack' && !subscription.cancels_at" class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-surface-50 p-4">
            <p class="text-sm text-surface-600">Your plan renews automatically. You can cancel renewal and keep access through your paid period.</p>
            <Button variant="secondary" :disabled="canceling" @click="cancelRenewal">{{ canceling ? 'Canceling…' : 'Cancel renewal' }}</Button>
        </div>
        <p v-if="billingError" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ billingError }}</p>
        <div class="grid gap-6 lg:grid-cols-3">
            <div
                v-for="plan in plans"
                :key="plan.id"
                :class="[
                    'rounded-2xl border p-6 shadow-sm transition-all',
                    isCurrent(plan.id)
                        ? 'border-primary-300 bg-primary-50/40'
                        : 'border-surface-200/60 bg-white hover:border-primary-200 hover:shadow-md',
                ]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-lg font-semibold text-surface-900">{{ plan.name }}</p>
                        <p class="mt-1 text-sm text-surface-500">{{ plan.description || 'Flexible access to premium content.' }}</p>
                    </div>
                    <Badge v-if="Number(plan.price) === 0 && plan.trial_period_days > 0" variant="info" size="sm">
                        {{ plan.trial_period_days }}-day trial
                    </Badge>
                </div>

                <div class="mt-5">
                    <p class="text-3xl font-bold text-surface-900">
                        {{ formatPrice(plan) }}
                    </p>
                    <p class="text-xs text-surface-400 mt-1">{{ billingLabel(plan) }}</p>
                </div>

                <div class="mt-5 space-y-2">
                    <div v-if="!plan.features?.length" class="text-sm text-surface-500">
                        <Sparkles class="mr-1 inline h-4 w-4 text-primary-500" />
                        Premium access to learning tools and assessments
                    </div>
                    <div v-for="feature in plan.features" :key="feature.id" class="flex items-start gap-2 text-sm text-surface-600">
                        <CheckCircle class="mt-0.5 h-4 w-4 text-success-500" />
                        <span>
                            {{ feature.name }}
                            <span v-if="feature.value" class="text-surface-400">({{ feature.value }})</span>
                        </span>
                    </div>
                </div>

                <div class="mt-6 space-y-2">
                    <Button
                        v-if="Number(plan.price) === 0"
                        :disabled="isCurrent(plan.id)"
                        variant="primary"
                        size="md"
                        class="w-full"
                        @click="subscribe(plan.id)"
                    >
                        {{ isCurrent(plan.id) ? 'Current Plan' : 'Choose Plan' }}
                    </Button>
                    <Button
                        v-if="Number(plan.price || 0) > 0"
                        :disabled="isCurrent(plan.id) || processingPlan === plan.id"
                        variant="secondary"
                        size="md"
                        class="w-full"
                        @click="payWithPaystack(plan.id)"
                    >
                        {{ processingPlan === plan.id ? 'Redirectingâ€¦' : 'Pay with Paystack' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle, Sparkles } from 'lucide-vue-next';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    plans: { type: Array, default: () => [] },
    subscription: { type: Object, default: null },
});

const processingPlan = ref(null);
const billingError = ref('');
const canceling = ref(false);
function cancelRenewal() {
    canceling.value = true;
    router.post(`/subscriptions/${props.subscription.id}/cancel`, {}, { onFinish: () => canceling.value = false });
}


function formatPrice(plan) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: plan.currency || 'NGN' }).format(Number(plan.price || 0));
}

function billingLabel(plan) {
    const interval = Number(plan.billing_interval || 1);
    const period = plan.billing_period || 'month';
    const suffix = interval > 1 ? `${interval} ${period}s` : period;
    return `Billed every ${suffix}`;
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function isCurrent(planId) {
    return props.subscription && props.subscription.plan_id === planId;
}

function subscribe(planId) {
    router.post(`/subscriptions/${planId}/subscribe`);
}

async function payWithPaystack(planId) {
    processingPlan.value = planId;
    try {
        const resp = await axios.post(`/subscriptions/${planId}/paystack/initialize`);
        const url = resp.data?.authorization_url || resp.data?.redirect;
        if (url) {
            window.location.href = url;
        }
    } catch (error) {
        billingError.value = error.response?.data?.message || 'Checkout is temporarily unavailable. Please try again.';
        processingPlan.value = null;
    }
}
</script>

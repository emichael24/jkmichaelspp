<template>
    <Head title="Verify Email" />

    <div>
        <div class="mb-8 text-center">
            <div class="w-16 h-16 rounded-2xl bg-primary-50 flex items-center justify-center mx-auto mb-4">
                <MailCheck class="w-8 h-8 text-primary-600" />
            </div>
            <h2 class="text-2xl font-display font-bold text-surface-900">Check your email</h2>
            <p class="text-surface-500 mt-2 max-w-sm mx-auto">
                We've sent a verification link to your email address. Please click the link to verify your account.
            </p>
        </div>

        <div v-if="verificationLinkSent" class="mb-6 p-4 rounded-xl bg-success-50 text-success-700 text-sm font-medium text-center">
            A new verification link has been sent to your email.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Resend verification email
            </Button>

            <Button
                variant="ghost"
                size="lg"
                class="w-full"
                @click="logout"
            >
                Sign out
            </Button>
        </form>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/UI/Button.vue';
import { MailCheck } from 'lucide-vue-next';

defineOptions({ layout: AuthLayout });

const page = usePage();
const verificationLinkSent = computed(() => page.props.flash?.status === 'verification-link-sent');

const form = useForm({});

const submit = () => {
    form.post('/email/verification-notification');
};

const logout = () => {
    router.post('/logout');
};
</script>

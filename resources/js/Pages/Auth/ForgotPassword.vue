<template>
    <Head title="Forgot Password" />

    <div>
        <div class="mb-8">
            <h2 class="text-2xl font-display font-bold text-surface-900">Reset password</h2>
            <p class="text-surface-500 mt-2">
                Enter your email address and we'll send you a link to reset your password.
            </p>
        </div>

        <div v-if="status" class="mb-6 p-4 rounded-xl bg-success-50 text-success-700 text-sm font-medium">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                placeholder="you@company.com"
                :icon="Mail"
                :error="form.errors.email"
                required
                autocomplete="email"
            />

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Send reset link
            </Button>
        </form>

        <p class="text-center text-sm text-surface-500 mt-8">
            Remember your password?
            <Link href="/login" class="font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                Sign in
            </Link>
        </p>
    </div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { Mail } from 'lucide-vue-next';

defineOptions({ layout: AuthLayout });

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

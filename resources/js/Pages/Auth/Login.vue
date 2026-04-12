<template>
    <Head title="Sign In" />

    <div>
        <div class="mb-8">
            <h2 class="text-2xl font-display font-bold text-surface-900">Welcome back</h2>
            <p class="text-surface-500 mt-2">Sign in to continue your learning journey.</p>
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

            <div>
                <Input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    label="Password"
                    placeholder="Enter your password"
                    :icon="Lock"
                    :error="form.errors.password"
                    required
                    autocomplete="current-password"
                >
                    <template #trailing>
                        <button
                            type="button"
                            class="text-surface-400 hover:text-surface-600 transition-colors"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="w-4 h-4" />
                            <Eye v-else class="w-4 h-4" />
                        </button>
                    </template>
                </Input>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="w-4 h-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500/20 transition"
                    />
                    <span class="text-sm text-surface-600">Remember me</span>
                </label>
                <Link href="/forgot-password" class="text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
                    Forgot password?
                </Link>
            </div>

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Sign in
            </Button>
        </form>

        <!-- Divider -->
        <div class="relative my-8">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-surface-200" />
            </div>
            <div class="relative flex justify-center">
                <span class="px-3 bg-white text-sm text-surface-400">or</span>
            </div>
        </div>

        <!-- Alternative sign-in -->
        <Button variant="secondary" size="lg" class="w-full" :icon="Building2">
            Sign in with organization
        </Button>

        <!-- Sign up link -->
        <p class="text-center text-sm text-surface-500 mt-8">
            Don't have an account?
            <Link href="/register" class="font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                Create account
            </Link>
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { Mail, Lock, Eye, EyeOff, Building2 } from 'lucide-vue-next';

defineOptions({ layout: AuthLayout });

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Create Account" />

    <div>
        <div class="mb-8">
            <h2 class="text-2xl font-display font-bold text-surface-900">Create your account</h2>
            <p class="text-surface-500 mt-2">Start your professional mastery journey today.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <Input
                    v-model="form.first_name"
                    label="First name"
                    placeholder="John"
                    :error="form.errors.first_name"
                    required
                    autocomplete="given-name"
                />
                <Input
                    v-model="form.last_name"
                    label="Last name"
                    placeholder="Doe"
                    :error="form.errors.last_name"
                    required
                    autocomplete="family-name"
                />
            </div>

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

            <Input
                v-model="form.phone"
                type="tel"
                label="Phone number"
                placeholder="+234 xxx xxx xxxx"
                :icon="Phone"
                :error="form.errors.phone"
                autocomplete="tel"
            />

            <Input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                label="Password"
                placeholder="Create a strong password"
                :icon="Lock"
                :error="form.errors.password"
                required
                autocomplete="new-password"
                hint="Minimum 8 characters with letters and numbers"
            >
                <template #trailing>
                    <button type="button" class="text-surface-400 hover:text-surface-600" @click="showPassword = !showPassword">
                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                        <Eye v-else class="w-4 h-4" />
                    </button>
                </template>
            </Input>

            <Input
                v-model="form.password_confirmation"
                type="password"
                label="Confirm password"
                placeholder="Re-enter your password"
                :icon="Lock"
                :error="form.errors.password_confirmation"
                required
                autocomplete="new-password"
            />

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input
                    v-model="form.terms"
                    type="checkbox"
                    class="w-4 h-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500/20 mt-0.5"
                />
                <span class="text-sm text-surface-600">
                    I agree to the
                    <a href="#" class="text-primary-600 hover:underline font-medium">Terms of Service</a>
                    and
                    <a href="#" class="text-primary-600 hover:underline font-medium">Privacy Policy</a>
                </span>
            </label>
            <p v-if="form.errors.terms" class="text-sm text-danger-500 -mt-3">{{ form.errors.terms }}</p>

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Create account
            </Button>
        </form>

        <p class="text-center text-sm text-surface-500 mt-8">
            Already have an account?
            <Link href="/login" class="font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                Sign in
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
import { Mail, Lock, Eye, EyeOff, Phone } from 'lucide-vue-next';

defineOptions({ layout: AuthLayout });

const showPassword = ref(false);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

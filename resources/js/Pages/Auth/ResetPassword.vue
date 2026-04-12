<template>
    <Head title="Reset Password" />

    <div>
        <div class="mb-8">
            <h2 class="text-2xl font-display font-bold text-surface-900">Set new password</h2>
            <p class="text-surface-500 mt-2">Enter your new password below.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <Input
                v-model="form.email"
                type="email"
                label="Email address"
                :icon="Mail"
                :error="form.errors.email"
                required
                autocomplete="email"
            />

            <Input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                label="New password"
                placeholder="Enter your new password"
                :icon="Lock"
                :error="form.errors.password"
                required
                autocomplete="new-password"
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
                label="Confirm new password"
                placeholder="Re-enter your new password"
                :icon="Lock"
                :error="form.errors.password_confirmation"
                required
                autocomplete="new-password"
            />

            <Button
                type="submit"
                variant="primary"
                size="lg"
                :loading="form.processing"
                class="w-full"
            >
                Reset password
            </Button>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { Mail, Lock, Eye, EyeOff } from 'lucide-vue-next';

defineOptions({ layout: AuthLayout });

const props = defineProps({
    token: String,
    email: String,
});

const showPassword = ref(false);

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

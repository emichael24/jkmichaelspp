<template>
    <Head :title="`Edit ${user.first_name} ${user.last_name}`" />

    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <Link href="/admin/users" class="text-sm text-surface-500 hover:text-primary-600 flex items-center gap-1 mb-4">
                <ArrowLeft class="w-4 h-4" /> Back to users
            </Link>
            <h1 class="text-2xl font-display font-bold text-surface-900">
                Edit {{ user.first_name }} {{ user.last_name }}
            </h1>
        </div>

        <Alert v-if="$page.props.flash?.success" variant="success" dismissible>
            {{ $page.props.flash.success }}
        </Alert>

        <Card>
            <form @submit.prevent="submit" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input v-model="form.first_name" label="First name" :error="form.errors.first_name" required />
                    <Input v-model="form.last_name" label="Last name" :error="form.errors.last_name" required />
                </div>

                <Input v-model="form.email" type="email" label="Email" :error="form.errors.email" required />
                <Input v-model="form.phone" type="tel" label="Phone" :error="form.errors.phone" />

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Role</label>
                    <select v-model="form.role" class="w-full rounded-xl border-surface-300 text-sm focus:border-primary-500 focus:ring-primary-500/20">
                        <option v-for="role in roles" :key="role" :value="role">{{ role.replace('_', ' ') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Organization</label>
                    <select v-model="form.organization_id" class="w-full rounded-xl border-surface-300 text-sm focus:border-primary-500 focus:ring-primary-500/20">
                        <option :value="null">None (platform-level)</option>
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </div>

                <label class="flex items-center gap-2">
                    <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-surface-300 text-primary-600" />
                    <span class="text-sm text-surface-700">Active</span>
                </label>

                <div class="flex justify-end gap-3 pt-4 border-t border-surface-100">
                    <Button variant="ghost" @click="router.visit('/admin/users')">Cancel</Button>
                    <Button type="submit" :loading="form.processing">Update user</Button>
                </div>
            </form>
        </Card>
    </div>
</template>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Alert from '@/Components/UI/Alert.vue';
import { ArrowLeft } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    user: Object,
    roles: Array,
    organizations: Array,
    departments: Array,
});

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    phone: props.user.phone || '',
    role: props.user.roles?.[0]?.name || 'learner',
    organization_id: props.user.organization_id,
    is_active: props.user.is_active,
});

const submit = () => {
    form.put(`/admin/users/${props.user.id}`);
};
</script>

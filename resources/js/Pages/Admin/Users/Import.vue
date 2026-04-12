<template>
    <Head title="Import Users" />

    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <Link href="/admin/users" class="text-sm text-surface-500 hover:text-primary-600 flex items-center gap-1 mb-4">
                <ArrowLeft class="w-4 h-4" /> Back to users
            </Link>
            <h1 class="text-2xl font-display font-bold text-surface-900">Import Users via CSV</h1>
            <p class="text-surface-500 text-sm mt-1">Upload a CSV file to create multiple users at once.</p>
        </div>

        <Card>
            <form @submit.prevent="submit" class="space-y-5">
                <!-- Template download -->
                <div class="p-4 bg-surface-50 rounded-xl">
                    <p class="text-sm text-surface-700 font-medium mb-1">CSV Format</p>
                    <p class="text-xs text-surface-500">
                        Your CSV must have these columns: <code class="bg-surface-200 px-1 rounded">first_name, last_name, email, phone, password</code>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">CSV File</label>
                    <input
                        type="file"
                        accept=".csv,.txt"
                        @change="form.csv_file = $event.target.files[0]"
                        class="w-full text-sm text-surface-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                    />
                    <p v-if="form.errors.csv_file" class="text-sm text-danger-500 mt-1">{{ form.errors.csv_file }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Assign Role</label>
                    <select v-model="form.role" class="w-full rounded-xl border-surface-300 text-sm focus:border-primary-500 focus:ring-primary-500/20">
                        <option v-for="role in roles" :key="role" :value="role">{{ role.replace('_', ' ') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Organization</label>
                    <select v-model="form.organization_id" class="w-full rounded-xl border-surface-300 text-sm focus:border-primary-500 focus:ring-primary-500/20">
                        <option :value="null">None</option>
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-surface-100">
                    <Button variant="ghost" @click="router.visit('/admin/users')">Cancel</Button>
                    <Button type="submit" :icon="Upload" :loading="form.processing">Import users</Button>
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
import { ArrowLeft, Upload } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

defineProps({
    organizations: Array,
    roles: Array,
});

const form = useForm({
    csv_file: null,
    role: 'learner',
    organization_id: null,
});

const submit = () => {
    form.post('/admin/users/import', {
        forceFormData: true,
    });
};
</script>

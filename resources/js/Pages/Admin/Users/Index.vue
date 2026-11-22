<template>
    <Head title="User Management" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Users</h1>
                <p class="text-surface-500 text-sm mt-1">Manage all platform users</p>
            </div>
            <div class="flex items-center gap-3">
                <Button variant="secondary" :icon="Upload" @click="router.visit('/admin/users/import')">
                    Import CSV
                </Button>
                <Button :icon="Plus" @click="router.visit('/admin/users/create')">
                    Add user
                </Button>
            </div>
        </div>

        <!-- Filters -->
        <Card noPadding>
            <div class="p-4 border-b border-surface-100 flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <Input
                        v-model="search"
                        placeholder="Search by name or email..."
                        :icon="SearchIcon"
                        clearable
                        @update:modelValue="applyFilters"
                    />
                </div>
                <select v-model="filterOrganization" aria-label="Filter by organization" class="rounded-xl border-surface-300 text-sm px-3 py-2 focus:border-primary-500 focus:ring-primary-500/20" @change="applyFilters">
                    <option value="">All organizations</option>
                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                </select>
                <select
                    v-model="filterRole"
                    class="rounded-xl border-surface-300 text-sm px-3 py-2 focus:border-primary-500 focus:ring-primary-500/20"
                    @change="applyFilters"
                >
                    <option value="">All roles</option>
                    <option v-for="role in roles" :key="role" :value="role">{{ role.replace('_', ' ') }}</option>
                </select>
                <select
                    v-model="filterStatus"
                    class="rounded-xl border-surface-300 text-sm px-3 py-2 focus:border-primary-500 focus:ring-primary-500/20"
                    @change="applyFilters"
                >
                    <option value="">All status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface-50 text-left text-surface-500 font-medium">
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3 hidden md:table-cell">Organization</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 hidden lg:table-cell">Last login</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="u in users.data" :key="u.id" class="hover:bg-surface-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <Avatar :name="u.first_name + ' ' + u.last_name" size="sm" />
                                    <div>
                                        <p class="font-medium text-surface-900">{{ u.first_name }} {{ u.last_name }}</p>
                                        <p class="text-xs text-surface-400">{{ u.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge v-if="u.roles.length" :variant="roleBadgeVariant(u.roles[0].name)">
                                    {{ u.roles[0].name.replace('_', ' ') }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-surface-600">
                                {{ u.organization?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="u.is_active ? 'success' : 'danger'" dot>
                                    {{ u.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell text-surface-500 text-xs">
                                {{ u.last_login_at ? new Date(u.last_login_at).toLocaleDateString() : 'Never' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        @click="router.visit(`/admin/users/${u.id}/edit`)"
                                        class="p-1.5 rounded-lg text-surface-400 hover:text-primary-600 hover:bg-primary-50 transition-colors"
                                    >
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="confirmDelete(u)"
                                        class="p-1.5 rounded-lg text-surface-400 hover:text-danger-600 hover:bg-danger-50 transition-colors"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="6" class="px-4 py-12 text-center">
                                <EmptyState
                                    icon="Users"
                                    title="No users found"
                                    description="No users match the current filters."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="p-4 border-t border-surface-100 flex items-center justify-between">
                <p class="text-sm text-surface-500">
                    Showing {{ users.from }}–{{ users.to }} of {{ users.total }} users
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm transition-colors',
                            link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                    />
                </div>
            </div>
        </Card>

        <!-- Delete modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" title="Deactivate user" size="sm">
            <p class="text-surface-600">
                Are you sure you want to deactivate <strong>{{ userToDelete?.first_name }} {{ userToDelete?.last_name }}</strong>?
                They will no longer be able to access the platform.
            </p>
            <div class="flex justify-end gap-3 mt-6">
                <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
                <Button variant="danger" :loading="deleteForm.processing" @click="deleteUser">Deactivate</Button>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Avatar from '@/Components/UI/Avatar.vue';
import Modal from '@/Components/UI/Modal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { Plus, Upload, Search as SearchIcon, Pencil, Trash2 } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: Object,
    roles: Array,
    organizations: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const filterRole = ref(props.filters?.role || '');
const filterOrganization = ref(props.filters?.organization_id || '');
const filterStatus = ref(props.filters?.is_active ?? '');

let searchTimeout;
const applyFilters = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/users', {
            search: search.value || undefined,
            role: filterRole.value || undefined,
            organization_id: filterOrganization.value || undefined,
            is_active: filterStatus.value !== '' ? filterStatus.value : undefined,
        }, { preserveState: true, replace: true });
    }, 300);
};

const roleBadgeVariant = (role) => {
    const map = { platform_admin: 'danger', org_admin: 'warning', facilitator: 'info', learner: 'primary' };
    return map[role] || 'surface';
};

const showDeleteModal = ref(false);
const userToDelete = ref(null);
const deleteForm = useForm({});

const confirmDelete = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const deleteUser = () => {
    deleteForm.delete(`/admin/users/${userToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
            userToDelete.value = null;
        },
    });
};
</script>

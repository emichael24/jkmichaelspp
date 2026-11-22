<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Layers, Plus, Users, UserRound } from 'lucide-vue-next';
import WorkspaceShell from '@/Layouts/WorkspaceShell.vue';
import Modal from '@/Components/UI/Modal.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
const props = defineProps({ organization: Object, departments: Array, managers: Array });
const editing = ref(null);
const open = ref(false);
const deleting = ref(null);
const form = useForm({ name: '', manager_id: '' });
const removal = useForm({});
const edit = (department = null) => { editing.value = department; form.clearErrors(); form.name = department?.name || ''; form.manager_id = department?.manager_id || ''; open.value = true; };
const save = () => form.submit(editing.value ? 'patch' : 'post', editing.value ? `/organization/departments/${editing.value.id}` : '/organization/departments', { preserveScroll: true, onSuccess: () => { open.value = false; } });
const remove = () => removal.delete(`/organization/departments/${deleting.value.id}`, { preserveScroll: true, onSuccess: () => { deleting.value = null; } });
</script>
<template>
    <Head title="Departments" />
    <WorkspaceShell workspace="organization">
        <div class="space-y-7">
            <header class="flex flex-wrap items-start justify-between gap-5"><div><p class="text-xs font-semibold uppercase tracking-widest text-primary-700">{{ organization.name }}</p><h1 class="mt-2 text-3xl font-display font-bold tracking-tight">A place for every team</h1><p class="mt-2 text-sm text-surface-500">Organize people into departments and identify who leads each team.</p></div><Button :icon="Plus" @click="edit()">New department</Button></header>
            <div class="flex items-start gap-3 rounded-xl border border-surface-200 bg-white px-5 py-4 text-sm text-surface-600"><Users class="mt-0.5 h-5 w-5 shrink-0 text-surface-400" /><p>Manage department memberships in <Link href="/organization/people" class="font-medium text-primary-700 underline underline-offset-4">People & access</Link>. A department manager is a point of contact; administrator permissions stay unchanged.</p></div>
            <div v-if="departments.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="department in departments" :key="department.id" class="flex flex-col rounded-2xl border border-surface-200 bg-white p-6"><div class="mb-5 flex items-center justify-between"><span class="rounded-xl bg-primary-50 p-3 text-primary-700"><Layers class="h-5 w-5" /></span><span class="text-xs text-surface-500">{{ department.users_count }} {{ department.users_count === 1 ? 'member' : 'members' }}</span></div><h2 class="break-words text-lg font-semibold">{{ department.name }}</h2><div class="mt-4 flex items-center gap-2 text-sm text-surface-500"><UserRound class="h-4 w-4" /><span>{{ department.manager?.full_name || 'No manager assigned' }}</span></div><div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-surface-100 pt-4"><Link :href="`/organization/people?department=${department.id}`" class="py-2 text-sm font-medium text-primary-700">View members</Link><div class="flex gap-1"><Button variant="ghost" size="sm" :aria-label="`Edit ${department.name}`" @click="edit(department)">Edit</Button><Button variant="ghost" size="sm" :aria-label="`Delete ${department.name}`" @click="deleting = department; removal.clearErrors()">Delete</Button></div></div></article></div>
            <div v-else class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center"><Layers class="mx-auto h-10 w-10 text-surface-300" /><h2 class="mt-4 text-lg font-semibold">Build your organization structure</h2><p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-surface-500">Start with a department such as Operations or Finance. You can add people and assign a manager as your team grows.</p><Button class="mt-6" :icon="Plus" @click="edit()">Create your first department</Button></div>
        </div>
        <Modal :open="open" :title="editing ? 'Edit department' : 'Create a department'" @close="!form.processing && (open = false)"><form @submit.prevent="save" class="space-y-5"><Input v-model="form.name" label="Department name" placeholder="e.g. Operations" :error="form.errors.name" required /><label class="block space-y-2 text-sm font-medium">Team manager <span class="font-normal text-surface-400">(optional)</span><select v-model="form.manager_id" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm"><option value="">Assign later</option><option v-for="person in managers" :key="person.id" :value="person.id">{{ person.full_name }}</option></select><span v-if="form.errors.manager_id" role="alert" class="block text-xs text-red-700">{{ form.errors.manager_id }}</span></label><div class="flex justify-end gap-2"><Button variant="secondary" :disabled="form.processing" @click="open = false">Cancel</Button><Button type="submit" :loading="form.processing">Save department</Button></div></form></Modal>
        <Modal :open="!!deleting" title="Delete department?" @close="!removal.processing && (deleting = null)"><p class="text-sm leading-relaxed text-surface-600">Remove <strong>{{ deleting?.name }}</strong> from your organization. Move its members and revoke any outstanding invitations first.</p><p v-for="error in removal.errors" :key="error" role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ error }}</p><template #footer><Button variant="secondary" :disabled="removal.processing" @click="deleting = null">Keep department</Button><Button variant="danger" :loading="removal.processing" @click="remove">Delete department</Button></template></Modal>
    </WorkspaceShell>
</template>

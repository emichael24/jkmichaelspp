<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, ShieldCheck, Users, Search, Check, AlertCircle } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ organization: Object, seats: Object, administrators: Array });
const editing = computed(() => Boolean(props.organization));
const form = useForm({
    name: props.organization?.name || '', industry: props.organization?.industry || '', size: props.organization?.size || '',
    contact_email: props.organization?.contact_email || '', contact_phone: props.organization?.contact_phone || '',
    address: props.organization?.address || '', country: props.organization?.country || 'NGA',
    max_seats: props.organization?.max_seats ?? 50, is_active: props.organization?.is_active ?? true,
    admin_mode: 'none', admin_user_id: null, admin_first_name: '', admin_last_name: '', admin_email: '', admin_password: '', admin_password_confirmation: '',
});
const search = ref('');
const candidates = ref([]);
const searching = ref(false);
const searched = ref(false);
const searchError = ref('');
const selectedUser = ref(null);
const searchUsers = async () => {
    if (search.value.trim().length < 2 || searching.value) return;
    searching.value = true; searchError.value = ''; searched.value = false;
    try { const { data } = await axios.get('/admin/organizations/administrator-candidates', { params: { search: search.value.trim(), organization_id: props.organization?.id } }); candidates.value = data.users; searched.value = true; }
    catch { searchError.value = 'Could not load users. Check your connection and try again.'; }
    finally { searching.value = false; }
};
const selectUser = user => { selectedUser.value = user; form.admin_user_id = user.id; form.clearErrors('admin_user_id'); };
const committed = computed(() => (props.seats?.used || 0) + (props.seats?.reserved || 0));
const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { form.reset('admin_password', 'admin_password_confirmation', 'admin_first_name', 'admin_last_name', 'admin_email', 'admin_user_id', 'admin_mode'); selectedUser.value = null; candidates.value = []; } };
    if (editing.value) form.put(`/admin/organizations/${props.organization.id}`, options); else form.post('/admin/organizations', options);
};
</script>

<template>
    <Head :title="editing ? `Manage ${organization.name}` : 'Create organization'" />
    <div class="mx-auto max-w-6xl space-y-6">
        <header>
            <Link href="/admin/organizations" class="mb-4 inline-flex min-h-8 items-center gap-2 rounded-lg text-sm text-surface-500 hover:text-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500"><ArrowLeft class="h-4 w-4" aria-hidden="true" /> All organizations</Link>
            <div class="flex flex-wrap items-center gap-3"><h1 class="text-2xl font-display font-bold tracking-tight text-surface-900 sm:text-3xl">{{ editing ? organization.name : 'Create organization' }}</h1><Badge v-if="editing" :variant="organization.is_active ? 'success' : 'neutral'" dot>{{ organization.is_active ? 'Active' : 'Inactive' }}</Badge></div>
            <p class="mt-2 text-sm text-surface-500">{{ editing ? 'Manage company information, workspace access and administrators.' : 'Give your client or team a dedicated learning workspace.' }}</p>
            <Link v-if="editing" :href="`/admin/organizations/${organization.id}/licenses`" class="mt-4 inline-flex rounded-xl border border-surface-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700">Manage course allocations →</Link>
        </header>
        <form @submit.prevent="submit" class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_290px]">
            <div class="min-w-0 space-y-6">
                <section class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-7" aria-labelledby="company-heading">
                    <div class="mb-6 flex items-center gap-3"><Building2 class="h-5 w-5 text-primary-600" aria-hidden="true" /><div><h2 id="company-heading" class="font-semibold text-surface-900">Company details</h2><p class="mt-0.5 text-xs text-surface-500">Use the name and contact details your team recognizes.</p></div></div>
                    <div class="space-y-5">
                        <Input v-model="form.name" label="Organization name" placeholder="e.g. Acme Learning Group" required maxlength="255" autocomplete="organization" :error="form.errors.name" />
                        <div class="grid gap-5 sm:grid-cols-2"><Input v-model="form.industry" label="Industry" placeholder="e.g. Financial services" maxlength="100" :error="form.errors.industry" /><div><label for="company-size" class="mb-1.5 block text-sm font-medium text-surface-700">Company size</label><select id="company-size" v-model="form.size" :aria-invalid="form.errors.size ? 'true' : undefined" aria-describedby="company-size-error" class="w-full rounded-xl border border-surface-200 bg-white px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"><option value="">Select company size</option><option value="1-50">1–50 employees</option><option value="51-200">51–200 employees</option><option value="201-1000">201–1,000 employees</option><option value="1000+">More than 1,000 employees</option></select><p v-if="form.errors.size" id="company-size-error" class="mt-1 text-sm text-danger-600">{{ form.errors.size }}</p></div></div>
                        <div class="grid gap-5 sm:grid-cols-2"><Input v-model="form.contact_email" label="Contact email" type="email" required autocomplete="email" :error="form.errors.contact_email" hint="Company contact; this does not create a login." /><Input v-model="form.contact_phone" label="Contact phone" type="tel" maxlength="20" autocomplete="tel" :error="form.errors.contact_phone" /></div>
                        <div><label for="organization-address" class="mb-1.5 block text-sm font-medium text-surface-700">Business address</label><textarea id="organization-address" v-model="form.address" rows="3" maxlength="2000" autocomplete="street-address" :aria-invalid="form.errors.address ? 'true' : undefined" aria-describedby="address-error" class="w-full rounded-xl border border-surface-200 px-3.5 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" /><p v-if="form.errors.address" id="address-error" class="mt-1 text-sm text-danger-600">{{ form.errors.address }}</p></div>
                        <Input v-model="form.country" label="Country code" required minlength="3" maxlength="3" pattern="[A-Za-z]{3}" hint="Three-letter country code, e.g. NGA (Nigeria), GHA (Ghana), GBR (United Kingdom)." :error="form.errors.country" class="sm:max-w-sm" />
                    </div>
                </section>

                <section class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-7" aria-labelledby="administrator-heading">
                    <div class="mb-5 flex items-center gap-3"><ShieldCheck class="h-5 w-5 text-primary-600" aria-hidden="true" /><div><h2 id="administrator-heading" class="font-semibold text-surface-900">Organization administrators</h2><p class="mt-0.5 text-xs text-surface-500">Administrators manage people, departments and learning reports for this organization.</p></div></div>
                    <div v-if="administrators.length" class="mb-6 divide-y divide-surface-100 rounded-xl border border-surface-200">
                        <div v-for="admin in administrators" :key="admin.id" class="flex flex-wrap items-center justify-between gap-3 p-4"><div class="min-w-0"><p class="text-sm font-semibold text-surface-900">{{ admin.first_name }} {{ admin.last_name }}</p><p class="break-all text-xs text-surface-500">{{ admin.email }}</p><p v-if="!admin.is_active || !admin.email_verified_at" class="mt-1 text-xs text-amber-700">{{ !admin.is_active ? 'Account inactive' : 'Email verification needed' }}</p></div><Link :href="`/admin/users/${admin.id}/edit`" class="text-sm font-medium text-primary-700 underline underline-offset-4">Manage user</Link></div>
                    </div>
                    <fieldset class="space-y-3"><legend class="mb-3 text-sm font-medium text-surface-700">{{ editing ? 'Assign another administrator' : 'Set up the first administrator' }}</legend>
                        <label v-for="option in [{ value: 'none', label: editing ? 'Keep current administrators' : 'Assign an administrator later', description: editing ? 'Save company and seat changes only.' : 'Create the workspace now and return to appoint an administrator.' }, { value: 'existing', label: 'Assign an existing user', description: 'Choose an active member or an unassigned learner.' }, { value: 'new', label: 'Create an administrator account', description: 'Add a new person with their own sign-in details.' }]" :key="option.value" :class="['flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition', form.admin_mode === option.value ? 'border-primary-300 bg-primary-50/50' : 'border-surface-200 hover:border-surface-300']"><input v-model="form.admin_mode" type="radio" name="administrator-mode" :value="option.value" class="mt-0.5 text-primary-600 focus:ring-primary-500" /><span><span class="block text-sm font-medium text-surface-900">{{ option.label }}</span><span class="mt-1 block text-xs text-surface-500">{{ option.description }}</span></span></label>
                    </fieldset>
                    <p v-if="form.errors.admin_mode" class="mt-2 text-sm text-danger-600" role="alert">{{ form.errors.admin_mode }}</p>

                    <div v-if="form.admin_mode === 'existing'" class="mt-5 space-y-3">
                        <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-end"><Input v-model="search" label="Find a user" placeholder="Enter at least 2 characters" :icon="Search" maxlength="100" @keydown.enter.prevent="searchUsers" /><Button variant="secondary" :loading="searching" :disabled="search.trim().length < 2" @click="searchUsers">Find users</Button></div>
                        <p v-if="searchError" role="alert" class="text-sm text-danger-600">{{ searchError }}</p>
                        <p v-if="searched && !candidates.length" role="status" class="rounded-xl bg-surface-50 p-4 text-sm text-surface-600">No eligible users found. Try another name or create a new administrator account.</p>
                        <div v-if="candidates.length" class="max-h-64 space-y-2 overflow-y-auto" aria-label="Eligible users"><button v-for="user in candidates" :key="user.id" type="button" :aria-pressed="form.admin_user_id === user.id" @click="selectUser(user)" :class="['flex w-full items-center justify-between gap-3 rounded-xl border p-3 text-left focus-visible:ring-2 focus-visible:ring-primary-500', form.admin_user_id === user.id ? 'border-primary-300 bg-primary-50' : 'border-surface-200 hover:bg-surface-50']"><span class="min-w-0"><span class="block text-sm font-medium">{{ user.first_name }} {{ user.last_name }}</span><span class="block break-all text-xs text-surface-500">{{ user.email }}</span></span><Check v-if="form.admin_user_id === user.id" class="h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" /></button></div>
                        <p v-if="selectedUser" role="status" class="text-sm font-medium text-primary-700">Selected: {{ selectedUser.first_name }} {{ selectedUser.last_name }}</p>
                        <p v-if="form.errors.admin_user_id" role="alert" class="text-sm text-danger-600">{{ form.errors.admin_user_id }}</p>
                        <p class="text-xs text-surface-500">Existing passwords and other roles are kept. Users from another organization are not eligible.</p>
                    </div>
                    <div v-if="form.admin_mode === 'new'" class="mt-5 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2"><Input v-model="form.admin_first_name" label="Administrator first name" required maxlength="100" :error="form.errors.admin_first_name" /><Input v-model="form.admin_last_name" label="Administrator last name" required maxlength="100" :error="form.errors.admin_last_name" /></div>
                        <Input v-model="form.admin_email" label="Administrator email" type="email" required :error="form.errors.admin_email" hint="The administrator uses this address to sign in and verify their email." />
                        <div class="grid gap-5 sm:grid-cols-2"><Input v-model="form.admin_password" label="Initial password" type="password" autocomplete="new-password" required minlength="12" :error="form.errors.admin_password" hint="At least 12 characters, including letters and numbers." /><Input v-model="form.admin_password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required :error="form.errors.admin_password_confirmation" /></div>
                        <p class="rounded-xl bg-surface-50 p-3 text-xs leading-relaxed text-surface-600">No email is sent when you save. Share sign-in details securely. The new administrator must verify their email before using the workspace.</p>
                    </div>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-24">
                <section class="rounded-2xl border border-surface-200 bg-white p-5" aria-labelledby="access-heading"><div class="mb-5 flex items-center gap-2"><Users class="h-5 w-5 text-primary-600" aria-hidden="true" /><h2 id="access-heading" class="font-semibold text-surface-900">Seats & access</h2></div>
                    <Input v-model="form.max_seats" label="Seat limit" type="number" required min="1" max="1000000" step="1" :error="form.errors.max_seats" hint="Active members and pending invitations each use one seat." />
                    <div v-if="seats" class="mt-4 space-y-2 rounded-xl bg-surface-50 p-3 text-xs"><p class="flex justify-between"><span class="text-surface-500">Active members</span><strong>{{ seats.used }}</strong></p><p class="flex justify-between"><span class="text-surface-500">Reserved invitations</span><strong>{{ seats.reserved }}</strong></p><p class="flex justify-between border-t border-surface-200 pt-2"><span class="text-surface-500">Minimum allocation</span><strong>{{ Math.max(1, committed) }}</strong></p></div>
                    <label class="mt-5 flex cursor-pointer items-center justify-between gap-3 border-t border-surface-100 pt-5"><span class="text-sm font-medium text-surface-700">Organization active</span><input v-model="form.is_active" type="checkbox" class="h-5 w-5 rounded border-surface-300 text-primary-600 focus:ring-primary-500" /></label>
                    <p v-if="form.errors.is_active" class="mt-2 text-sm text-danger-600">{{ form.errors.is_active }}</p>
                    <p v-if="!form.is_active" role="status" class="mt-3 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-900"><AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />Saving an inactive organization stops member access and revokes their sessions. Platform administrators retain access.</p>
                    <p v-else class="mt-3 text-xs leading-relaxed text-surface-500">Members can sign in once their account is active and their email is verified.</p>
                </section>
                <div class="rounded-2xl bg-surface-900 p-5 text-white"><h2 class="text-sm font-semibold">Ready for the next step</h2><p class="mt-2 text-xs leading-relaxed text-surface-300">After setup, the organization administrator can manage people, create departments and review learning progress from the Organization workspace.</p><Link v-if="editing" :href="`/admin/users?organization_id=${organization.id}`" class="mt-4 inline-flex min-h-10 items-center rounded-lg border border-white/30 px-3 text-sm font-medium text-white hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white">View organization users</Link></div>
                <div class="flex flex-col gap-2"><Button type="submit" :loading="form.processing" :disabled="form.admin_mode === 'existing' && !form.admin_user_id" class="w-full">{{ editing ? 'Save organization' : 'Create organization' }}</Button><Link href="/admin/organizations" class="rounded-xl py-2.5 text-center text-sm font-medium text-surface-500 hover:bg-surface-100 focus-visible:ring-2 focus-visible:ring-primary-500">Cancel</Link></div>
            </aside>
        </form>
    </div>
</template>

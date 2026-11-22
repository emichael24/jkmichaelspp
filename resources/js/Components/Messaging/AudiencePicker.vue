<script setup>
import { computed } from 'vue';
const props = defineProps({ modelValue: Object, options: { type: Object, default: () => ({}) } });
const emit = defineEmits(['update:modelValue']);
const value = computed(() => props.modelValue || {});
const title = text => text.replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
function set(key, input) { emit('update:modelValue', { ...value.value, [key]: input }); }
function source(input) { emit('update:modelValue', { source: input, role: '', organization_id: '', organization_members: false, contact_types: [], crm_organization_id: '' }); }
function contactType(type, checked) { const types = new Set(value.value.contact_types || []); checked ? types.add(type) : types.delete(type); set('contact_types', [...types]); }
</script>
<template>
    <fieldset class="space-y-4 rounded-xl border border-surface-200 bg-surface-50/60 p-4 sm:p-5">
        <legend class="px-1 text-sm font-semibold text-surface-900">Choose your audience</legend>
        <label class="block text-sm font-medium">Send to<select :value="value.source || 'users'" @change="source($event.target.value)" class="mt-1.5 w-full rounded-xl border-surface-300 text-sm"><option value="users">Platform users</option><option value="crm">CRM contacts</option><option value="all">All users and CRM contacts</option></select></label>
        <div v-if="value.source !== 'crm'" class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium">User type<select :value="value.role || ''" @change="set('role', $event.target.value)" class="mt-1.5 w-full rounded-xl border-surface-300 text-sm"><option value="">All user types</option><option v-for="role in options.roles || []" :key="role" :value="role">{{ role === 'org_admin' ? 'Organization administrators' : title(role) }}</option></select></label>
            <label class="block text-sm font-medium">Organization<select :value="value.organization_id || ''" @change="set('organization_id', $event.target.value)" class="mt-1.5 w-full rounded-xl border-surface-300 text-sm"><option value="">All organizations and independent users</option><option v-for="org in options.organizations || []" :key="org.id" :value="org.id">{{ org.name }}</option></select></label>
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" :checked="value.organization_members" @change="set('organization_members', $event.target.checked)" class="rounded text-primary-700" />Only users who belong to an organization</label>
        </div>
        <div v-if="value.source !== 'users'" class="space-y-4">
            <fieldset><legend class="mb-2 text-sm font-medium">CRM contact types <span class="font-normal text-surface-500">· leave empty for all contacts</span></legend><div class="flex flex-wrap gap-2"><label v-for="type in options.contactTypes || []" :key="type" class="flex cursor-pointer items-center gap-2 rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm"><input type="checkbox" :checked="value.contact_types?.includes(type)" @change="contactType(type, $event.target.checked)" class="rounded text-primary-700" />{{ title(type) }}</label></div></fieldset>
            <label class="block text-sm font-medium">CRM organization<select :value="value.crm_organization_id || ''" @change="set('crm_organization_id', $event.target.value)" class="mt-1.5 w-full rounded-xl border-surface-300 text-sm"><option value="">All CRM organizations</option><option v-for="org in options.crmOrganizations || []" :key="org.id" :value="org.id">{{ org.name }}</option></select></label>
        </div>
        <p v-if="value.source === 'all'" class="text-xs leading-5 text-surface-500">User filters apply to platform users; CRM filters apply to contacts. Both groups are combined, with duplicate recipients removed.</p>
    </fieldset>
</template>

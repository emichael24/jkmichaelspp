<template>
    <div class="space-y-6">
        <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
            Please correct the highlighted fields. Your entries have been kept.
        </div>
        <fieldset class="space-y-4">
            <legend class="mb-3 text-sm font-semibold text-surface-900">Contact information</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <Input v-for="field in contactTextFields" :key="field.key" v-model="form[field.key]" :label="field.label" :type="field.type || 'text'" :required="field.required" :autocomplete="field.autocomplete" :maxlength="field.max" :placeholder="field.placeholder" :error="form.errors[field.key]" />
            </div>
        </fieldset>
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-sm font-semibold text-surface-900">Relationship</legend>
            <div v-for="field in ['type', 'status']" :key="field">
                <label :for="`${id}-${field}`" class="mb-1.5 block text-sm font-medium text-surface-700">{{ titleCase(field) }} <span class="text-danger-600">*</span></label>
                <select :id="`${id}-${field}`" v-model="form[field]" required :aria-invalid="!!form.errors[field]" :aria-describedby="form.errors[field] ? `${id}-${field}-error` : undefined" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                    <option v-for="value in field === 'type' ? contactTypes : contactStatuses" :key="value" :value="value">{{ titleCase(value) }}</option>
                </select>
                <p v-if="form.errors[field]" :id="`${id}-${field}-error`" class="mt-1 text-sm text-danger-600">{{ form.errors[field] }}</p>
            </div>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-surface-200 bg-surface-50 p-4">
            <legend class="px-1 text-sm font-semibold">Campaign subscriptions</legend>
            <p class="text-xs text-surface-600">Enable a channel only when this contact has agreed to receive campaigns. Individual correspondence remains available.</p>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.marketing_email" type="checkbox" class="rounded text-primary-700" />Email campaigns</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.marketing_whatsapp" type="checkbox" class="rounded text-primary-700" />WhatsApp campaigns</label>
        </fieldset>
        <fieldset>
            <legend class="mb-3 text-sm font-semibold text-surface-900">Address <span class="font-normal text-surface-500">· optional</span></legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <Input v-for="field in addressFields" :key="field.key" v-model="form[field.key]" :label="field.label" :autocomplete="field.autocomplete" :maxlength="field.max" :error="form.errors[field.key]" />
            </div>
        </fieldset>
        <div>
            <label :for="`${id}-notes`" class="mb-1.5 block text-sm font-medium text-surface-700">Notes</label>
            <textarea :id="`${id}-notes`" v-model="form.notes" rows="3" maxlength="5000" :aria-invalid="!!form.errors.notes" :aria-describedby="form.errors.notes ? `${id}-notes-error` : undefined" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500" />
            <p v-if="form.errors.notes" :id="`${id}-notes-error`" class="mt-1 text-sm text-danger-600">{{ form.errors.notes }}</p>
        </div>
    </div>
</template>
<script setup>
import { useId } from 'vue';
import Input from '@/Components/UI/Input.vue';
import { contactTextFields, addressFields, contactTypes, contactStatuses, titleCase } from './contactFields';
defineProps({ form: { type: Object, required: true } });
const id = useId();
</script>

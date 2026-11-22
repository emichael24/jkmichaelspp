<template>
    <Head title="Notification Preferences" />

    <div class="max-w-2xl mx-auto space-y-6">
        <a href="/notifications" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to notifications
        </a>

        <h1 class="text-2xl font-display font-bold text-surface-900">Notification Preferences</h1>

        <form @submit.prevent="save" class="space-y-6"><div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-danger-50 p-4 text-sm text-danger-800"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p></div>
            <!-- Channels -->
            <Card title="Delivery Channels">
                <div class="space-y-4">
                    <label class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-sm text-surface-900">Push Notifications</p>
                            <p class="text-xs text-surface-500">Receive push notifications on your device</p>
                        </div>
                        <input v-model="form.push_enabled" type="checkbox"
                               class="rounded border-surface-300 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                    </label>
                    <label class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-sm text-surface-900">Email Notifications</p>
                            <p class="text-xs text-surface-500">Receive important updates via email</p>
                        </div>
                        <input v-model="form.email_enabled" type="checkbox"
                               class="rounded border-surface-300 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                    </label>
                </div>
            </Card>

            <Card title="This device"><p class="text-sm text-surface-600">{{ pushAvailable ? 'Connect this browser to receive private notification alerts.' : 'Browser push is not available yet. You can still receive in-app and email updates.' }}</p><div class="mt-4 flex flex-wrap gap-3"><button v-if="pushAvailable" type="button" :disabled="deviceBusy" @click="connectDevice" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white">Enable this device</button><button type="button" :disabled="deviceBusy" @click="disconnectDevice" class="rounded-xl border border-surface-300 px-4 py-2.5 text-sm">Disconnect this device</button></div><p v-if="deviceMessage" role="status" class="mt-3 text-sm text-surface-600">{{ deviceMessage }}</p></Card>
            <Card title="News and offers" subtitle="Optional messages from the JK Michaels team. Off by default."><div class="space-y-4"><label class="flex items-center justify-between gap-4 text-sm"><span>Email campaigns</span><input v-model="form.marketing_email" type="checkbox" class="h-5 w-5 rounded border-surface-300 text-primary-600" /></label><label class="flex items-center justify-between gap-4 text-sm"><span>WhatsApp campaigns</span><input v-model="form.marketing_whatsapp" type="checkbox" class="h-5 w-5 rounded border-surface-300 text-primary-600" /></label></div></Card>
            <!-- Categories -->
            <Card title="Categories">
                <div class="space-y-4">
                    <label class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-sm text-surface-900">Learning Reminders</p>
                            <p class="text-xs text-surface-500">Study reminders, quiz alerts, streak rescue</p>
                        </div>
                        <input v-model="form.learning_reminders" type="checkbox"
                               class="rounded border-surface-300 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                    </label>
                    <label class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-sm text-surface-900">Internship Reminders</p>
                            <p class="text-xs text-surface-500">Task deadlines, submission alerts, meeting reminders</p>
                        </div>
                        <input v-model="form.internship_reminders" type="checkbox"
                               class="rounded border-surface-300 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                    </label>
                    <label class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-sm text-surface-900">Announcements</p>
                            <p class="text-xs text-surface-500">Cohort announcements, system notices</p>
                        </div>
                        <input v-model="form.announcements" type="checkbox"
                               class="rounded border-surface-300 text-primary-600 focus:ring-primary-500 w-5 h-5" />
                    </label>
                </div>
            </Card>

            <!-- Frequency -->
            <Card title="Delivery Frequency">
                <div class="space-y-2">
                    <label v-for="opt in frequencyOptions" :key="opt.value"
                           class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                           :class="form.frequency === opt.value ? 'border-primary-500 bg-primary-50' : 'border-surface-200 hover:border-surface-300'">
                        <input type="radio" v-model="form.frequency" :value="opt.value"
                               class="text-primary-600 focus:ring-primary-500" />
                        <div>
                            <p class="text-sm font-medium text-surface-900">{{ opt.label }}</p>
                            <p class="text-xs text-surface-500">{{ opt.desc }}</p>
                        </div>
                    </label>
                </div>
            </Card>

            <!-- Quiet Hours -->
            <Card title="Quiet Hours" :subtitle="`Email and push delivery is delayed during these hours (${timezone}).`">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Start Time</label>
                        <input v-model="form.quiet_hours_start" type="time"
                               class="w-full rounded-lg border-surface-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">End Time</label>
                        <input v-model="form.quiet_hours_end" type="time"
                               class="w-full rounded-lg border-surface-300 text-sm" />
                    </div>
                </div>
                <p class="text-xs text-surface-400 mt-2">e.g., 22:00 to 07:00 to silence overnight notifications</p>
            </Card>

            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 transition-colors disabled:opacity-50">
                    {{ form.processing ? 'Saving...' : 'Save Preferences' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { subscribeToPush, unsubscribeFromPush } from '@/pwa';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    preferences: { type: Object, required: true }, pushAvailable: Boolean, timezone: String,
});

const frequencyOptions = [
    { value: 'realtime', label: 'Real-time', desc: 'Receive notifications immediately' },
    { value: 'daily_digest', label: 'Daily Digest', desc: 'Bundle notifications into a daily summary' },
    { value: 'weekly_digest', label: 'Weekly Digest', desc: 'Bundle notifications into a weekly summary' },
];

const form = useForm({
    marketing_email: props.preferences.marketing_email ?? false,
    marketing_whatsapp: props.preferences.marketing_whatsapp ?? false,
    push_enabled: props.preferences.push_enabled ?? true,
    email_enabled: props.preferences.email_enabled ?? true,
    learning_reminders: props.preferences.learning_reminders ?? true,
    internship_reminders: props.preferences.internship_reminders ?? true,
    announcements: props.preferences.announcements ?? true,
    frequency: props.preferences.frequency ?? 'realtime',
    quiet_hours_start: props.preferences.quiet_hours_start?.slice(0, 5) ?? null,
    quiet_hours_end: props.preferences.quiet_hours_end?.slice(0, 5) ?? null,
});

const deviceBusy = ref(false); const deviceMessage = ref('');
async function connectDevice() {
    deviceBusy.value = true;
    try { await subscribeToPush(); deviceMessage.value = 'This device is connected.'; }
    catch (error) { deviceMessage.value = error.response?.data?.message || error.message; }
    finally { deviceBusy.value = false; }
}
async function disconnectDevice() {
    deviceBusy.value = true;
    try { await unsubscribeFromPush(); deviceMessage.value = 'This device is disconnected.'; }
    catch { deviceMessage.value = 'Could not disconnect. Check your connection and try again.'; }
    finally { deviceBusy.value = false; }
}
function save() {
    form.put('/notifications/preferences');
}
</script>

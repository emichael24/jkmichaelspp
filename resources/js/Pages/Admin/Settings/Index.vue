<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import { Settings, Save, MessageCircle, ChevronRight, Wifi, Users } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });
const props = defineProps({
    settings: Object,
    waSessions: { type: Array, default: () => [] },
    waPairedCount: { type: Number, default: 0 },
});

const form = useForm({
    platform_name: props.settings?.platform_name || 'JK Michaels',
    support_email: props.settings?.support_email || '',
    default_currency: props.settings?.default_currency || 'NGN',
    max_login_attempts: props.settings?.max_login_attempts || 5,
    session_timeout_minutes: props.settings?.session_timeout_minutes || 30,
    enable_registrations: props.settings?.enable_registrations ?? true,
    maintenance_mode: props.settings?.maintenance_mode ?? false,
});

function save() {
    form.put(route('admin.settings.update'));
}

function sessionStatusClass(status) {
    return {
        CONNECTED: 'bg-success-50 text-success-700',
        WAITING_FOR_QR: 'bg-amber-50 text-amber-700',
        DISCONNECTED: 'bg-surface-100 text-surface-500',
    }[status] || 'bg-surface-100 text-surface-500';
}
</script>

<template>
    <Head title="Settings" />
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Platform Settings</h1>
                <p class="text-surface-500 mt-1 text-sm">Configure platform behavior and preferences</p>
            </div>
            <Button @click="save" variant="primary" :disabled="form.processing" class="gap-2"><Save class="w-4 h-4" />Save Changes</Button>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <Link :href="route('admin.settings.payments')" class="lg:col-span-2 flex items-center justify-between gap-4 rounded-2xl border border-primary-200 bg-primary-50 p-5 hover:bg-primary-100 focus-visible:ring-2 focus-visible:ring-primary-500">
                <div><h2 class="text-lg font-semibold text-surface-900">Payments</h2><p class="mt-1 text-sm text-surface-600">Manage Paystack keys and switch between test and live payments.</p></div>
                <ChevronRight class="h-5 w-5 shrink-0 text-primary-700" />
            </Link>
            <Card title="General" class="p-5">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Platform Name</label>
                        <input v-model="form.platform_name" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Support Email</label>
                        <input v-model="form.support_email" type="email" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Default Currency</label>
                        <select v-model="form.default_currency" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="NGN">Nigerian Naira (₦)</option>
                            <option value="USD">US Dollar ($)</option>
                            <option value="GBP">British Pound (£)</option>
                        </select>
                    </div>
                </div>
            </Card>

            <Card title="Security" class="p-5">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Max Login Attempts</label>
                        <input v-model="form.max_login_attempts" type="number" min="1" max="20" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Session Timeout (minutes)</label>
                        <input v-model="form.session_timeout_minutes" type="number" min="5" max="480" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div class="flex items-center gap-3">
                        <input v-model="form.enable_registrations" type="checkbox" id="enable_reg" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        <label for="enable_reg" class="text-sm text-surface-700">Enable New Registrations</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <input v-model="form.maintenance_mode" type="checkbox" id="maint" class="rounded border-surface-300 text-warning-600 focus:ring-warning-500" />
                        <label for="maint" class="text-sm text-surface-700">Maintenance Mode</label>
                    </div>
                </div>
            </Card>

            <!-- Messaging & WhatsApp Settings -->
            <Card class="p-5 lg:col-span-2">
                <template #default>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <MessageCircle class="w-4 h-4 text-green-600" />
                            </div>
                            <h2 class="text-base font-semibold text-surface-900">Messaging &amp; WhatsApp</h2>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 mb-5">
                        <!-- WA Sessions Summary -->
                        <div class="bg-surface-50 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <Wifi class="w-4 h-4 text-surface-500" />
                                <span class="text-sm font-medium text-surface-700">Connected Sessions</span>
                            </div>
                            <div v-if="waSessions.length" class="space-y-2">
                                <div v-for="s in waSessions" :key="s.session_id" class="flex items-center justify-between text-sm">
                                    <span class="text-surface-700 truncate max-w-[120px]">{{ s.name }}</span>
                                    <span :class="[sessionStatusClass(s.status), 'px-2 py-0.5 rounded-full text-xs font-medium']">{{ s.status }}</span>
                                </div>
                            </div>
                            <p v-else class="text-xs text-surface-400">No sessions yet</p>
                            <Link href="/admin/erp/whatsapp" class="mt-3 text-xs text-primary-600 hover:underline flex items-center gap-1">
                                Manage Sessions <ChevronRight class="w-3 h-3" />
                            </Link>
                        </div>

                        <!-- Paired Users Summary -->
                        <div class="bg-surface-50 rounded-xl p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <Users class="w-4 h-4 text-surface-500" />
                                <span class="text-sm font-medium text-surface-700">User WA Numbers</span>
                            </div>
                            <p class="text-2xl font-bold text-surface-900">{{ waPairedCount }}</p>
                            <p class="text-xs text-surface-400 mt-0.5">users with WA number paired</p>
                            <Link href="/admin/settings/whatsapp-pairings" class="mt-3 text-xs text-primary-600 hover:underline flex items-center gap-1">
                                Manage Pairings <ChevronRight class="w-3 h-3" />
                            </Link>
                        </div>

                        <!-- Quick Links -->
                        <div class="bg-surface-50 rounded-xl p-4">
                            <p class="text-sm font-medium text-surface-700 mb-3">Quick Actions</p>
                            <div class="space-y-2">
                                <Link href="/admin/settings/whatsapp-pairings" class="flex items-center justify-between text-sm text-surface-600 hover:text-primary-600 group">
                                    <span>Pair user numbers</span>
                                    <ChevronRight class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </Link>
                                <Link href="/admin/erp/whatsapp" class="flex items-center justify-between text-sm text-surface-600 hover:text-primary-600 group">
                                    <span>Add WA session</span>
                                    <ChevronRight class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </Link>
                                <Link href="/admin/erp/messaging" class="flex items-center justify-between text-sm text-surface-600 hover:text-primary-600 group">
                                    <span>Campaigns &amp; broadcasts</span>
                                    <ChevronRight class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>


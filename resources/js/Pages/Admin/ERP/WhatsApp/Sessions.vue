<template>
    <Head title="WhatsApp Sessions" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link href="/admin/erp/messaging">
                    <button class="p-2 rounded-xl hover:bg-surface-100">
                        <ArrowLeft class="w-4 h-4 text-surface-500" />
                    </button>
                </Link>
                <div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">WhatsApp Sessions</h1>
                    <p class="text-surface-500 mt-0.5 text-sm">Manage connected WhatsApp numbers for messaging.</p>
                </div>
            </div>
            <button @click="showNewSessionModal = true" class="px-4 py-2 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 flex items-center gap-2 self-start sm:self-auto">
                <Plus class="w-4 h-4" /> Connect Number
            </button>
        </div>

        <!-- Gateway Status -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div :class="['w-3 h-3 rounded-full', gatewayOnline ? 'bg-success-500 animate-pulse' : 'bg-danger-500']"></div>
                    <div>
                        <p class="font-semibold text-surface-900">Gateway Status</p>
                        <p class="text-sm text-surface-500">FlexiChurch WhatsApp API</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span :class="['px-3 py-1 rounded-full text-xs font-semibold', gatewayOnline ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700']">
                        {{ gatewayOnline ? 'Online' : 'Offline' }}
                    </span>
                    <button @click="refreshSessions" class="p-2 rounded-xl hover:bg-surface-100 text-surface-400 hover:text-surface-700">
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
                    </button>
                </div>
            </div>
            <div v-if="props.gatewayStatus?.uptime" class="mt-2 text-xs text-surface-400">
                Uptime: {{ props.gatewayStatus.uptime }}
            </div>
        </div>

        <!-- Sessions List -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h2 class="font-semibold text-surface-900">Connected Sessions</h2>
            </div>
            <div class="overflow-x-auto">
                <table v-if="props.sessions?.length" class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Session Name</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Phone Number</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="session in props.sessions" :key="session.id || session.name" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center">
                                        <MessageCircle class="w-4 h-4 text-green-600" />
                                    </div>
                                    <div>
                                        <p class="font-semibold text-surface-900">{{ session.name }}</p>
                                        <p class="text-xs text-surface-400">{{ session.id || session.name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-surface-600">
                                {{ session.phone || session.wid?.user || '—' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="sessionStatusClass(session.status)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium">
                                    <span :class="sessionDotClass(session.status)" class="w-1.5 h-1.5 rounded-full"></span>
                                    {{ session.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        v-if="session.status === 'WAITING_FOR_QR'"
                                        @click="showQR(session)"
                                        class="px-3 py-1.5 text-xs font-medium bg-amber-50 text-amber-700 rounded-lg hover:bg-amber-100 flex items-center gap-1"
                                    >
                                        <QrCode class="w-3.5 h-3.5" /> Show QR
                                    </button>
                                    <button
                                        v-if="session.status === 'CONNECTED'"
                                        @click="logoutSession(session)"
                                        class="px-3 py-1.5 text-xs font-medium bg-danger-50 text-danger-700 rounded-lg hover:bg-danger-100 flex items-center gap-1"
                                    >
                                        <LogOut class="w-3.5 h-3.5" /> Disconnect
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!props.sessions?.length" class="p-16 text-center">
                <MessageCircle class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No WhatsApp sessions</p>
                <p class="text-sm text-surface-400 mt-1">Connect a number to start sending WhatsApp messages.</p>
            </div>
        </div>
    </div>

    <!-- Connect New Number Modal -->
    <Modal :show="showNewSessionModal" @close="cancelNewSession" maxWidth="sm">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">Connect WhatsApp Number</h3>

            <div v-if="!pendingQR">
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Session Name</label>
                    <Input v-model="newSessionName" placeholder="e.g. jkmichaels-main" :error="newSessionError" />
                    <p class="text-xs text-surface-400 mt-1">Use a short, unique identifier. No spaces.</p>
                </div>
                <div class="flex justify-end gap-3 pt-3">
                    <Button variant="ghost" @click="cancelNewSession">Cancel</Button>
                    <Button variant="primary" @click="createSession" :disabled="creatingSession || !newSessionName.trim()">
                        <span v-if="creatingSession" class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Creating…</span>
                        <span v-else>Create &amp; Get QR</span>
                    </Button>
                </div>
            </div>

            <!-- QR Code Display -->
            <div v-else class="space-y-4">
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-800">
                    Open WhatsApp on your phone → ⋮ → Linked Devices → Link a Device, then scan this QR code.
                </div>
                <div class="flex justify-center">
                    <div v-if="loadingQR" class="w-48 h-48 bg-surface-100 rounded-xl flex items-center justify-center animate-pulse">
                        <QrCode class="w-8 h-8 text-surface-300" />
                    </div>
                    <img v-else-if="qrBase64" :src="'data:image/png;base64,' + qrBase64" alt="WhatsApp QR Code" class="w-48 h-48 rounded-xl border-4 border-white shadow-lg" />
                    <div v-else class="w-48 h-48 bg-danger-50 rounded-xl flex flex-col items-center justify-center gap-2">
                        <XCircle class="w-8 h-8 text-danger-400" />
                        <p class="text-xs text-danger-600">QR unavailable</p>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm">
                        <span :class="['w-2 h-2 rounded-full', sessionConnected ? 'bg-success-500' : 'bg-amber-400 animate-pulse']"></span>
                        <span :class="sessionConnected ? 'text-success-700' : 'text-amber-700'">
                            {{ sessionConnected ? 'Connected!' : 'Waiting for scan…' }}
                        </span>
                    </div>
                    <button @click="refreshQR" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                        <RefreshCw class="w-3 h-3" /> Refresh QR
                    </button>
                </div>

                <div class="flex justify-end gap-3 pt-1 border-t border-surface-100">
                    <Button variant="ghost" @click="cancelNewSession">Close</Button>
                    <Button v-if="sessionConnected" variant="primary" @click="cancelNewSession">Done</Button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Modal from '@/Components/UI/Modal.vue';
import { Plus, ArrowLeft, RefreshCw, MessageCircle, QrCode, LogOut, XCircle } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    gatewayStatus: { type: Object, default: () => ({}) },
});

const refreshing = ref(false);
const showNewSessionModal = ref(false);
const newSessionName = ref('');
const newSessionError = ref('');
const creatingSession = ref(false);
const pendingQR = ref(null);
const qrBase64 = ref('');
const loadingQR = ref(false);
const sessionConnected = ref(false);
let pollInterval = null;

const gatewayOnline = computed(() => props.gatewayStatus?.status === 'ok' || props.gatewayStatus?.uptime);

function refreshSessions() {
    refreshing.value = true;
    router.reload({ only: ['sessions', 'gatewayStatus'], onFinish: () => { refreshing.value = false; } });
}

function sessionStatusClass(status) {
    return {
        CONNECTED: 'bg-success-50 text-success-700',
        WAITING_FOR_QR: 'bg-amber-50 text-amber-700',
        DISCONNECTED: 'bg-surface-100 text-surface-500',
    }[status] || 'bg-surface-100 text-surface-500';
}

function sessionDotClass(status) {
    return {
        CONNECTED: 'bg-success-500',
        WAITING_FOR_QR: 'bg-amber-400 animate-pulse',
        DISCONNECTED: 'bg-surface-400',
    }[status] || 'bg-surface-400';
}

async function createSession() {
    newSessionError.value = '';
    if (!newSessionName.value.trim()) { newSessionError.value = 'Session name is required.'; return; }
    creatingSession.value = true;
    try {
        const res = await axios.post('/admin/erp/whatsapp/sessions', { session_name: newSessionName.value.trim() });
        pendingQR.value = res.data.session_name || newSessionName.value.trim();
        await fetchQR(pendingQR.value);
        startPolling(pendingQR.value);
    } catch (e) {
        newSessionError.value = e.response?.data?.message || 'Failed to create session.';
    } finally {
        creatingSession.value = false;
    }
}

async function fetchQR(sessionName) {
    loadingQR.value = true;
    qrBase64.value = '';
    try {
        const res = await axios.get(`/admin/erp/whatsapp/sessions/${sessionName}/qr`);
        qrBase64.value = res.data.qr_code || '';
    } catch { /* QR might not be ready yet, ignore */ }
    finally { loadingQR.value = false; }
}

async function refreshQR() {
    if (!pendingQR.value) return;
    await fetchQR(pendingQR.value);
}

async function showQR(session) {
    showNewSessionModal.value = true;
    pendingQR.value = session.name || session.id;
    await fetchQR(pendingQR.value);
    startPolling(pendingQR.value);
}

function startPolling(sessionName) {
    stopPolling();
    pollInterval = setInterval(async () => {
        try {
            const res = await axios.get(`/admin/erp/whatsapp/sessions/${sessionName}/status`);
            const status = res.data?.status;
            if (status === 'CONNECTED') {
                sessionConnected.value = true;
                stopPolling();
                router.reload({ only: ['sessions'] });
            } else if (status === 'WAITING_FOR_QR' && !qrBase64.value) {
                await fetchQR(sessionName);
            }
        } catch { /* silent */ }
    }, 3000);
}

function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

function cancelNewSession() {
    stopPolling();
    showNewSessionModal.value = false;
    pendingQR.value = null;
    qrBase64.value = '';
    sessionConnected.value = false;
    newSessionName.value = '';
    newSessionError.value = '';
}

async function logoutSession(session) {
    const name = session.name || session.id;
    if (!confirm(`Disconnect "${name}" from WhatsApp? This will stop all messaging through this number.`)) return;
    try {
        await axios.post(`/admin/erp/whatsapp/sessions/${name}/logout`);
        router.reload({ only: ['sessions'] });
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to logout session.');
    }
}

onUnmounted(() => stopPolling());
</script>

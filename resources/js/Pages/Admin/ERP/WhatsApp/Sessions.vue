<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Modal from '@/Components/UI/Modal.vue';
import { Plus, RefreshCw, MessageCircle, QrCode, LogOut } from 'lucide-vue-next';
import { integrationError, whatsappQrSource } from '@/lib/integration';

defineOptions({ layout: AdminLayout });
const props = defineProps({ sessions: { type: Array, default: () => [] }, gatewayStatus: Object, gatewayError: String });
const online = computed(() => props.gatewayStatus?.status === 'ok');
const modal = ref(false), name = ref(''), pendingId = ref(''), qr = ref(''), qrExpiresAt = ref(null);
const busy = ref(false), connected = ref(false), error = ref(''), pageError = ref(''), refreshing = ref(false);
const disconnecting = ref('');
let timer, generation = 0, pollUntil = 0;
const sessionUrl = id => `/admin/erp/whatsapp/sessions/${encodeURIComponent(id)}`;

async function refresh() {
    if (refreshing.value) return;
    refreshing.value = true;
    pageError.value = '';
    for (let i = 0; i < props.sessions.length; i += 3) {
        const results = await Promise.allSettled(props.sessions.slice(i, i + 3).map(session => axios.get(`${sessionUrl(session.session_id)}/status`)));
        const failed = results.find(result => result.status === 'rejected');
        if (failed) { pageError.value = integrationError(failed.reason); break; }
    }
    router.reload({ only: ['sessions', 'gatewayStatus', 'gatewayError'], onFinish: () => refreshing.value = false });
}
function stop() { clearTimeout(timer); generation++; }
function close() {
    stop(); modal.value = false; pendingId.value = ''; qr.value = ''; qrExpiresAt.value = null;
    connected.value = false; name.value = ''; error.value = ''; busy.value = false;
    router.reload({ only: ['sessions'] });
}
function openNew() { stop(); name.value = ''; pendingId.value = ''; qr.value = ''; connected.value = false; error.value = ''; modal.value = true; }
function validRun(run) { return modal.value && run === generation; }
function setQr(data) {
    qr.value = whatsappQrSource(data.qrCode);
    const expiry = Date.parse(data.qrExpiresAt);
    qrExpiresAt.value = Number.isFinite(expiry) ? expiry : Date.now() + 45000;
}
async function fetchQr(id, run) {
    const { data } = await axios.get(`${sessionUrl(id)}/qr`);
    if (validRun(run)) setQr(data);
}
async function poll(id, run) {
    if (!validRun(run)) return;
    if (Date.now() > pollUntil) { qr.value = ''; error.value = 'Pairing timed out. Select Refresh QR to try again.'; return; }
    try {
        const { data } = await axios.get(`${sessionUrl(id)}/status`);
        if (!validRun(run)) return;
        if (data.status === 'CONNECTED') {
            connected.value = true; qr.value = ''; stop(); router.reload({ only: ['sessions'] }); return;
        }
        if (['DISCONNECTED', 'ERROR'].includes(data.status)) {
            qr.value = ''; error.value = 'The session disconnected. Close this dialog and connect a new session.'; return;
        }
        if (!qr.value || Date.now() >= qrExpiresAt.value) await fetchQr(id, run);
        if (validRun(run)) timer = setTimeout(() => poll(id, run), 4000);
    } catch (e) { if (validRun(run)) { qr.value = ''; error.value = integrationError(e); } }
}
async function refreshQr() {
    if (!pendingId.value || busy.value) return;
    stop(); const run = generation; busy.value = true; error.value = ''; qr.value = ''; pollUntil = Date.now() + 180000;
    try {
        await fetchQr(pendingId.value, run);
        if (validRun(run)) timer = setTimeout(() => poll(pendingId.value, run), 2000);
    } catch (e) { if (validRun(run)) error.value = integrationError(e); }
    finally { if (validRun(run)) busy.value = false; }
}
async function create() {
    if (busy.value || !name.value.trim()) return;
    stop(); const run = generation; busy.value = true; error.value = '';
    try {
        const { data } = await axios.post('/admin/erp/whatsapp/sessions', { session_name: name.value.trim() });
        if (!validRun(run)) return;
        pendingId.value = data.session.id;
        connected.value = data.session.status === 'CONNECTED';
        setQr(data.session);
        pollUntil = Date.now() + 180000;
        if (!connected.value) timer = setTimeout(() => poll(pendingId.value, run), 1000);
        router.reload({ only: ['sessions'] });
    } catch (e) { if (validRun(run)) error.value = integrationError(e); }
    finally { if (validRun(run)) busy.value = false; }
}
async function showQr(session) {
    stop(); pendingId.value = session.session_id; name.value = session.name; connected.value = false; modal.value = true;
    await refreshQr();
}
async function disconnect(session) {
    if (disconnecting.value || !confirm(`Disconnect ${session.name}?`)) return;
    disconnecting.value = session.session_id; pageError.value = '';
    try { await axios.post(`${sessionUrl(session.session_id)}/logout`); refresh(); }
    catch (e) { pageError.value = integrationError(e); }
    finally { disconnecting.value = ''; }
}
const statusLabel = status => ({ CONNECTED: 'Connected', WAITING_FOR_QR: 'Ready to pair', CONNECTING: 'Connecting', DISCONNECTED: 'Disconnected', ERROR: 'Needs attention' }[status] || status);
onUnmounted(stop);
</script>

<template>
    <Head title="WhatsApp connections" />
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <Link href="/admin/erp/messaging" class="text-sm font-medium text-primary-700">Back to messaging</Link>
                <h1 class="mt-2 text-2xl font-display font-bold text-surface-900">WhatsApp connections</h1>
                <p class="text-sm text-surface-600 mt-1">Link a phone securely and check its connection status.</p>
            </div>
            <Button @click="openNew"><Plus class="w-4 h-4 mr-2" />Connect number</Button>
        </div>
        <div v-if="pageError || gatewayError" role="alert" class="rounded-xl border border-red-200 bg-red-50 text-red-800 p-4 text-sm">{{ pageError || gatewayError }}</div>
        <div class="bg-white rounded-2xl border border-surface-200 p-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full" :class="online ? 'bg-green-500' : 'bg-amber-500'"></span>
                <div><p class="font-semibold text-surface-900">Messaging gateway</p><p class="text-sm text-surface-500">{{ online ? 'Service reachable' : 'Connection needs attention' }}</p></div>
            </div>
            <Button variant="secondary" :disabled="refreshing" @click="refresh"><RefreshCw class="w-4 h-4 mr-2" :class="{ 'animate-spin': refreshing }" />Refresh</Button>
        </div>
        <p class="text-sm text-surface-600">These are JK Michaels phone connections. Outbound messages currently use the sender configured by the messaging provider; connecting a phone does not change that sender.</p>
        <div class="bg-white rounded-2xl border border-surface-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 font-semibold">Your connections</div>
            <div v-if="!sessions.length" class="p-10 text-center">
                <MessageCircle class="w-10 h-10 mx-auto text-surface-400 mb-3" />
                <h2 class="font-semibold text-surface-900">Connect your first phone</h2>
                <p class="text-sm text-surface-500 mt-1">Keep WhatsApp open on your phone, then select Connect number.</p>
            </div>
            <div v-for="session in sessions" :key="session.session_id" class="p-5 border-b last:border-b-0 border-surface-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="min-w-0"><p class="font-semibold text-surface-900">{{ session.name }}</p><p class="text-sm text-surface-500">{{ session.phone || 'No phone linked yet' }}</p></div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-semibold rounded-full px-3 py-1" :class="session.status === 'CONNECTED' ? 'bg-green-50 text-green-800' : 'bg-surface-100 text-surface-700'">{{ statusLabel(session.status) }}</span>
                    <Button v-if="['WAITING_FOR_QR', 'CONNECTING'].includes(session.status)" variant="secondary" @click="showQr(session)"><QrCode class="w-4 h-4 mr-2" />Show QR</Button>
                    <Button v-if="session.status === 'CONNECTED'" variant="ghost" :disabled="!!disconnecting" @click="disconnect(session)"><LogOut class="w-4 h-4 mr-2" />Disconnect</Button>
                </div>
            </div>
        </div>
    </div>
    <Modal :show="modal" @close="close" maxWidth="sm" :title="pendingId ? name : 'Connect WhatsApp'">
        <div class="space-y-5">
            <p v-if="error" role="alert" class="rounded-xl bg-red-50 text-red-800 p-3 text-sm">{{ error }}</p>
            <template v-if="!pendingId">
                <label class="block text-sm font-medium" for="wa-name">Connection name</label>
                <Input id="wa-name" v-model="name" maxlength="100" placeholder="e.g. JK Michaels support" @keydown.enter="create" />
                <p class="text-sm text-surface-500">Choose a name your team will recognize.</p>
                <Button class="w-full" :disabled="busy || !name.trim()" @click="create">{{ busy ? 'Creating connection…' : 'Continue to QR code' }}</Button>
            </template>
            <template v-else>
                <div v-if="connected" role="status" class="p-5 rounded-xl bg-green-50 text-green-800 text-center font-medium">Your phone is connected.</div>
                <template v-else>
                    <ol class="list-decimal pl-5 text-sm text-surface-600 space-y-2"><li>Open WhatsApp on your phone.</li><li>Open Settings or the menu, then Linked devices.</li><li>Select Link a device and scan below.</li></ol>
                    <div class="min-h-56 flex items-center justify-center bg-surface-50 rounded-xl p-4">
                        <img v-if="qr" :src="qr" alt="Scan this code using WhatsApp Linked devices" class="w-56 h-56 bg-white p-2 rounded-lg" />
                        <p v-else role="status" class="text-sm text-surface-600">{{ error ? 'QR code unavailable' : 'Preparing your QR code…' }}</p>
                    </div>
                    <p class="text-xs text-surface-500">QR codes refresh automatically for three minutes. Keep this window open while linking.</p>
                    <Button variant="secondary" :disabled="busy" @click="refreshQr">{{ busy ? 'Refreshing…' : 'Refresh QR' }}</Button>
                </template>
            </template>
            <Button variant="ghost" @click="close">{{ connected ? 'Done' : 'Close' }}</Button>
        </div>
    </Modal>
</template>

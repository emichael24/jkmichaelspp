<template>
    <Head title="WhatsApp Number Pairings" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link href="/admin/settings">
                    <button class="p-2 rounded-xl hover:bg-surface-100">
                        <ArrowLeft class="w-4 h-4 text-surface-500" />
                    </button>
                </Link>
                <div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">WhatsApp Number Pairings</h1>
                    <p class="text-surface-500 mt-0.5 text-sm">Assign WhatsApp numbers to platform users for messaging.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showBulkImport = true" class="px-3 py-2 text-sm font-medium border border-surface-200 rounded-xl hover:bg-surface-50 flex items-center gap-1.5">
                    <Upload class="w-4 h-4" /> Bulk Import
                </button>
                <button @click="confirmAutoPair" class="px-3 py-2 text-sm font-medium bg-amber-50 text-amber-700 border border-amber-200 rounded-xl hover:bg-amber-100 flex items-center gap-1.5">
                    <Zap class="w-4 h-4" /> Auto-Pair from Phone
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-surface-900">{{ stats.total }}</p>
                <p class="text-xs text-surface-500 mt-0.5">Total Users</p>
            </div>
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-success-700">{{ stats.paired }}</p>
                <p class="text-xs text-surface-500 mt-0.5">Paired</p>
            </div>
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ stats.unpaired }}</p>
                <p class="text-xs text-surface-500 mt-0.5">Unpaired</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
            <div class="flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" />
                    <input
                        v-model="filterForm.search"
                        @input="applyFilters"
                        placeholder="Search name, email or WA number…"
                        class="w-full pl-9 pr-3 py-2 text-sm border border-surface-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>
                <select v-model="filterForm.role" @change="applyFilters" class="text-sm border border-surface-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="">All Roles</option>
                    <option value="learner">Learner</option>
                    <option value="facilitator">Facilitator</option>
                    <option value="platform_admin">Admin</option>
                </select>
                <div class="flex gap-2">
                    <button
                        @click="toggleFilter('paired_only')"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors', filterForm.paired_only ? 'bg-success-50 border-success-300 text-success-700' : 'border-surface-200 text-surface-600 hover:bg-surface-50']"
                    >
                        Paired only
                    </button>
                    <button
                        @click="toggleFilter('unpaired_only')"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors', filterForm.unpaired_only ? 'bg-amber-50 border-amber-300 text-amber-700' : 'border-surface-200 text-surface-600 hover:bg-surface-50']"
                    >
                        Unpaired only
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">User</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Phone (Profile)</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">WhatsApp Number</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">WA Session</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Paired By</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-xs font-semibold text-primary-700">
                                        {{ initials(user) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-surface-900">{{ user.first_name }} {{ user.last_name }}</p>
                                        <p class="text-xs text-surface-400">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-surface-600">{{ user.phone || '—' }}</td>
                            <td class="px-5 py-3.5">
                                <!-- Inline edit mode -->
                                <div v-if="editingUserId === user.id" class="flex items-center gap-2">
                                    <input
                                        v-model="editForm.whatsapp_number"
                                        placeholder="+2348012345678"
                                        class="w-40 px-2.5 py-1.5 text-sm border border-primary-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                        @keyup.enter="savePairing(user)"
                                        @keyup.escape="cancelEdit"
                                    />
                                </div>
                                <span v-else :class="['font-mono text-sm', user.whatsapp_number ? 'text-success-700' : 'text-surface-300 italic']">
                                    {{ user.whatsapp_number || 'not set' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div v-if="editingUserId === user.id">
                                    <select v-model="editForm.whatsapp_session" class="w-36 text-xs border border-primary-300 rounded-lg px-2 py-1.5 focus:outline-none">
                                        <option value="">Platform default</option>
                                        <option v-for="s in sessions" :key="s.session_id" :value="s.session_id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <span v-else class="text-xs text-surface-500">{{ user.whatsapp_session || 'default' }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div v-if="user.whatsapp_paired_at" class="text-xs">
                                    <p class="text-surface-700">{{ user.paired_by?.first_name || 'Admin' }}</p>
                                    <p class="text-surface-400">{{ formatDate(user.whatsapp_paired_at) }}</p>
                                </div>
                                <span v-else class="text-xs text-surface-300">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div v-if="editingUserId === user.id" class="flex items-center justify-end gap-1">
                                    <button @click="savePairing(user)" :disabled="saving" class="px-3 py-1.5 text-xs font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                        Save
                                    </button>
                                    <button @click="cancelEdit" class="px-3 py-1.5 text-xs font-medium border border-surface-200 rounded-lg hover:bg-surface-50">
                                        Cancel
                                    </button>
                                </div>
                                <div v-else class="flex items-center justify-end gap-1">
                                    <button @click="startEdit(user)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600" title="Edit pairing">
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button v-if="user.whatsapp_number" @click="unpair(user)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600" title="Remove pairing">
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!users.data?.length" class="p-16 text-center">
                <Users class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No users found</p>
                <p class="text-sm text-surface-400 mt-1">Try adjusting your filters.</p>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-surface-100">
                <p class="text-xs text-surface-500">Showing {{ users.from }}–{{ users.to }} of {{ users.total }}</p>
                <div class="flex gap-1">
                    <button v-for="link in users.links" :key="link.label"
                        v-html="link.label"
                        :disabled="!link.url"
                        @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                        :class="['px-3 py-1.5 text-xs rounded-lg border transition-colors', link.active ? 'bg-primary-600 text-white border-primary-600' : 'border-surface-200 text-surface-600 hover:bg-surface-50 disabled:opacity-40']"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Import Modal -->
    <Modal :show="showBulkImport" @close="showBulkImport = false" maxWidth="lg">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">Bulk Import WhatsApp Numbers</h3>
            <p class="text-sm text-surface-500">Paste one pairing per line in the format: <code class="bg-surface-100 px-1 rounded">email,whatsapp_number</code></p>
            <p class="text-xs text-surface-400">Example:<br>john@example.com,+2348012345678<br>jane@example.com,+2348098765432</p>

            <textarea
                v-model="bulkCsv"
                rows="8"
                placeholder="email,whatsapp_number&#10;john@example.com,+2348012345678"
                class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>

            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Assign WA Session (optional)</label>
                <select v-model="bulkSession" class="w-full text-sm border border-surface-200 rounded-xl px-3 py-2">
                    <option value="">Platform default</option>
                    <option v-for="s in sessions" :key="s.session_id" :value="s.session_id">{{ s.name }}</option>
                </select>
            </div>

            <div v-if="bulkErrors.length" class="bg-danger-50 border border-danger-200 rounded-xl p-3 text-xs text-danger-700 space-y-1">
                <p v-for="err in bulkErrors" :key="err">{{ err }}</p>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-surface-100">
                <Button variant="ghost" @click="showBulkImport = false">Cancel</Button>
                <Button variant="primary" @click="submitBulkImport" :disabled="processingBulk">
                    <span v-if="processingBulk" class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Importing…</span>
                    <span v-else>Import</span>
                </Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import { ArrowLeft, Search, Pencil, X, Upload, Zap, Users } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: { type: Object, default: () => ({ data: [], links: [] }) },
    sessions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, paired: 0, unpaired: 0 }) },
});

// ─── Filters ─────────────────────────────────────────────────────────────────
const filterForm = reactive({
    search: props.filters.search || '',
    role: props.filters.role || '',
    paired_only: props.filters.paired_only === '1' || props.filters.paired_only === true,
    unpaired_only: props.filters.unpaired_only === '1' || props.filters.unpaired_only === true,
});
let searchTimeout;

function applyFilters() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/admin/settings/whatsapp-pairings', {
            search: filterForm.search || undefined,
            role: filterForm.role || undefined,
            paired_only: filterForm.paired_only ? 1 : undefined,
            unpaired_only: filterForm.unpaired_only ? 1 : undefined,
        }, { preserveState: true, preserveScroll: true });
    }, 350);
}

function toggleFilter(key) {
    filterForm.paired_only = false;
    filterForm.unpaired_only = false;
    filterForm[key] = !filterForm[key];
    applyFilters();
}

// ─── Inline Edit ─────────────────────────────────────────────────────────────
const editingUserId = ref(null);
const saving = ref(false);
const editForm = reactive({ whatsapp_number: '', whatsapp_session: '' });

function startEdit(user) {
    editingUserId.value = user.id;
    editForm.whatsapp_number = user.whatsapp_number || '';
    editForm.whatsapp_session = user.whatsapp_session || '';
}

function cancelEdit() {
    editingUserId.value = null;
    editForm.whatsapp_number = '';
    editForm.whatsapp_session = '';
}

function savePairing(user) {
    saving.value = true;
    router.patch(`/admin/settings/whatsapp-pairings/${user.id}/pair`, {
        whatsapp_number: editForm.whatsapp_number,
        whatsapp_session: editForm.whatsapp_session || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { cancelEdit(); },
        onFinish: () => { saving.value = false; },
    });
}

function unpair(user) {
    if (!confirm(`Remove WhatsApp pairing from ${user.first_name} ${user.last_name}?`)) return;
    router.delete(`/admin/settings/whatsapp-pairings/${user.id}/unpair`, { preserveScroll: true });
}

// ─── Auto-pair from phone ─────────────────────────────────────────────────────
const autoPairSession = ref('');
function confirmAutoPair() {
    const session = props.sessions[0]?.session_id || '';
    if (!confirm(`Auto-pair all unpaired users using their profile phone number?\nThis will not overwrite existing WA pairings.`)) return;
    router.post('/admin/settings/whatsapp-pairings/auto-pair', {
        default_session: session,
    }, { preserveScroll: true });
}

// ─── Bulk Import ─────────────────────────────────────────────────────────────
const showBulkImport = ref(false);
const bulkCsv = ref('');
const bulkSession = ref('');
const bulkErrors = ref([]);
const processingBulk = ref(false);

function parseCsv(raw) {
    const lines = raw.trim().split('\n').map(l => l.trim()).filter(Boolean);
    const pairs = [];
    const errors = [];
    for (const line of lines) {
        const parts = line.split(',').map(p => p.trim());
        if (parts.length < 2) { errors.push(`Skipped invalid line: "${line}"`); continue; }
        pairs.push({ email: parts[0], whatsapp_number: parts[1], session: parts[2] || bulkSession.value || null });
    }
    return { pairs, errors };
}

async function submitBulkImport() {
    bulkErrors.value = [];
    const { pairs, errors } = parseCsv(bulkCsv.value);
    if (errors.length) bulkErrors.value = errors;
    if (!pairs.length) { bulkErrors.value.push('No valid entries to import.'); return; }

    processingBulk.value = true;
    try {
        // Resolve email → user_id first
        const res = await axios.post('/admin/settings/whatsapp-pairings/bulk', {
            pairs: pairs.map(p => ({ ...p })),
        });
        showBulkImport.value = false;
        bulkCsv.value = '';
        router.reload({ only: ['users', 'stats'] });
    } catch (e) {
        const msg = e.response?.data?.message || 'Import failed. Check the data and try again.';
        bulkErrors.value = [msg];
    } finally {
        processingBulk.value = false;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function initials(user) {
    return `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase();
}

function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

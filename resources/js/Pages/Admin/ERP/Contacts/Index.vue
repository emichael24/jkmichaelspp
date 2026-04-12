<template>
    <Head title="CRM Contacts" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Contacts</h1>
                <p class="text-surface-500 mt-1 text-sm">{{ contacts.total || 0 }} total contacts</p>
            </div>
            <button @click="showNewContactForm = true" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-colors flex items-center gap-2">
                <UserPlus class="w-4 h-4" /> Add Contact
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <Input
                        v-model="filterForm.search"
                        placeholder="Search by name, email or phone…"
                        :icon="SearchIcon"
                        clearable
                        @update:modelValue="debouncedFilter"
                    />
                </div>
                <select v-model="filterForm.contact_type" @change="applyFilters" class="rounded-xl border-surface-200 text-sm px-3 py-2">
                    <option value="">All Types</option>
                    <option value="lead">Lead</option>
                    <option value="prospect">Prospect</option>
                    <option value="customer">Customer</option>
                    <option value="partner">Partner</option>
                </select>
                <select v-model="filterForm.status" @change="applyFilters" class="rounded-xl border-surface-200 text-sm px-3 py-2">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="unsubscribed">Unsubscribed</option>
                </select>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="my_contacts" v-model="filterForm.assigned_to_me" @change="applyFilters" class="rounded text-primary-600" />
                    <label for="my_contacts" class="text-sm text-surface-700 whitespace-nowrap">My contacts</label>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Contact</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Type</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Source</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Last Contact</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-sm font-bold text-primary-700 flex-shrink-0">
                                        {{ initials(contact) }}
                                    </div>
                                    <div>
                                        <Link :href="`/admin/erp/contacts/${contact.id}`">
                                            <p class="font-semibold text-surface-900 hover:text-primary-600">{{ contact.first_name }} {{ contact.last_name }}</p>
                                        </Link>
                                        <p class="text-xs text-surface-400">{{ contact.email }}</p>
                                        <p v-if="contact.phone" class="text-xs text-surface-400">{{ contact.phone }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <Badge :variant="typeBadgeVariant(contact.contact_type)" size="sm">{{ contact.contact_type }}</Badge>
                            </td>
                            <td class="px-5 py-4 text-surface-500 text-xs">{{ contact.source || '—' }}</td>
                            <td class="px-5 py-4 text-surface-500 text-xs">
                                {{ contact.last_contacted_at ? formatDate(contact.last_contacted_at) : 'Never' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <Badge :variant="contact.status === 'active' ? 'success' : 'secondary'" size="sm">{{ contact.status }}</Badge>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/admin/erp/contacts/${contact.id}`">
                                        <button class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                    </Link>
                                    <button @click="quickWA(contact)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-success-600 transition-colors" title="WhatsApp">
                                        <MessageCircle class="w-4 h-4" />
                                    </button>
                                    <button @click="quickEmail(contact)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-blue-600 transition-colors" title="Email">
                                        <Mail class="w-4 h-4" />
                                    </button>
                                    <button @click="confirmDelete(contact)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!contacts.data?.length" class="p-16 text-center">
                <Users class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No contacts found</p>
                <p class="text-sm text-surface-400 mt-1">Try adjusting your filters or add a new contact.</p>
            </div>

            <!-- Pagination -->
            <div v-if="contacts.last_page > 1" class="border-t border-surface-100 px-5 py-3 flex items-center justify-between">
                <p class="text-sm text-surface-400">Showing {{ contacts.from }}–{{ contacts.to }} of {{ contacts.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in contacts.links"
                        :key="link.label"
                        :href="link.url"
                        :class="['px-3 py-1.5 rounded-lg text-sm transition-colors', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
                        v-html="link.label"
                        preserve-state
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- New Contact Modal -->
    <Modal :show="showNewContactForm" @close="showNewContactForm = false">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">Add New Contact</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-surface-700 mb-1">First Name *</label>
                    <Input v-model="newForm.first_name" :error="newForm.errors.first_name" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-surface-700 mb-1">Last Name</label>
                    <Input v-model="newForm.last_name" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-surface-700 mb-1">Email *</label>
                <Input v-model="newForm.email" type="email" :error="newForm.errors.email" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-surface-700 mb-1">Phone</label>
                    <Input v-model="newForm.phone" placeholder="+234…" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-surface-700 mb-1">Type</label>
                    <select v-model="newForm.contact_type" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                        <option value="lead">Lead</option>
                        <option value="prospect">Prospect</option>
                        <option value="customer">Customer</option>
                        <option value="partner">Partner</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-surface-700 mb-1">Notes</label>
                <textarea v-model="newForm.notes" rows="2" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <Button variant="ghost" @click="showNewContactForm = false">Cancel</Button>
                <Button variant="primary" @click="saveContact" :disabled="newForm.processing">
                    {{ newForm.processing ? 'Saving…' : 'Add Contact' }}
                </Button>
            </div>
        </div>
    </Modal>

    <!-- Quick Send Modal -->
    <Modal :show="showQuickSend" @close="closeQuickSend">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">
                Send {{ quickSendChannel === 'whatsapp' ? 'WhatsApp' : 'Email' }} to {{ quickSendContact?.first_name }}
            </h3>
            <div v-if="quickSendChannel === 'email'">
                <label class="block text-xs font-medium text-surface-700 mb-1">Subject</label>
                <Input v-model="quickSendForm.subject" />
            </div>
            <div>
                <label class="block text-xs font-medium text-surface-700 mb-1">Message</label>
                <textarea v-model="quickSendForm.message" rows="4" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <Button variant="ghost" @click="closeQuickSend">Cancel</Button>
                <Button variant="primary" @click="sendQuick" :disabled="quickSendForm.processing">
                    {{ quickSendForm.processing ? 'Sending…' : 'Send' }}
                </Button>
            </div>
        </div>
    </Modal>

    <!-- Delete Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-surface-900">Delete Contact</h3>
            <p class="mt-2 text-sm text-surface-500">Delete "{{ contactToDelete?.first_name }} {{ contactToDelete?.last_name }}"? This cannot be undone.</p>
            <div class="mt-6 flex justify-end gap-3">
                <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
                <Button variant="danger" @click="deleteContact" :disabled="deleteForm.processing">Delete</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { UserPlus, Users, Eye, Mail, MessageCircle, Trash2, Search as SearchIcon } from 'lucide-vue-next';
import axios from 'axios';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search: props.filters.search || '',
    contact_type: props.filters.contact_type || '',
    status: props.filters.status || '',
    assigned_to_me: props.filters.assigned_to_me || false,
});

let filterTimer = null;
function debouncedFilter() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 400);
}
function applyFilters() {
    router.get('/admin/erp/contacts', filterForm, { preserveState: true, replace: true });
}

function initials(c) { return (c.first_name?.[0] || '') + (c.last_name?.[0] || ''); }
function typeBadgeVariant(t) { return { lead: 'warning', prospect: 'info', customer: 'success', partner: 'secondary' }[t] || 'secondary'; }
function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

// ─── New Contact ───
const showNewContactForm = ref(false);
const newForm = useForm({ first_name: '', last_name: '', email: '', phone: '', contact_type: 'lead', notes: '' });

function saveContact() {
    newForm.post('/admin/erp/contacts', { onSuccess: () => { showNewContactForm.value = false; newForm.reset(); } });
}

// ─── Quick Send ───
const showQuickSend = ref(false);
const quickSendChannel = ref('whatsapp');
const quickSendContact = ref(null);
const quickSendForm = useForm({ recipient_type: 'contact', recipient_id: null, channel: 'whatsapp', subject: '', message: '' });

function quickWA(contact) {
    quickSendChannel.value = 'whatsapp';
    quickSendContact.value = contact;
    quickSendForm.channel = 'whatsapp';
    quickSendForm.recipient_id = contact.id;
    quickSendForm.recipient_type = 'contact';
    showQuickSend.value = true;
}
function quickEmail(contact) {
    quickSendChannel.value = 'email';
    quickSendContact.value = contact;
    quickSendForm.channel = 'email';
    quickSendForm.recipient_id = contact.id;
    quickSendForm.recipient_type = 'contact';
    showQuickSend.value = true;
}
function sendQuick() {
    quickSendForm.post('/admin/erp/messaging/quick-send', { onSuccess: closeQuickSend });
}
function closeQuickSend() { showQuickSend.value = false; quickSendForm.reset(); quickSendContact.value = null; }

// ─── Delete ───
const showDeleteModal = ref(false);
const contactToDelete = ref(null);
const deleteForm = useForm({});

function confirmDelete(contact) { contactToDelete.value = contact; showDeleteModal.value = true; }
function deleteContact() {
    deleteForm.delete(`/admin/erp/contacts/${contactToDelete.value.id}`, {
        onSuccess: () => { showDeleteModal.value = false; contactToDelete.value = null; },
    });
}
</script>

<template>
    <Head title="CRM Contacts" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Contacts</h1>
                <p class="text-surface-500 mt-1 text-sm">{{ contacts.total || 0 }} total contacts</p>
            </div>
            <div class="flex flex-wrap gap-2">
            <Link :href="`/admin/erp/messaging?compose=1&audience=crm${filterForm.type ? '&contact_type=' + encodeURIComponent(filterForm.type) : ''}`" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-4 py-2 text-sm font-semibold"><Mail class="h-4 w-4" />Bulk campaign</Link>
            <Button variant="secondary" @click="showImport = true"><Upload class="mr-2 h-4 w-4" /> Import contacts</Button>
            <button @click="showNewContactForm = true" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-colors flex items-center gap-2">
                <UserPlus class="w-4 h-4" /> Add Contact
            </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <Input
                        v-model="filterForm.search"
                        aria-label="Search contacts" placeholder="Search name, email, phone or company…"
                        :icon="SearchIcon"
                        clearable
                        @update:modelValue="debouncedFilter"
                    />
                </div>
                <select aria-label="Filter by contact type" v-model="filterForm.type" @change="applyFilters" class="rounded-xl border-surface-200 text-sm px-3 py-2">
                    <option value="">All Types</option>
                    <option value="lead">Lead</option>
                    <option value="prospect">Prospect</option>
                    <option value="customer">Customer</option>
                    <option value="partner">Partner</option><option value="student">Student</option><option value="alumni">Alumni</option>
                </select>
                <select aria-label="Filter by contact status" v-model="filterForm.status" @change="applyFilters" class="rounded-xl border-surface-200 text-sm px-3 py-2">
                    <option value="">All Status</option>
                    <option v-for="status in contactStatuses" :key="status" :value="status">{{ titleCase(status) }}</option>
                </select>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="my_contacts" v-model="filterForm.assigned_to_me" @change="applyFilters" class="rounded text-primary-600" />
                    <label for="my_contacts" class="text-sm text-surface-700 whitespace-nowrap">My contacts</label>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Contacts list">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th scope="col" class="text-left px-5 py-3 font-semibold text-surface-600">Contact</th>
                            <th scope="col" class="text-left px-5 py-3 font-semibold text-surface-600">Type</th>
                            <th scope="col" class="text-left px-5 py-3 font-semibold text-surface-600">Source</th>
                            <th scope="col" class="text-left px-5 py-3 font-semibold text-surface-600">Last Contact</th>
                            <th scope="col" class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th scope="col" class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
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
                                        <p class="text-xs text-surface-600">{{ contact.email || 'No email' }}</p><p v-if="contact.company" class="text-xs text-surface-600">{{ contact.company }}</p>
                                        <p v-if="contact.phone" class="text-xs text-surface-500">{{ contact.phone }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <Badge :variant="typeBadgeVariant(contact.type)" size="sm">{{ titleCase(contact.type) }}</Badge>
                            </td>
                            <td class="px-5 py-4 text-surface-500 text-xs">{{ contact.source || '—' }}</td>
                            <td class="px-5 py-4 text-surface-500 text-xs">
                                {{ contact.last_contacted_at ? formatDate(contact.last_contacted_at) : 'Never' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <Badge :variant="contact.status === 'converted' ? 'success' : 'secondary'" size="sm">{{ contact.status }}</Badge>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/admin/erp/contacts/${contact.id}`" :aria-label="`View ${contact.first_name} ${contact.last_name}`" class="p-2 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-primary-600"><Eye class="w-4 h-4" /></Link>
                                    <button @click="quickWA(contact)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-success-600 transition-colors" aria-label="Send WhatsApp message" :disabled="!contact.whatsapp && !contact.phone">
                                        <MessageCircle class="w-4 h-4" />
                                    </button>
                                    <button @click="quickEmail(contact)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-blue-600 transition-colors" aria-label="Send email" :disabled="!contact.email">
                                        <Mail class="w-4 h-4" />
                                    </button>
                                    <button :aria-label="`Archive ${contact.first_name} ${contact.last_name}`" @click="confirmDelete(contact)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-danger-600 transition-colors">
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
                <p class="text-sm text-surface-500 mt-1">Try adjusting your filters or add a new contact.</p>
            </div>

            <!-- Pagination -->
            <div v-if="contacts.last_page > 1" class="border-t border-surface-100 px-5 py-3 flex items-center justify-between">
                <p class="text-sm text-surface-500">Showing {{ contacts.from }}–{{ contacts.to }} of {{ contacts.total }}</p>
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

    <Modal :show="showNewContactForm" size="lg" title="Add contact" subtitle="Capture the details your team needs. Fields marked * are required." :closeable="!newForm.processing" @close="showNewContactForm = false">
        <form @submit.prevent="saveContact" class="space-y-6">
            <ContactFields :form="newForm" />
            <div class="flex justify-end gap-3 border-t border-surface-100 pt-4">
                <Button type="button" variant="ghost" :disabled="newForm.processing" @click="showNewContactForm = false">Cancel</Button>
                <Button type="submit" :disabled="newForm.processing">{{ newForm.processing ? 'Saving…' : 'Create contact' }}</Button>
            </div>
        </form>
    </Modal>
    <ContactImport :show="showImport" @close="showImport = false" />

    <!-- Quick Send Modal -->
    <Modal :show="showQuickSend" size="xl" title="Compose a message" :closeable="!quickSendForm.processing" @close="closeQuickSend">
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">
                Send {{ quickSendChannel === 'whatsapp' ? 'WhatsApp' : 'Email' }} to {{ quickSendContact?.first_name }}
            </h3>
            <div v-if="quickSendChannel === 'email'">
                <Input v-model="quickSendForm.subject" label="Email subject" :error="quickSendForm.errors.subject" />
            </div>
            <div v-if="Object.keys(quickSendForm.errors).length" role="alert" class="rounded-xl bg-danger-50 p-3 text-sm text-danger-700"><p v-for="(error, key) in quickSendForm.errors" :key="key">{{ error }}</p></div>
            <EmailEditor v-if="quickSendChannel === 'email'" v-model="quickSendForm.message" :subject="quickSendForm.subject" :error="quickSendForm.errors.message" :disabled="quickSendForm.processing" @apply-template="template => quickSendForm.subject = template.subject" />
            <div v-else><label for="crm-quick-message" class="mb-1 block text-sm font-medium">Message</label><textarea id="crm-quick-message" v-model="quickSendForm.message" rows="6" maxlength="3500" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm" /></div>
            <p class="text-xs text-surface-500">This sends individual correspondence. Use a bulk campaign to contact an audience.</p>
            <div class="flex justify-end gap-3">
                <Button variant="ghost" @click="closeQuickSend">Cancel</Button>
                <Button variant="primary" @click="sendQuick" :disabled="quickSendForm.processing">
                    {{ quickSendForm.processing ? 'Queueing…' : 'Queue message' }}
                </Button>
            </div>
        </div>
    </Modal>

    <!-- Delete Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-surface-900">Archive contact</h3>
            <p class="mt-2 text-sm text-surface-500">Delete "{{ contactToDelete?.first_name }} {{ contactToDelete?.last_name }}"? This removes the contact from active lists and retains their history.</p>
            <div class="mt-6 flex justify-end gap-3">
                <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
                <Button variant="danger" @click="deleteContact" :disabled="deleteForm.processing">Delete</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, reactive, onBeforeUnmount } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import EmailEditor from '@/Components/Messaging/EmailEditor.vue';
import { Upload, UserPlus, Users, Eye, Mail, MessageCircle, Trash2, Search as SearchIcon } from 'lucide-vue-next';
import ContactFields from '@/Components/CRM/ContactFields.vue';
import ContactImport from '@/Components/CRM/ContactImport.vue';
import { contactValues, contactStatuses, titleCase } from '@/Components/CRM/contactFields';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search: props.filters.search || '',
    type: props.filters.type || '',
    status: props.filters.status || '',
    assigned_to_me: props.filters.assigned_to_me || false,
});

let filterTimer = null;
onBeforeUnmount(() => clearTimeout(filterTimer));
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
const newForm = useForm(contactValues());
const showImport = ref(false);

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
    quickSendForm.transform(data => ({ ...data, body_format: data.channel === 'email' ? 'html' : 'text' }));
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

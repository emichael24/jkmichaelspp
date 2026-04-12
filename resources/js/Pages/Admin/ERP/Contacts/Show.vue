<template>
    <Head :title="`${contact.first_name} ${contact.last_name} – CRM`" />

    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/admin/erp/contacts" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </Link>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-display font-bold text-surface-900">{{ contact.first_name }} {{ contact.last_name }}</h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <Badge :variant="typeBadgeVariant(contact.contact_type)" size="sm">{{ contact.contact_type }}</Badge>
                    <Badge :variant="contact.status === 'active' ? 'success' : 'secondary'" size="sm">{{ contact.status }}</Badge>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="quickWA" class="px-3 py-2 bg-success-50 text-success-700 border border-success-200 rounded-xl text-sm font-medium hover:bg-success-100 flex items-center gap-1.5">
                    <MessageCircle class="w-4 h-4" /> WhatsApp
                </button>
                <button @click="quickEmail" class="px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-sm font-medium hover:bg-blue-100 flex items-center gap-1.5">
                    <Mail class="w-4 h-4" /> Email
                </button>
                <button @click="showEditForm = !showEditForm" class="p-2 rounded-xl border border-surface-200 hover:bg-surface-100 text-surface-500">
                    <Pencil class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Details -->
            <div class="space-y-5">
                <!-- Contact Info Card -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5 space-y-4">
                    <h2 class="font-semibold text-surface-800">Contact Details</h2>

                    <div v-if="!showEditForm" class="space-y-3">
                        <InfoRow label="Email" :value="contact.email" />
                        <InfoRow label="Phone" :value="contact.phone || '—'" />
                        <InfoRow label="Company" :value="contact.company || '—'" />
                        <InfoRow label="Job Title" :value="contact.job_title || '—'" />
                        <InfoRow label="Source" :value="contact.source || '—'" />
                        <InfoRow label="Location" :value="contact.location || '—'" />
                        <InfoRow label="Last Contacted" :value="contact.last_contacted_at ? formatDate(contact.last_contacted_at) : 'Never'" />
                        <div v-if="contact.notes" class="pt-2 border-t border-surface-100">
                            <p class="text-xs text-surface-500 font-medium mb-1">Notes</p>
                            <p class="text-sm text-surface-700">{{ contact.notes }}</p>
                        </div>
                    </div>

                    <form v-else @submit.prevent="saveEdit" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs text-surface-600 mb-1 block">First Name</label>
                                <Input v-model="editForm.first_name" size="sm" />
                            </div>
                            <div>
                                <label class="text-xs text-surface-600 mb-1 block">Last Name</label>
                                <Input v-model="editForm.last_name" size="sm" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-surface-600 mb-1 block">Email</label>
                            <Input v-model="editForm.email" type="email" size="sm" />
                        </div>
                        <div>
                            <label class="text-xs text-surface-600 mb-1 block">Phone</label>
                            <Input v-model="editForm.phone" size="sm" />
                        </div>
                        <div>
                            <label class="text-xs text-surface-600 mb-1 block">Company</label>
                            <Input v-model="editForm.company" size="sm" />
                        </div>
                        <div>
                            <label class="text-xs text-surface-600 mb-1 block">Type</label>
                            <select v-model="editForm.contact_type" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                                <option value="lead">Lead</option>
                                <option value="prospect">Prospect</option>
                                <option value="customer">Customer</option>
                                <option value="partner">Partner</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-surface-600 mb-1 block">Notes</label>
                            <textarea v-model="editForm.notes" rows="3" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-xs resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <Button type="submit" variant="primary" size="sm" :disabled="editForm.processing">Save</Button>
                            <Button variant="ghost" size="sm" @click="showEditForm = false">Cancel</Button>
                        </div>
                    </form>
                </div>

                <!-- Deals -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-surface-800">Deals</h2>
                        <button @click="showNewDealForm = true" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                            <Plus class="w-3.5 h-3.5" /> New Deal
                        </button>
                    </div>
                    <div v-if="contact.deals?.length" class="space-y-2">
                        <div v-for="deal in contact.deals" :key="deal.id" class="p-3 bg-surface-50 rounded-xl border border-surface-100">
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-sm text-surface-800">{{ deal.name }}</p>
                                <span class="text-xs font-semibold text-success-700">₦{{ Number(deal.amount).toLocaleString() }}</span>
                            </div>
                            <span class="text-xs px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full mt-1 inline-block">{{ deal.stage }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-surface-400">No deals yet.</p>
                </div>
            </div>

            <!-- Right: Activity Timeline -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Log Activity -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5 space-y-4">
                    <h2 class="font-semibold text-surface-800">Log Activity</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <button
                            v-for="aType in activityTypes"
                            :key="aType.value"
                            @click="activityForm.activity_type = aType.value"
                            :class="[
                                'py-2 rounded-xl text-xs font-medium border transition-colors flex items-center justify-center gap-1.5',
                                activityForm.activity_type === aType.value
                                    ? 'bg-primary-600 text-white border-primary-600'
                                    : 'bg-white text-surface-600 border-surface-200 hover:border-primary-400',
                            ]"
                        >
                            <component :is="aType.icon" class="w-3.5 h-3.5" />
                            {{ aType.label }}
                        </button>
                    </div>
                    <div>
                        <label class="text-xs text-surface-600 mb-1 block">Subject</label>
                        <Input v-model="activityForm.subject" placeholder="e.g. Follow-up call about course enrollment" />
                    </div>
                    <div>
                        <label class="text-xs text-surface-600 mb-1 block">Notes</label>
                        <textarea v-model="activityForm.notes" rows="2" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <Button variant="primary" size="sm" @click="logActivity" :disabled="activityForm.processing || !activityForm.subject">
                        {{ activityForm.processing ? 'Logging…' : 'Log Activity' }}
                    </Button>
                </div>

                <!-- Activity Feed -->
                <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5 space-y-3">
                    <h2 class="font-semibold text-surface-800">Activity History</h2>
                    <div v-if="activities.length" class="space-y-3">
                        <div v-for="activity in activities" :key="activity.id"
                             :class="['flex items-start gap-3 p-3 rounded-xl border', activity.is_completed ? 'bg-surface-50 border-surface-100' : 'bg-white border-surface-200']">
                            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', activityBg(activity.activity_type)]">
                                <component :is="activityIcon(activity.activity_type)" class="w-4 h-4 text-white" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-start justify-between">
                                    <p class="font-medium text-surface-900 text-sm">{{ activity.subject }}</p>
                                    <button v-if="!activity.is_completed" @click="completeActivity(activity)" class="text-xs text-success-600 hover:underline ml-2 flex-shrink-0">Done</button>
                                </div>
                                <p v-if="activity.notes" class="text-xs text-surface-500 mt-0.5">{{ activity.notes }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-surface-400">{{ formatDateTime(activity.created_at) }}</span>
                                    <span v-if="activity.is_completed" class="text-xs text-success-600 font-medium">✓ Completed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center">
                        <ClipboardList class="w-8 h-8 text-surface-300 mx-auto mb-2" />
                        <p class="text-sm text-surface-400">No activities yet. Log the first one above.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Deal Modal -->
    <Modal :show="showNewDealForm" @close="showNewDealForm = false">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">New Deal</h3>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Deal Name *</label>
                <Input v-model="dealForm.name" :error="dealForm.errors.name" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Value (₦)</label>
                    <Input v-model="dealForm.amount" type="number" min="0" />
                </div>
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Stage</label>
                    <select v-model="dealForm.stage" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                        <option value="new">New</option>
                        <option value="qualified">Qualified</option>
                        <option value="proposal">Proposal</option>
                        <option value="negotiation">Negotiation</option>
                        <option value="closed_won">Closed Won</option>
                        <option value="closed_lost">Closed Lost</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Expected Close Date</label>
                <Input v-model="dealForm.expected_close_date" type="date" />
            </div>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Notes</label>
                <textarea v-model="dealForm.notes" rows="2" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <Button variant="ghost" @click="showNewDealForm = false">Cancel</Button>
                <Button variant="primary" @click="saveDeal" :disabled="dealForm.processing">Add Deal</Button>
            </div>
        </div>
    </Modal>

    <!-- Quick Send Modal -->
    <Modal :show="showQuickSend" @close="closeQuickSend">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">
                Quick {{ quickSendChannel === 'whatsapp' ? 'WhatsApp' : 'Email' }}
            </h3>
            <div v-if="quickSendChannel === 'email'">
                <label class="text-xs font-medium text-surface-700 mb-1 block">Subject</label>
                <Input v-model="quickForm.subject" />
            </div>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Message</label>
                <textarea v-model="quickForm.message" rows="4" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <Button variant="ghost" @click="closeQuickSend">Cancel</Button>
                <Button variant="primary" @click="sendQuick" :disabled="quickForm.processing">Send</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { ArrowLeft, Plus, Pencil, Mail, MessageCircle, Phone, Users, ClipboardList, Activity, Video, FileText } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    contact: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
});

// Inline component for info rows
const InfoRow = { template: '<div class="flex justify-between"><p class="text-xs text-surface-500">{{ label }}</p><p class="text-sm text-surface-800 text-right max-w-xs truncate">{{ value }}</p></div>', props: ['label', 'value'] };

function typeBadgeVariant(t) { return { lead: 'warning', prospect: 'info', customer: 'success', partner: 'secondary' }[t] || 'secondary'; }
function activityBg(type) { return { call: 'bg-blue-500', email: 'bg-purple-500', whatsapp: 'bg-green-500', meeting: 'bg-amber-500', note: 'bg-surface-500', video: 'bg-red-500' }[type] || 'bg-surface-400'; }
function activityIcon(type) { return { call: Phone, email: Mail, whatsapp: MessageCircle, meeting: Users, note: ClipboardList, video: Video }[type] || Activity; }
function formatDate(dt) { return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); }
function formatDateTime(dt) { return new Date(dt).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); }

// ─── Edit ───
const showEditForm = ref(false);
const editForm = useForm({
    first_name: props.contact.first_name, last_name: props.contact.last_name,
    email: props.contact.email, phone: props.contact.phone || '', company: props.contact.company || '',
    contact_type: props.contact.contact_type, notes: props.contact.notes || '',
});
function saveEdit() {
    editForm.put(`/admin/erp/contacts/${props.contact.id}`, { onSuccess: () => { showEditForm.value = false; } });
}

// ─── Activity ───
const activityTypes = [
    { value: 'call', label: 'Call', icon: Phone },
    { value: 'email', label: 'Email', icon: Mail },
    { value: 'whatsapp', label: 'WhatsApp', icon: MessageCircle },
    { value: 'meeting', label: 'Meeting', icon: Users },
    { value: 'note', label: 'Note', icon: ClipboardList },
];
const activityForm = useForm({ contact_id: props.contact.id, activity_type: 'call', subject: '', notes: '' });
function logActivity() {
    activityForm.post('/admin/erp/activities', { preserveScroll: true, onSuccess: () => { activityForm.subject = ''; activityForm.notes = ''; } });
}
function completeActivity(activity) {
    router.patch(`/admin/erp/activities/${activity.id}/complete`, {}, { preserveScroll: true });
}

// ─── Deal ───
const showNewDealForm = ref(false);
const dealForm = useForm({ contact_id: props.contact.id, name: '', amount: '', stage: 'new', expected_close_date: '', notes: '' });
function saveDeal() {
    dealForm.post('/admin/erp/deals', { onSuccess: () => { showNewDealForm.value = false; dealForm.reset(); dealForm.contact_id = props.contact.id; } });
}

// ─── Quick Send ───
const showQuickSend = ref(false);
const quickSendChannel = ref('whatsapp');
const quickForm = useForm({ recipient_type: 'contact', recipient_id: props.contact.id, channel: 'whatsapp', subject: '', message: '' });

function quickWA() { quickSendChannel.value = 'whatsapp'; quickForm.channel = 'whatsapp'; showQuickSend.value = true; }
function quickEmail() { quickSendChannel.value = 'email'; quickForm.channel = 'email'; showQuickSend.value = true; }
function sendQuick() { quickForm.post('/admin/erp/messaging/quick-send', { onSuccess: closeQuickSend }); }
function closeQuickSend() { showQuickSend.value = false; quickForm.reset(); quickForm.recipient_id = props.contact.id; }
</script>

<template>
    <Head title="Messaging Campaigns" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Messaging</h1>
                <p class="text-surface-500 mt-1 text-sm">Email and WhatsApp campaigns, broadcasts, and follow-ups.</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/admin/erp/whatsapp/sessions">
                    <Button variant="secondary" size="sm">
                        <Wifi class="w-4 h-4 mr-1.5" /> WA Sessions
                    </Button>
                </Link>
                <button @click="showNewCampaign = true" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 flex items-center gap-2">
                    <Plus class="w-4 h-4" /> New Campaign
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div v-for="stat in statsCards" :key="stat.label" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4">
                <p class="text-xs text-surface-500 font-medium">{{ stat.label }}</p>
                <p class="text-xl font-bold text-surface-900 mt-1">{{ stat.value }}</p>
                <p v-if="stat.sub" class="text-xs mt-0.5" :class="stat.subColor || 'text-surface-400'">{{ stat.sub }}</p>
            </div>
        </div>

        <!-- Campaign Tabs -->
        <div class="flex gap-1 bg-surface-100 p-1 rounded-xl w-fit">
            <button v-for="tab in ['All', 'Email', 'WhatsApp', 'Scheduled']" :key="tab"
                @click="activeTab = tab"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', activeTab === tab ? 'bg-white shadow-sm text-surface-900' : 'text-surface-500 hover:text-surface-700']">
                {{ tab }}
            </button>
        </div>

        <!-- Campaign List -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Campaign</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Channel</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Sent</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Delivered</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Failed</th>
                            <th class="text-center px-5 py-3 font-semibold text-surface-600">Status</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Date</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="campaign in filteredCampaigns" :key="campaign.id" class="hover:bg-surface-50 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-surface-900">{{ campaign.name }}</p>
                                <p class="text-xs text-surface-400 truncate max-w-xs">{{ campaign.subject || campaign.body?.substring(0, 60) }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', campaign.channel === 'whatsapp' ? 'bg-green-50 text-green-700' : 'bg-blue-50 text-blue-700']">
                                    <component :is="campaign.channel === 'whatsapp' ? MessageCircle : Mail" class="w-3 h-3" />
                                    {{ campaign.channel === 'whatsapp' ? 'WhatsApp' : 'Email' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center text-surface-600">{{ campaign.total_sent || 0 }}</td>
                            <td class="px-5 py-4 text-center text-success-600 font-medium">{{ campaign.total_delivered || 0 }}</td>
                            <td class="px-5 py-4 text-center text-danger-600">{{ campaign.total_failed || 0 }}</td>
                            <td class="px-5 py-4 text-center">
                                <Badge :variant="campaignStatusVariant(campaign.status)" size="sm">{{ campaign.status }}</Badge>
                            </td>
                            <td class="px-5 py-4 text-surface-500 text-xs">
                                {{ campaign.sent_at ? formatDate(campaign.sent_at) : (campaign.scheduled_at ? 'Sched: ' + formatDate(campaign.scheduled_at) : '—') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="viewCampaign(campaign)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="campaign.status === 'draft'"
                                        @click="sendCampaign(campaign)"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-success-600"
                                        title="Send now"
                                    >
                                        <Send class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!filteredCampaigns.length" class="p-16 text-center">
                <MessageSquare class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No campaigns yet</p>
                <p class="text-sm text-surface-400 mt-1">Create your first email or WhatsApp campaign.</p>
            </div>
        </div>
    </div>

    <!-- New Campaign Modal -->
    <Modal :show="showNewCampaign" @close="showNewCampaign = false" maxWidth="lg">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">New Campaign</h3>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Campaign Name *</label>
                    <Input v-model="newCampaignForm.name" :error="newCampaignForm.errors.name" />
                </div>
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Channel</label>
                    <select v-model="newCampaignForm.channel" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                        <option value="email">Email</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="both">Both</option>
                    </select>
                </div>
            </div>

            <div v-if="newCampaignForm.channel !== 'whatsapp'">
                <label class="text-xs font-medium text-surface-700 mb-1 block">Email Subject</label>
                <Input v-model="newCampaignForm.subject" placeholder="Enter subject line…" />
            </div>

            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Message Body *</label>
                <textarea
                    v-model="newCampaignForm.body"
                    rows="5"
                    placeholder="Your message content…"
                    class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                ></textarea>
            </div>

            <!-- Audience -->
            <div class="border-t border-surface-100 pt-3">
                <p class="text-sm font-semibold text-surface-800 mb-3">Audience</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-surface-700 mb-1 block">By Role</label>
                        <select v-model="newCampaignForm.audience_filter.role" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                            <option value="">All Users</option>
                            <option value="learner">Learners</option>
                            <option value="facilitator">Facilitators</option>
                            <option value="platform_admin">Admins</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-surface-700 mb-1 block">CRM Contact Type</label>
                        <select v-model="newCampaignForm.audience_filter.contact_type" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                            <option value="">All Contacts</option>
                            <option value="lead">Leads</option>
                            <option value="prospect">Prospects</option>
                            <option value="customer">Customers</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Schedule -->
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Schedule (optional — leave blank to send immediately)</label>
                <Input v-model="newCampaignForm.scheduled_at" type="datetime-local" />
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-surface-100">
                <Button variant="ghost" @click="showNewCampaign = false">Cancel</Button>
                <Button variant="secondary" @click="saveCampaignDraft" :disabled="newCampaignForm.processing">Save Draft</Button>
                <Button variant="primary" @click="saveCampaignAndSend" :disabled="newCampaignForm.processing">
                    <Send class="w-4 h-4 mr-1.5" /> Save & Send
                </Button>
            </div>
        </div>
    </Modal>

    <!-- Campaign Detail Modal -->
    <Modal :show="!!activeCampaign" @close="activeCampaign = null" maxWidth="lg">
        <div v-if="activeCampaign" class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-surface-900">{{ activeCampaign.name }}</h3>
                <Badge :variant="campaignStatusVariant(activeCampaign.status)" size="sm">{{ activeCampaign.status }}</Badge>
            </div>
            <div class="grid grid-cols-3 gap-4 text-center border border-surface-100 rounded-xl p-3">
                <div>
                    <p class="text-xl font-bold text-surface-900">{{ activeCampaign.total_sent || 0 }}</p>
                    <p class="text-xs text-surface-400">Sent</p>
                </div>
                <div>
                    <p class="text-xl font-bold text-success-700">{{ activeCampaign.total_delivered || 0 }}</p>
                    <p class="text-xs text-surface-400">Delivered</p>
                </div>
                <div>
                    <p class="text-xl font-bold text-danger-600">{{ activeCampaign.total_failed || 0 }}</p>
                    <p class="text-xs text-surface-400">Failed</p>
                </div>
            </div>
            <div v-if="activeCampaign.subject" class="bg-surface-50 rounded-xl p-3">
                <p class="text-xs text-surface-500 mb-1">Subject</p>
                <p class="text-sm font-medium text-surface-800">{{ activeCampaign.subject }}</p>
            </div>
            <div class="bg-surface-50 rounded-xl p-3">
                <p class="text-xs text-surface-500 mb-1">Message</p>
                <p class="text-sm text-surface-700 whitespace-pre-wrap">{{ activeCampaign.body }}</p>
            </div>
            <div v-if="activeCampaign.status === 'draft'" class="flex justify-end">
                <Button variant="primary" @click="sendCampaign(activeCampaign)">
                    <Send class="w-4 h-4 mr-1.5" /> Send Now
                </Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { Plus, Eye, Send, Mail, MessageCircle, MessageSquare, Wifi } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    campaigns: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const activeTab = ref('All');
const showNewCampaign = ref(false);
const activeCampaign = ref(null);

const filteredCampaigns = computed(() => {
    if (activeTab.value === 'All') return props.campaigns;
    if (activeTab.value === 'Scheduled') return props.campaigns.filter(c => c.scheduled_at && c.status === 'draft');
    return props.campaigns.filter(c => c.channel === activeTab.value.toLowerCase());
});

const statsCards = computed(() => [
    { label: 'Total Sent', value: Number(props.stats.total_sent || 0).toLocaleString() },
    { label: 'Delivered', value: Number(props.stats.total_delivered || 0).toLocaleString(), sub: deliveryRate.value + '% delivery rate', subColor: 'text-success-600' },
    { label: 'Failed', value: Number(props.stats.total_failed || 0).toLocaleString(), sub: 'check WhatsApp session', subColor: 'text-danger-500' },
    { label: 'Campaigns', value: props.campaigns.length },
]);

const deliveryRate = computed(() => {
    const sent = props.stats.total_sent || 0;
    const delivered = props.stats.total_delivered || 0;
    if (!sent) return 0;
    return Math.round((delivered / sent) * 100);
});

function campaignStatusVariant(status) {
    return { draft: 'secondary', sending: 'warning', sent: 'success', failed: 'danger', scheduled: 'info' }[status] || 'secondary';
}

function viewCampaign(campaign) { activeCampaign.value = campaign; }

function sendCampaign(campaign) {
    if (!confirm(`Send campaign "${campaign.name}" now?`)) return;
    router.post(`/admin/erp/messaging/${campaign.id}/send`, {}, {
        preserveScroll: true,
        onSuccess: () => { activeCampaign.value = null; },
    });
}

const newCampaignForm = useForm({
    name: '', channel: 'email', subject: '', body: '',
    audience_filter: { role: '', contact_type: '' },
    scheduled_at: '',
    send_immediately: false,
});

function saveCampaignDraft() {
    newCampaignForm.send_immediately = false;
    newCampaignForm.post('/admin/erp/messaging', { onSuccess: () => { showNewCampaign.value = false; newCampaignForm.reset(); newCampaignForm.audience_filter = { role: '', contact_type: '' }; } });
}

function saveCampaignAndSend() {
    newCampaignForm.send_immediately = true;
    newCampaignForm.post('/admin/erp/messaging', { onSuccess: () => { showNewCampaign.value = false; newCampaignForm.reset(); newCampaignForm.audience_filter = { role: '', contact_type: '' }; } });
}

function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

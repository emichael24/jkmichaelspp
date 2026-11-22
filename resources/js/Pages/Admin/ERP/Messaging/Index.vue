<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import EmailEditor from '@/Components/Messaging/EmailEditor.vue';
import AudiencePicker from '@/Components/Messaging/AudiencePicker.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Mail, Search, ArrowRight, Users } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/UI/Modal.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
defineOptions({ layout: AdminLayout });
const props = defineProps({ campaigns: Object, stats: Object, filters: Object, audienceOptions: Object, initialAudience: Object, compose: Boolean });
const open = ref(props.compose);
const search = ref(props.filters?.search || '');
const channel = ref(props.filters?.channel || '');
const form = useForm({ name: '', channel: 'email', type: 'broadcast', subject: '', body: '', body_format: 'html', audience_filter: { source: 'users', role: '', organization_id: '', organization_members: false, contact_types: [], crm_organization_id: '', ...props.initialAudience }, scheduled_at: '', send_immediately: false });
function filter() { router.get('/admin/erp/messaging', { search: search.value, channel: channel.value }, { preserveState: true, replace: true }); }
function save() {
    form.transform(data => ({ ...data, body_format: data.channel === 'email' ? 'html' : 'text', scheduled_at: data.scheduled_at ? new Date(data.scheduled_at).toISOString() : null }));
    form.post('/admin/erp/messaging', { onSuccess: () => { open.value = false; form.reset(); } });
}
const preview = ref(null), previewError = ref(''), previewBusy = ref(false);
let previewVersion = 0;
watch(() => [form.channel, JSON.stringify(form.audience_filter)], () => { previewVersion++; preview.value = null; previewError.value = ''; previewBusy.value = false; });
watch(() => form.channel, (channel, previous) => {
    if (previous === 'email' && channel !== 'email') {
        const document = new DOMParser().parseFromString(form.body, 'text/html');
        document.querySelectorAll('p,li,h2,h3,h4,br').forEach(node => node.append(document.createTextNode('\n')));
        form.body = document.body.textContent?.trim() || '';
    } else if (channel === 'email') {
        form.body = form.body ? '<p>' + form.body.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('\n', '</p><p>') + '</p>' : '';
    }
});
async function previewAudience() {
    const version = ++previewVersion; previewBusy.value = true; previewError.value = '';
    try { const response = await axios.post('/admin/erp/messaging/audience-preview', { channel: form.channel, audience_filter: form.audience_filter }); if (version === previewVersion) preview.value = response.data; }
    catch (error) { if (version === previewVersion) previewError.value = Object.values(error.response?.data?.errors || {}).flat().join(' ') || 'Could not load recipients. Try again.'; }
    finally { if (version === previewVersion) previewBusy.value = false; }
}
const date = value => value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
const statuses = { draft: 'Draft', scheduled: 'Scheduled', sending: 'Queued / sending', sent: 'Completed', paused: 'Needs attention', cancelled: 'Canceled' };
</script>
<template>
    <Head title="Messaging" />
    <div class="mx-auto max-w-6xl space-y-7">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-primary-700">Communications</p><h1 class="mt-2 text-3xl font-display font-bold text-surface-950">Reach the right audience</h1><p class="mt-2 text-sm text-surface-600">Send email, WhatsApp and private inbox campaigns to users and CRM contacts.</p></div>
            <Button @click="open = true"><Plus class="mr-2 h-4 w-4" />New campaign</Button>
        </header>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <section v-for="metric in [{ label: 'Campaigns', value: campaigns.total }, { label: 'Sent / inbox delivered', value: stats.total_sent }, { label: 'Confirmed delivered', value: stats.total_delivered }, { label: 'Needs review', value: stats.total_failed }]" :key="metric.label" class="rounded-2xl border border-surface-200 bg-white p-5"><p class="text-xs font-medium text-surface-500">{{ metric.label }}</p><p class="mt-3 text-3xl font-semibold tracking-tight">{{ Number(metric.value || 0).toLocaleString() }}</p></section>
        </div>
        <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white">
            <form @submit.prevent="filter" class="flex flex-wrap items-end gap-3 border-b border-surface-200 p-5">
                <Input v-model="search" label="Find a campaign" placeholder="Search by name" :icon="Search" class="min-w-48 flex-1" />
                <div><label for="campaign-channel" class="mb-1.5 block text-sm font-medium">Channel</label><select id="campaign-channel" v-model="channel" @change="filter" class="min-h-11 rounded-xl border-surface-300 text-sm"><option value="">All channels</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="inbox">Platform inbox</option></select></div>
                <Button type="submit" variant="secondary">Apply filters</Button>
            </form>
            <div v-if="campaigns.data?.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm"><thead class="bg-surface-50 text-xs text-surface-500"><tr><th scope="col" class="px-5 py-3">Campaign</th><th scope="col" class="px-5 py-3">Status</th><th scope="col" class="px-5 py-3">Recipients</th><th scope="col" class="px-5 py-3">Accepted</th><th scope="col" class="px-5 py-3">Created</th><th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody><tr v-for="campaign in campaigns.data" :key="campaign.id" class="border-t border-surface-100 hover:bg-surface-50"><td class="px-5 py-4"><Link :href="`/admin/erp/messaging/${campaign.id}`" class="font-semibold text-surface-900 hover:text-primary-700">{{ campaign.name }}</Link><p class="mt-1 text-xs capitalize text-surface-500">{{ campaign.channel }}</p></td><td class="px-5 py-4"><span class="whitespace-nowrap rounded-full bg-surface-100 px-3 py-1 text-xs font-medium">{{ statuses[campaign.status] || campaign.status }}</span></td><td class="px-5 py-4">{{ campaign.recipient_count || 0 }}</td><td class="px-5 py-4">{{ campaign.sent_count || 0 }}</td><td class="whitespace-nowrap px-5 py-4 text-surface-500">{{ date(campaign.created_at) }}</td><td class="px-5 py-4"><Link :href="`/admin/erp/messaging/${campaign.id}`" :aria-label="`Open ${campaign.name}`" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl hover:bg-primary-50"><ArrowRight class="h-4 w-4" /></Link></td></tr></tbody>
                </table>
            </div>
            <div v-else class="px-6 py-16 text-center"><Mail class="mx-auto h-10 w-10 text-surface-400" /><h2 class="mt-4 font-semibold">{{ search || channel ? 'No matching campaigns' : 'Your first campaign starts here' }}</h2><p class="mt-2 text-sm text-surface-500">{{ search || channel ? 'Adjust your filters to find another campaign.' : 'Create a draft, review its message and choose your audience.' }}</p><Button v-if="!search && !channel" class="mt-5" variant="secondary" @click="open = true">Create a draft</Button></div>
            <nav v-if="campaigns.last_page > 1" aria-label="Campaign pages" class="flex flex-wrap gap-2 border-t border-surface-200 p-4"><template v-for="(page, index) in campaigns.links" :key="index"><Link v-if="page.url" :href="page.url" :aria-current="page.active ? 'page' : undefined" class="rounded-lg border px-3 py-2 text-sm" :class="page.active ? 'border-primary-700 bg-primary-700 text-white' : 'border-surface-200'">{{ index === 0 ? 'Previous' : index === campaigns.links.length - 1 ? 'Next' : page.label }}</Link></template></nav>
        </section>
        <p class="flex items-start gap-2 text-xs leading-5 text-surface-500"><Users class="mt-0.5 h-4 w-4 shrink-0" />Email and WhatsApp campaigns respect channel opt-ins. Platform inbox campaigns respect announcement preferences. Quiet hours and delivery limits apply. Provider acceptance does not confirm inbox delivery.</p>
    </div>
    <Modal :open="open" title="Create a bulk campaign" subtitle="Choose an audience, compose your message and review before sending." size="xl" @close="open = false">
        <form @submit.prevent="save" class="space-y-5">
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl bg-danger-50 p-4 text-sm text-danger-800"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p></div>
            <Input v-model="form.name" label="Campaign name" required :error="form.errors.name" />
            <div><label for="new-channel" class="mb-1.5 block text-sm font-medium">Delivery channel</label><select id="new-channel" v-model="form.channel" class="w-full rounded-xl border-surface-300 text-sm"><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="inbox">Platform inbox</option></select></div>
            <AudiencePicker v-model="form.audience_filter" :options="audienceOptions" />
            <div class="rounded-xl border border-primary-100 bg-primary-50/40 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-sm font-semibold">Review recipients</h3><p class="mt-1 text-xs leading-5 text-surface-600">{{ form.channel === 'inbox' ? 'Active, verified users with announcements enabled. CRM contacts need a linked account.' : 'Active recipients with valid contact details and channel opt-in. CRM consent is managed on each contact.' }}</p></div><Button type="button" variant="secondary" :disabled="previewBusy" @click="previewAudience">{{ previewBusy ? 'Checking…' : 'Preview recipients' }}</Button></div>
                <p v-if="previewError" role="alert" class="mt-3 text-sm text-danger-700">{{ previewError }}</p>
                <div v-if="preview" class="mt-4 space-y-3" aria-live="polite"><div class="flex flex-wrap gap-4 text-xs"><span class="font-semibold text-primary-800">{{ preview.eligible.toLocaleString() }} recipients</span><span>{{ preview.excluded }} excluded by preferences or contact details</span><span>{{ preview.duplicates }} duplicates removed</span></div><p v-if="!preview.eligible" class="text-sm text-surface-600">No eligible recipients. Check the audience filters, contact details and channel preferences.</p><ul v-else class="space-y-1 text-xs text-surface-600"><li v-for="(person, index) in preview.sample" :key="index">{{ person.name }} · {{ form.channel === 'inbox' ? person.source : person.recipient }}</li></ul><p class="text-xs text-surface-500">Preview shows up to five recipients. Eligibility is checked again when the campaign starts and before delivery.</p></div>
            </div>
            <Input v-if="form.channel !== 'whatsapp'" v-model="form.subject" :label="form.channel === 'email' ? 'Email subject' : 'Message title'" required :error="form.errors.subject" />
            <EmailEditor v-if="form.channel === 'email'" v-model="form.body" :error="form.errors.body" :disabled="form.processing" />
            <div v-else><label for="campaign-body" class="mb-1.5 block text-sm font-medium">Message</label><textarea id="campaign-body" v-model="form.body" required rows="7" maxlength="3500" class="w-full rounded-xl border-surface-300 text-sm" placeholder="Write a clear, helpful message…" /><p class="mt-1.5 text-xs text-surface-500">{{ form.body.length }}/3,500 characters · {{ form.channel === 'inbox' ? 'Each person receives a private message in their platform inbox.' : 'Sent through your configured WhatsApp connection.' }}</p></div>
            <Input v-model="form.scheduled_at" type="datetime-local" label="Schedule (optional)" hint="Uses your device’s time zone. Leave empty to save a draft." />
            <div class="flex justify-end gap-3 border-t border-surface-200 pt-4"><Button type="button" variant="secondary" @click="open = false">Cancel</Button><Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : form.scheduled_at ? 'Schedule campaign' : 'Save draft' }}</Button></div>
        </form>
    </Modal>
</template>

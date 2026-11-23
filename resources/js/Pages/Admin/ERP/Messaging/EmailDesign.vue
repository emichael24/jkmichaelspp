<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Trash2, Pencil, Plus, Save, LayoutTemplate } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/UI/Modal.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
import EmailEditor from '@/Components/Messaging/EmailEditor.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ branding: Object, templates: Array, categories: Array });

const categoryLabels = { general: 'General', newsletter: 'Newsletter', announcement: 'Announcement', event: 'Event', follow_up: 'Follow-up', welcome: 'Welcome', reminder: 'Reminder' };

// ── Branding ───────────────────────────────────────────────────────────
const brand = useForm({
    brand_name: props.branding.brand_name,
    accent_color: props.branding.accent_color,
    header_background: props.branding.header_background,
    footer_text: props.branding.footer_text,
    logo: null,
    remove_logo: false,
});
const logoPreview = ref(props.branding.logo_url);
const logoInput = ref(null);
function chooseLogo(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    brand.logo = file; brand.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
}
function removeLogo() { brand.logo = null; brand.remove_logo = true; logoPreview.value = null; if (logoInput.value) logoInput.value.value = ''; }
function saveBranding() {
    brand.post('/admin/erp/email-design/branding', { forceFormData: true, preserveScroll: true, onSuccess: () => { brand.logo = null; brand.remove_logo = false; } });
}

const sample = '<p>Hi {{first_name}},</p><h2>Your headline here</h2><p>This is how campaigns, CRM emails and notifications look with your branding. Links appear in your <a href="https://example.com">brand colour</a>.</p><ul><li>Clear, friendly layout</li><li>Works on mobile and desktop</li></ul><p>The JK Michaels team</p>';
const previewHtml = ref('');
let timer, version = 0;
async function refreshPreview() {
    const current = ++version;
    try {
        const { data } = await axios.post('/admin/erp/email-design/preview', { body: sample, brand: { brand_name: brand.brand_name, accent_color: brand.accent_color, header_background: brand.header_background, footer_text: brand.footer_text } });
        if (current !== version) return;
        // Show a just-chosen (unsaved) logo in the preview too.
        let html = data.html;
        if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
            html = html.replace(/<span style="font-size:20px[^"]*">[^<]*<\/span>|<img [^>]*>/, `<img src="${logoPreview.value}" alt="" height="48" style="display:block;height:48px;width:auto;max-width:240px">`);
        } else if (!logoPreview.value && props.branding.logo_url) {
            html = html.replace(/<img [^>]*>/, `<span style="font-size:20px;font-weight:bold;color:${brand.accent_color}">${brand.brand_name.replace(/[<>&"]/g, '')}</span>`);
        }
        previewHtml.value = html;
    } catch { /* Keep the last preview. */ }
}
watch(() => [brand.brand_name, brand.accent_color, brand.header_background, brand.footer_text, logoPreview.value], () => { clearTimeout(timer); timer = setTimeout(refreshPreview, 350); });
onMounted(refreshPreview);

// ── Templates ──────────────────────────────────────────────────────────
const editing = ref(null);
const templateForm = useForm({ name: '', category: 'general', subject: '', body: '' });
const grouped = computed(() => props.categories.map(category => ({ category, items: props.templates.filter(item => item.category === category) })).filter(group => group.items.length));
function openTemplate(template = null) {
    editing.value = template ? template.id : 'new';
    templateForm.clearErrors();
    templateForm.name = template?.name || ''; templateForm.category = template?.category || 'general';
    templateForm.subject = template?.subject || ''; templateForm.body = template?.body || '';
}
function saveTemplate() {
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value === 'new' ? templateForm.post('/admin/erp/email-templates', options) : templateForm.put(`/admin/erp/email-templates/${editing.value}`, options);
}
function deleteTemplate(template) {
    if (window.confirm(`Delete the “${template.name}” template? Campaigns already sent are not affected.`)) router.delete(`/admin/erp/email-templates/${template.id}`, { preserveScroll: true });
}
const excerpt = html => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
</script>

<template>
    <Head title="Email design" />
    <div class="mx-auto max-w-6xl space-y-8">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link href="/admin/erp/messaging" aria-label="Back to campaigns" class="rounded-lg p-2 hover:bg-surface-100"><ArrowLeft class="h-5 w-5 text-surface-400" /></Link>
                <div><p class="text-xs font-semibold uppercase tracking-widest text-primary-700">Communications</p><h1 class="mt-1 text-3xl font-display font-bold text-surface-950">Email design</h1><p class="mt-1 text-sm text-surface-600">Your logo, colours and footer appear on every campaign, CRM email and notification.</p></div>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <form class="space-y-5 rounded-2xl border border-surface-200 bg-white p-6 shadow-sm" @submit.prevent="saveBranding">
                <h2 class="text-lg font-semibold">Branding</h2>
                <div v-if="Object.keys(brand.errors).length" role="alert" class="rounded-xl bg-danger-50 p-3 text-sm text-danger-800"><p v-for="(error, key) in brand.errors" :key="key">{{ error }}</p></div>
                <div>
                    <p class="mb-2 text-sm font-medium text-surface-700">Logo</p>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="grid h-20 w-48 place-items-center rounded-xl border border-dashed border-surface-300 p-2" :style="{ background: brand.header_background }">
                            <img v-if="logoPreview" :src="logoPreview" alt="Email logo preview" class="max-h-16 max-w-full object-contain" />
                            <span v-else class="text-sm font-bold" :style="{ color: brand.accent_color }">{{ brand.brand_name }}</span>
                        </div>
                        <div class="space-y-2">
                            <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/gif" class="sr-only" @change="chooseLogo" />
                            <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 px-3 py-2 text-xs font-semibold hover:bg-surface-50" @click="logoInput.click()"><ImagePlus class="h-4 w-4" />{{ logoPreview ? 'Replace logo' : 'Upload logo' }}</button>
                            <button v-if="logoPreview" type="button" class="ml-2 inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-danger-700 hover:bg-danger-50" @click="removeLogo"><Trash2 class="h-4 w-4" />Remove</button>
                            <p class="text-xs text-surface-500">PNG or JPG, up to 1 MB. A wide logo about 480×96 px looks best.</p>
                        </div>
                    </div>
                </div>
                <Input v-model="brand.brand_name" label="Sender / brand name" required maxlength="120" hint="Shown when no logo is set, and in the footer." />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="accent" class="mb-1.5 block text-sm font-medium">Brand colour</label><div class="flex items-center gap-2"><input id="accent" v-model="brand.accent_color" type="color" class="h-11 w-14 cursor-pointer rounded-lg border border-surface-200" /><input v-model="brand.accent_color" aria-label="Brand colour hex" maxlength="7" class="min-w-0 flex-1 rounded-xl border border-surface-200 px-3 py-2.5 text-sm uppercase" /></div><p class="mt-1 text-xs text-surface-500">Header line, links and headings.</p></div>
                    <div><label for="header-bg" class="mb-1.5 block text-sm font-medium">Header background</label><div class="flex items-center gap-2"><input id="header-bg" v-model="brand.header_background" type="color" class="h-11 w-14 cursor-pointer rounded-lg border border-surface-200" /><input v-model="brand.header_background" aria-label="Header background hex" maxlength="7" class="min-w-0 flex-1 rounded-xl border border-surface-200 px-3 py-2.5 text-sm uppercase" /></div><p class="mt-1 text-xs text-surface-500">Use a dark colour if your logo is white.</p></div>
                </div>
                <div><label for="footer" class="mb-1.5 block text-sm font-medium">Footer</label><textarea id="footer" v-model="brand.footer_text" rows="4" maxlength="1000" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm" placeholder="JK Michaels Institute&#10;Address · Phone · www.jkmichaelspm.com" /><p class="mt-1 text-xs text-surface-500">Your address and contact details. An unsubscribe / preferences link is always added.</p></div>
                <div class="flex justify-end"><Button type="submit" :disabled="brand.processing"><Save class="mr-2 h-4 w-4" />{{ brand.processing ? 'Saving…' : 'Save branding' }}</Button></div>
            </form>
            <div class="overflow-hidden rounded-2xl border border-surface-200 bg-surface-100 shadow-sm">
                <p class="border-b border-surface-200 bg-white px-5 py-3 text-xs font-semibold uppercase tracking-widest text-surface-500">Live preview</p>
                <iframe :srcdoc="previewHtml" title="Email branding preview" sandbox="" class="block h-[640px] w-full bg-white" />
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="flex items-center gap-2 text-lg font-semibold"><LayoutTemplate class="h-5 w-5 text-primary-600" />Templates</h2><p class="mt-1 text-sm text-surface-600">Reusable emails you can pick when creating a campaign or emailing a contact.</p></div>
                <Button @click="openTemplate()"><Plus class="mr-2 h-4 w-4" />New template</Button>
            </div>
            <div v-if="!templates.length" class="rounded-2xl border border-dashed border-surface-300 bg-white p-10 text-center text-sm text-surface-500">No templates yet. Create your first one.</div>
            <div v-for="group in grouped" :key="group.category" class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-500">{{ categoryLabels[group.category] || group.category }}</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <article v-for="template in group.items" :key="template.id" class="flex flex-col rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
                        <p class="font-semibold text-surface-900">{{ template.name }}</p>
                        <p class="mt-1 text-xs text-surface-500">Subject: {{ template.subject }}</p>
                        <p class="mt-3 flex-1 text-sm leading-6 text-surface-600">{{ excerpt(template.body) }}…</p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-surface-700 hover:bg-surface-100" @click="openTemplate(template)"><Pencil class="h-3.5 w-3.5" />Edit</button>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-danger-700 hover:bg-danger-50" @click="deleteTemplate(template)"><Trash2 class="h-3.5 w-3.5" />Delete</button>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>

    <Modal :open="!!editing" :title="editing === 'new' ? 'New email template' : 'Edit email template'" size="xl" @close="editing = null">
        <form class="space-y-4" @submit.prevent="saveTemplate">
            <div v-if="Object.keys(templateForm.errors).length" role="alert" class="rounded-xl bg-danger-50 p-3 text-sm text-danger-800"><p v-for="(error, key) in templateForm.errors" :key="key">{{ error }}</p></div>
            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <Input v-model="templateForm.name" label="Template name" required maxlength="120" />
                <div><label for="template-category" class="mb-1.5 block text-sm font-medium">Category</label><select id="template-category" v-model="templateForm.category" class="w-full rounded-xl border-surface-300 text-sm"><option v-for="category in categories" :key="category" :value="category">{{ categoryLabels[category] || category }}</option></select></div>
            </div>
            <Input v-model="templateForm.subject" label="Email subject" required maxlength="255" />
            <EmailEditor v-model="templateForm.body" :subject="templateForm.subject" :show-templates="false" :error="templateForm.errors.body" :disabled="templateForm.processing" />
            <div class="flex justify-end gap-3 border-t border-surface-200 pt-4"><Button type="button" variant="secondary" @click="editing = null">Cancel</Button><Button type="submit" :disabled="templateForm.processing">{{ templateForm.processing ? 'Saving…' : 'Save template' }}</Button></div>
        </form>
    </Modal>
</template>

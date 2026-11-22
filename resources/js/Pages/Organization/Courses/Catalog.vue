<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, ShoppingBag } from 'lucide-vue-next';
import WorkspaceShell from '@/Layouts/WorkspaceShell.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Modal from '@/Components/UI/Modal.vue';
import SimplePagination from '@/Components/UI/SimplePagination.vue';
const props = defineProps({ organization: Object, courses: Object, filters: Object, gateways: Array });
const search = ref(props.filters.search || '');
const selected = ref(null);
const form = useForm({ quantity: 1, payment_gateway: props.gateways[0]?.id || '', discount_code: '' });
const money = (value, currency) => `${currency} ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const total = computed(() => Number(selected.value?.price || 0) * Math.max(0, Number(form.quantity) || 0));
const choose = course => { form.reset(); form.clearErrors(); selected.value = course; };
const buy = () => form.post(`/organization/courses/${selected.value.id}/purchase`, { onSuccess: () => { selected.value = null; } });
</script>
<template>
    <Head title="Buy organization course places" />
    <WorkspaceShell workspace="organization">
        <div class="space-y-7">
            <header><Link href="/organization/courses" class="inline-flex items-center gap-2 text-sm text-surface-500"><ArrowLeft class="h-4 w-4" /> Your courses</Link><h1 class="mt-4 text-3xl font-display font-bold">Invest in your team’s learning</h1><p class="mt-2 max-w-2xl text-sm leading-relaxed text-surface-500">Choose a course and the number of people who need it. After payment is verified, assign the places from your organization workspace.</p></header>
            <form @submit.prevent="router.get('/organization/courses/catalog', { search }, { preserveState: true })" class="flex flex-wrap items-end gap-3"><Input v-model="search" label="Find a course" placeholder="Search the catalog" maxlength="100" class="w-full sm:max-w-sm" /><Button type="submit" variant="secondary">Search</Button></form>
            <div v-if="courses.data.length" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"><article v-for="course in courses.data" :key="course.id" class="flex flex-col rounded-2xl border border-surface-200 bg-white p-6 shadow-sm"><div class="flex items-center gap-3 text-primary-700"><BookOpen class="h-6 w-6" /><span class="text-xs capitalize text-surface-500">{{ course.level }} · {{ course.duration_hours || 'Flexible' }} hours</span></div><h2 class="mt-5 text-lg font-semibold">{{ course.title }}</h2><p class="mt-2 flex-1 text-sm leading-relaxed text-surface-500">{{ course.subtitle || 'Build skills with structured lessons and progress tracking.' }}</p><p class="mt-6 text-xl font-bold text-surface-900">{{ money(course.price, course.currency) }}<span class="ml-1 text-xs font-normal text-surface-500">per place</span></p><Button class="mt-4 w-full" :disabled="!course.purchasable" @click="choose(course)"><ShoppingBag class="h-4 w-4" /> {{ course.purchasable ? 'Choose places' : 'Currently unavailable' }}</Button></article></div>
            <p v-else class="rounded-2xl border border-dashed border-surface-300 bg-white p-12 text-center text-surface-600">No published courses match your search.</p><SimplePagination :page="courses" />
        </div>
        <Modal :show="!!selected" title="Buy places for your organization" :subtitle="selected?.title" :closeable="!form.processing" @close="selected = null">
            <form @submit.prevent="buy" class="space-y-5">
                <div class="rounded-xl bg-surface-50 p-4 text-sm"><p class="font-semibold">{{ organization.name }}</p><p class="mt-1 text-surface-500">{{ organization.contact_email }}</p><p class="mt-1 whitespace-pre-line text-surface-500">{{ organization.address }}</p></div>
                <Input v-model="form.quantity" type="number" min="1" max="10000" step="1" label="Number of course places" required :error="form.errors.quantity" />
                <p class="text-sm leading-relaxed text-surface-500">{{ money(selected?.price || 0, selected?.currency) }} per place. Places can be assigned to your active learners. This purchase does not add organization membership seats.</p>
                <Input v-model="form.discount_code" label="Discount code (optional)" maxlength="100" :error="form.errors.discount_code" />
                <fieldset><legend class="mb-2 text-sm font-semibold">Payment method</legend><label v-for="gateway in gateways" :key="gateway.id" class="mb-2 flex items-center gap-3 rounded-xl border border-surface-200 p-3 text-sm"><input v-model="form.payment_gateway" type="radio" name="company-gateway" :value="gateway.id" required /> {{ gateway.name }}</label></fieldset>
                <p v-if="!gateways.length" role="status" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Payment is not configured. Ask the platform administrator to allocate places.</p>
                <div class="flex items-center justify-between border-t border-surface-100 pt-4"><span class="text-sm text-surface-600">Total before discount</span><strong class="text-xl">{{ money(total, selected?.currency) }}</strong></div>
                <p class="text-xs leading-relaxed text-surface-500">Any eligible discount is calculated on the server. Your company order and payment reference are saved before checkout. You can resume or check payment status from Billing.</p>
                <p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-sm text-red-700">{{ error }}</p>
                <div class="flex flex-wrap justify-end gap-3"><Button variant="secondary" :disabled="form.processing" @click="selected = null">Cancel</Button><Button type="submit" :loading="form.processing" :disabled="!form.payment_gateway || !selected?.purchasable">{{ total === 0 ? 'Get course places' : 'Continue to payment' }}</Button></div>
            </form>
        </Modal>
    </WorkspaceShell>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, Check, Search, UserPlus, Users, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';

defineOptions({ layout: AdminLayout });
const props = defineProps({ course: Object, enrollments: Object, counts: Object, filters: Object });
const total = computed(() => Object.values(props.counts || {}).reduce((sum, n) => sum + Number(n), 0));
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const open = ref(false);
const query = ref('');
const results = ref([]);
const searching = ref(false);
const searchError = ref('');
const selected = ref(null);
const form = useForm({ user_id: null, deadline: '' });
let timer;
let pending;
let sequence = 0;

function filterRoster() {
    router.get(`/admin/courses/${props.course.id}/learners`, { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}
function close() {
    if (form.processing) return;
    open.value = false;
    query.value = '';
    selected.value = null;
    results.value = [];
    form.reset();
    form.clearErrors();
    clearTimeout(timer);
    pending?.abort();
}
function enroll() {
    if (!selected.value || selected.value.enrollment_status) return;
    form.user_id = selected.value.id;
    form.post(`/admin/courses/${props.course.id}/enrollments`, {
        preserveScroll: true,
        onSuccess: () => { open.value = false; query.value = ''; selected.value = null; results.value = []; form.reset(); },
    });
}
watch(query, value => {
    clearTimeout(timer);
    pending?.abort();
    const attempt = ++sequence;
    selected.value = null;
    results.value = [];
    searchError.value = '';
    form.clearErrors('user_id');
    searching.value = value.trim().length >= 2;
    if (!searching.value) return;
    timer = setTimeout(async () => {
        pending = new AbortController();
        try {
            const response = await axios.get(`/admin/courses/${props.course.id}/learners/search`, { params: { search: value.trim() }, signal: pending.signal, timeout: 20000 });
            if (attempt === sequence) results.value = response.data.learners;
        } catch (error) {
            if (attempt === sequence && error.code !== 'ERR_CANCELED') searchError.value = 'We could not search learners. Check your connection and try another search.';
        } finally {
            if (attempt === sequence) searching.value = false;
        }
    }, 250);
});
onBeforeUnmount(() => { clearTimeout(timer); pending?.abort(); });
const label = value => ({ active: 'Active', completed: 'Completed', expired: 'Expired', suspended: 'Suspended' }[value] || value);
const variant = value => ({ active: 'success', completed: 'primary', expired: 'neutral', suspended: 'warning' }[value] || 'neutral');
const date = value => value ? new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <Head :title="`Learners: ${course.title}`" />
    <div class="mx-auto max-w-7xl space-y-6">
        <Link :href="`/admin/courses/${course.id}/edit`" class="inline-flex items-center gap-2 text-sm font-medium text-surface-500 hover:text-primary-700"><ArrowLeft class="h-4 w-4" /> Back to course</Link>
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-primary-700">Course workspace</p>
                <h1 class="mt-2 text-3xl font-bold text-surface-900">Learners</h1>
                <p class="mt-1 text-surface-600">{{ course.title }}</p>
            </div>
            <Button :disabled="!!course.superseded_at" @click="open = true"><UserPlus class="h-4 w-4" /> Enroll learner</Button>
        </header>

        <p v-if="course.superseded_at" role="status" class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800">This course has been replaced. Add new learners to the current course version.</p>
        <p v-else-if="!course.is_published" role="status" class="rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm text-primary-800">This course is a draft. You can enroll learners now; they can access its lessons after it is published.</p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="metric in [{ name: 'Total enrolled', value: total }, { name: 'Active learners', value: counts.active || 0 }, { name: 'Completed', value: counts.completed || 0 }, { name: 'Suspended or expired', value: Number(counts.suspended || 0) + Number(counts.expired || 0) }]" :key="metric.name" class="rounded-2xl border border-surface-200 bg-white p-5">
                <p class="text-sm text-surface-500">{{ metric.name }}</p><p class="mt-2 text-3xl font-semibold text-surface-900">{{ metric.value }}</p>
            </div>
        </div>

        <section aria-label="Course learners" class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
            <form class="flex flex-col gap-3 border-b border-surface-100 p-5 sm:flex-row sm:items-end" @submit.prevent="filterRoster">
                <div class="flex-1"><Input v-model="search" label="Find an enrolled learner" placeholder="Search name or email" maxlength="100" /></div>
                <div><label for="enrollment-status" class="mb-1.5 block text-sm font-medium text-surface-700">Status</label><select id="enrollment-status" v-model="status" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-2.5 text-sm sm:w-44"><option value="">All statuses</option><option v-for="value in ['active', 'completed', 'expired', 'suspended']" :key="value" :value="value">{{ label(value) }}</option></select></div>
                <Button type="submit" variant="secondary"><Search class="h-4 w-4" /> Search</Button>
                <Button v-if="search || status" variant="ghost" aria-label="Clear filters" @click="search = ''; status = ''; filterRoster()"><X class="h-4 w-4" /></Button>
            </form>
            <div v-if="enrollments.data.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Enrolled learners and course progress</caption>
                    <thead class="bg-surface-50 text-surface-600"><tr><th scope="col" class="px-5 py-3 font-medium">Learner</th><th scope="col" class="px-5 py-3 font-medium">Status</th><th scope="col" class="px-5 py-3 font-medium">Progress</th><th scope="col" class="px-5 py-3 font-medium whitespace-nowrap">Enrolled</th><th scope="col" class="px-5 py-3 font-medium whitespace-nowrap">Completion deadline</th></tr></thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="enrollment in enrollments.data" :key="enrollment.id" class="hover:bg-surface-50/60">
                            <td class="px-5 py-4"><p class="font-semibold text-surface-900">{{ enrollment.user?.full_name || 'Deleted account' }}</p><p class="mt-1 text-xs text-surface-500">{{ enrollment.user?.email }}</p></td>
                            <td class="px-5 py-4"><Badge :variant="variant(enrollment.status)">{{ label(enrollment.status) }}</Badge></td>
                            <td class="min-w-32 px-5 py-4"><span>{{ enrollment.progress_pct }}%</span><div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-100"><div class="h-full rounded-full bg-primary-600" :style="{ width: `${enrollment.progress_pct}%` }" /></div></td>
                            <td class="whitespace-nowrap px-5 py-4 text-surface-600">{{ date(enrollment.enrolled_at) }}<p class="mt-1 text-xs text-surface-400">{{ enrollment.enrolled_by === 'admin' ? 'Added by admin' : enrollment.enrolled_by === 'self' ? 'Self-enrolled' : 'System enrollment' }}</p></td>
                            <td class="whitespace-nowrap px-5 py-4 text-surface-600">{{ date(enrollment.deadline) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-6 py-14 text-center"><Users class="mx-auto h-10 w-10 text-surface-300" /><h2 class="mt-4 font-semibold text-surface-800">{{ total ? 'No learners match your filters' : 'Bring your learners into the course' }}</h2><p class="mx-auto mt-2 max-w-md text-sm text-surface-500">{{ total ? 'Try another name, email or status.' : 'Enroll an existing learner to give them a place in this course. Their progress will appear here.' }}</p><Button v-if="!total && !course.superseded_at" class="mt-5" variant="secondary" @click="open = true">Enroll the first learner</Button></div>
            <footer v-if="enrollments.total" class="flex flex-wrap items-center justify-between gap-3 border-t border-surface-100 px-5 py-4 text-sm text-surface-500"><p>{{ enrollments.from }}–{{ enrollments.to }} of {{ enrollments.total }} learners</p><nav aria-label="Learner list pages" class="flex gap-3"><Link v-if="enrollments.prev_page_url" :href="enrollments.prev_page_url" class="rounded-lg border border-surface-200 px-3 py-2 hover:bg-surface-50" preserve-scroll>Previous</Link><Link v-if="enrollments.next_page_url" :href="enrollments.next_page_url" class="rounded-lg border border-surface-200 px-3 py-2 hover:bg-surface-50" preserve-scroll>Next</Link></nav></footer>
        </section>

        <Modal :show="open" title="Enroll a learner" :subtitle="course.title" size="lg" :closeable="!form.processing" @close="close">
            <form class="space-y-5" @submit.prevent="enroll">
                <Input v-model="query" label="Find a learner" placeholder="Type a name or email address" maxlength="100" autocomplete="off" :error="form.errors.user_id" />
                <p class="-mt-3 text-xs text-surface-500">Search active learner accounts. Enter at least 2 characters; up to 20 matches are shown.</p>
                <div aria-live="polite">
                    <p v-if="searching" class="py-6 text-center text-sm text-surface-500">Searching learners…</p>
                    <p v-else-if="searchError" role="alert" class="rounded-xl bg-danger-50 p-4 text-sm text-danger-700">{{ searchError }}</p>
                    <p v-else-if="query.trim().length >= 2 && !results.length" class="rounded-xl bg-surface-50 p-4 text-sm text-surface-600">No active learners found. Try another search, or create a learner in <Link href="/admin/users/create" class="font-semibold text-primary-700 underline">Users &amp; access</Link>.</p>
                </div>
                <fieldset v-if="results.length" class="max-h-64 space-y-2 overflow-y-auto"><legend class="sr-only">Select a learner</legend>
                    <label v-for="learner in results" :key="learner.id" :class="['flex items-start gap-3 rounded-xl border p-3 transition-colors', learner.enrollment_status ? 'cursor-not-allowed border-surface-100 bg-surface-50 opacity-65' : selected?.id === learner.id ? 'border-primary-500 bg-primary-50' : 'cursor-pointer border-surface-200 hover:bg-surface-50']">
                        <input v-model="selected" type="radio" name="learner" :value="learner" :disabled="!!learner.enrollment_status" class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500" />
                        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-surface-900">{{ learner.name }}</span><span class="block break-all text-xs text-surface-500">{{ learner.email }}</span><span v-if="learner.organization" class="mt-1 block text-xs text-surface-500">{{ learner.organization }}</span><span v-if="learner.enrollment_status" class="mt-1 block text-xs font-medium text-surface-600">Already enrolled · {{ label(learner.enrollment_status) }}</span></span>
                        <Check v-if="selected?.id === learner.id" class="h-4 w-4 text-primary-700" />
                    </label>
                </fieldset>
                <div v-if="selected" class="space-y-4 border-t border-surface-100 pt-4"><Input v-model="form.deadline" type="date" label="Completion deadline (optional)" :error="form.errors.deadline" /><p class="rounded-xl bg-surface-50 p-3 text-sm text-surface-600">Add <strong>{{ selected.name }}</strong> to <strong>{{ course.title }}</strong>. {{ course.is_published ? 'They can start learning immediately.' : 'They can start learning when the course is published.' }}<span v-if="Number(course.price) > 0" class="mt-1 block">This grants access without checkout. No payment is collected.</span></p></div>
                <div class="flex justify-end gap-3 border-t border-surface-100 pt-5"><Button variant="secondary" :disabled="form.processing" @click="close">Cancel</Button><Button type="submit" :loading="form.processing" :disabled="!selected || !!selected.enrollment_status"><UserPlus class="h-4 w-4" /> Enroll learner</Button></div>
            </form>
        </Modal>
    </div>
</template>

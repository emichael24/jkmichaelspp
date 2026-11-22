<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Trash2, RefreshCw, ShieldCheck, ArrowUpRight } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { getDownloadedLessons, getPendingProgress, removeOfflineLesson, syncOfflineProgress, discardPendingProgress } from '@/lib/offlineStore';

defineOptions({ layout: AppLayout });
const lessons = ref([]);
const pending = ref(0);
const busy = ref(false);
const message = ref('');
const online = ref(navigator.onLine);
async function refresh() {
    try { lessons.value = await getDownloadedLessons(); pending.value = (await getPendingProgress()).length; }
    catch (error) { message.value = error.message; }
}
async function sync() {
    busy.value = true;
    try {
        const result = await syncOfflineProgress();
        message.value = `${result.accepted} completion events confirmed by the server. ${result.blocked ? `${result.blocked} need renewed lesson access; reopen those lessons online.` : 'Saved reading permissions checked.'}`;
    } catch (error) { message.value = error.response?.data?.message || 'Could not connect. Pending progress remains on this device until its permission expires.'; }
    finally { busy.value = false; await refresh(); }
}
async function remove(id) { try { await removeOfflineLesson(id); await refresh(); } catch (error) { message.value = error.message; } }
async function discard() { await discardPendingProgress(); await refresh(); message.value = 'Pending reading progress removed from this device.'; }
function network() { online.value = navigator.onLine; }
onMounted(() => { refresh(); window.addEventListener('online', network); window.addEventListener('offline', network); window.addEventListener('offline-data-changed', refresh); if (navigator.onLine) sync(); });
onUnmounted(() => { window.removeEventListener('online', network); window.removeEventListener('offline', network); window.removeEventListener('offline-data-changed', refresh); });
const date = value => new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head title="Saved readings" />
    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-primary-700">Your learning library</p><h1 class="mt-2 text-3xl font-bold">Saved readings</h1><p class="mt-2 max-w-2xl text-sm text-surface-600">Keep reading when your connection drops, then confirm your progress when you reconnect.</p></div>
            <button @click="sync" :disabled="busy || !online" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-surface-300 bg-white px-4 text-sm font-semibold disabled:opacity-50"><RefreshCw :class="['h-4 w-4', busy && 'animate-spin']" />{{ busy ? 'Checking…' : 'Sync progress' }}</button>
        </header>
        <p v-if="message" role="status" class="rounded-xl border border-surface-200 bg-white p-4 text-sm text-surface-700">{{ message }}</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="stat in [{ label: 'Readings in this tab', value: lessons.length }, { label: 'Awaiting confirmation', value: pending }, { label: 'Connection', value: online ? 'Online' : 'Offline' }]" :key="stat.label" class="rounded-2xl border border-surface-200 bg-white p-5"><p class="text-xs font-medium text-surface-500">{{ stat.label }}</p><p class="mt-2 text-2xl font-bold">{{ stat.value }}</p></div>
        </div>
        <section class="flex items-start gap-3 rounded-2xl border border-primary-100 bg-primary-50 p-5 text-sm text-primary-900">
            <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0" /><div><h2 class="font-semibold">Private to this account and tab</h2><p class="mt-1 leading-6">Readings are encrypted on this device and expire after 24 hours. Signing out, switching accounts or closing this tab removes access. Save up to 50 readings (20 MB total). Videos, audio, documents and assessments need a connection. Reconnect within 24 hours to confirm offline completion.</p></div>
        </section>
        <section v-if="lessons.length" aria-label="Saved lessons" class="overflow-hidden rounded-2xl border border-surface-200 bg-white">
            <div v-for="lesson in lessons" :key="lesson.lessonId" class="flex flex-wrap items-center justify-between gap-4 border-b border-surface-100 p-5 last:border-b-0">
                <div class="flex min-w-0 items-center gap-4"><div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-surface-100"><BookOpen class="h-5 w-5 text-surface-600" /></div><div><h2 class="font-semibold">{{ lesson.title }}</h2><p class="mt-1 text-xs text-surface-500">{{ lesson.course }} · Available until {{ date(lesson.expiresAt) }}</p></div></div>
                <div class="flex items-center gap-2"><a href="/offline" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-surface-200 px-4 text-sm font-semibold">Read offline<ArrowUpRight class="h-4 w-4" /></a><button @click="remove(lesson.lessonId)" :aria-label="`Remove ${lesson.title} from this device`" class="grid h-11 w-11 place-items-center rounded-lg text-surface-500 hover:bg-rose-50 hover:text-rose-700"><Trash2 class="h-4 w-4" /></button></div>
            </div>
        </section>
        <section v-else class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-12 text-center"><BookOpen class="mx-auto h-9 w-9 text-surface-400" /><h2 class="mt-4 text-lg font-semibold">Build your reading library</h2><p class="mx-auto mt-2 max-w-md text-sm text-surface-500">Open an enrolled lesson and choose “Save reading offline” where downloads are enabled.</p><Link href="/my-courses" class="mt-5 inline-flex rounded-xl bg-surface-900 px-5 py-3 text-sm font-semibold text-white">Browse my courses</Link></section>
        <details v-if="pending" class="rounded-xl border border-surface-200 bg-white p-4 text-sm"><summary class="cursor-pointer font-medium">Manage pending progress</summary><p class="my-3 text-surface-600">Remove pending events only if you no longer need to sync them. Your already confirmed server progress is preserved.</p><button @click="discard" class="rounded-lg border border-rose-200 px-4 py-2 text-rose-700">Discard {{ pending }} pending events</button></details>
    </div>
</template>

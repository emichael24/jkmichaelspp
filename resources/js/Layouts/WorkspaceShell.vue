<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Dialog, DialogPanel, DialogTitle, Menu as WorkspaceMenu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';
import { Bell, Menu, X, LogOut, ChevronRight, CheckCircle2, AlertCircle, ArrowUpRight, ChevronsUpDown, Check, PanelsTopLeft } from 'lucide-vue-next';
import WorkspaceNavigation from './Partials/WorkspaceNavigation.vue';
import { workspaces, personalLinks } from '@/navigation/workspaces';
import OfflineBanner from '@/Components/OfflineBanner.vue';
import { clearOfflineData } from '@/lib/offlineStore';
import { unsubscribeFromPush } from '@/pwa';

const props = defineProps({ workspace: { type: String, default: 'learner' } });
const page = usePage();
const mobileOpen = ref(false);
const errorsRef = ref(null);
const user = computed(() => page.props.auth?.user || {});
const config = computed(() => workspaces[props.workspace] || workspaces.learner);
const choices = computed(() => Object.entries(workspaces).filter(([key, value]) => key === 'learner' || (user.value.roles || []).includes(value.role)));
const allItems = computed(() => [...config.value.groups.flatMap(group => group.items), ...personalLinks]);
const currentItem = computed(() => {
    const path = page.url.split('?')[0];
    return allItems.value.filter(item => path === item.href || (item.href !== config.value.home && path.startsWith(item.href + '/')))
        .sort((a, b) => b.href.length - a.href.length)[0];
});
const primaryItems = computed(() => config.value.groups[0].items.slice(0, 3));
const errors = computed(() => [...new Set(Object.values(page.props.errors || {}).flat().filter(value => typeof value === 'string'))]);
const notices = computed(() => Object.entries(page.props.flash || {}).filter(([key, value]) => ['success', 'error', 'warning', 'info'].includes(key) && typeof value === 'string' && value));
watch(() => page.url, () => { mobileOpen.value = false; });
watch(errors, async value => { if (value.length) { await nextTick(); errorsRef.value?.focus(); } });
async function signOut() {
    await unsubscribeFromPush().catch(() => {});
    await clearOfflineData().catch(() => {});
    router.post('/logout');
}
</script>

<template>
    <div class="workspace-shell min-h-screen bg-surface-50 text-surface-900">
        <a href="#workspace-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-5 focus:py-3 focus:text-surface-900 focus:shadow-lg">Skip to main content</a>
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-surface-900 lg:flex">
            <Link :href="config.home" class="flex items-center gap-3 px-6 py-6">
                <img src="/images/logo-icon.png" alt="" class="h-9 w-9 object-contain" />
                <div><p class="font-display text-base font-bold tracking-tight text-white">JK Michaels</p><p class="mt-0.5 text-[11px] tracking-wide text-surface-400">Learn. Apply. Advance.</p></div>
            </Link>
            <WorkspaceNavigation :workspace="config" :active-href="currentItem?.href" />
            <div class="border-t border-surface-800 p-4">
                <p class="mb-3 px-2 text-xs text-surface-400">{{ config.label }}</p>
                <button type="button" @click="signOut" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm text-surface-300 hover:bg-surface-800 hover:text-white"><LogOut class="h-4 w-4" aria-hidden="true" /> Sign out</button>
            </div>
        </aside>

        <div class="min-w-0 lg:pl-64">
            <header class="sticky top-0 z-30 flex h-[76px] items-center justify-between gap-3 border-b border-surface-200 bg-white/95 px-4 backdrop-blur-sm sm:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" aria-label="Open workspace navigation" :aria-expanded="mobileOpen" @click="mobileOpen = true" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-surface-200 lg:hidden"><Menu class="h-5 w-5" /></button>
                    <div class="min-w-0">
                        <WorkspaceMenu v-if="choices.length > 1" as="div" class="relative">
                            <MenuButton class="flex min-h-11 max-w-full items-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-3 py-1.5 text-left text-primary-800 shadow-sm transition hover:border-primary-400 hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2" :aria-label="`Switch workspace, current: ${config.label}`">
                                <PanelsTopLeft class="hidden h-5 w-5 shrink-0 sm:block" aria-hidden="true" />
                                <span class="min-w-0"><span class="block text-[10px] font-semibold uppercase tracking-wide">Switch workspace</span><span class="block truncate text-xs font-semibold sm:text-sm">{{ config.label }}</span></span>
                                <ChevronsUpDown class="h-4 w-4 shrink-0" aria-hidden="true" />
                            </MenuButton>
                            <MenuItems class="absolute left-0 z-50 mt-2 w-64 max-w-[calc(100vw-6rem)] rounded-2xl border border-surface-200 bg-white p-2 shadow-xl focus:outline-none">
                                <p class="px-3 pb-2 pt-1 text-xs font-medium text-surface-500">Choose your workspace</p>
                                <MenuItem v-for="[key, value] in choices" :key="key" v-slot="{ active }">
                                    <Link :href="value.home" :aria-current="key === workspace ? 'true' : undefined" :class="['flex min-h-12 items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm', active ? 'bg-primary-50 text-primary-800' : 'text-surface-700', key === workspace ? 'font-semibold' : 'font-medium']">
                                        <span>{{ value.label }}<span v-if="key === workspace" class="block text-xs font-normal text-surface-500">Current workspace</span></span><Check v-if="key === workspace" class="h-4 w-4 shrink-0 text-primary-700" aria-hidden="true" />
                                    </Link>
                                </MenuItem>
                            </MenuItems>
                        </WorkspaceMenu>
                        <p v-else class="text-xs text-surface-500">{{ config.label }}</p>
                        <p class="truncate text-xs font-medium text-surface-600">{{ currentItem?.label || 'JK Michaels' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                    <Link href="/notifications" class="relative grid h-11 w-11 place-items-center rounded-xl text-surface-600 hover:bg-surface-100" :aria-label="`Notifications${page.props.notificationCount ? `, ${page.props.notificationCount} unread` : ''}`">
                        <Bell class="h-5 w-5" aria-hidden="true" /><span v-if="page.props.notificationCount" class="absolute right-1 top-1 grid min-w-4 h-4 place-items-center rounded-full bg-primary-700 px-1 text-[9px] font-bold text-white">{{ page.props.notificationCount > 99 ? '99+' : page.props.notificationCount }}</span>
                    </Link>
                    <div class="hidden h-8 w-px bg-surface-200 sm:block" />
                    <Link href="/profile/edit" class="flex min-h-11 items-center gap-2.5 rounded-xl p-1 hover:bg-surface-50" aria-label="Edit your profile">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary-50 text-sm font-semibold text-primary-800">{{ user.initials || 'JK' }}</span>
                        <span class="hidden max-w-36 truncate text-sm font-medium sm:block">{{ user.first_name || user.name || 'My profile' }}</span>
                    </Link>
                </div>
            </header>
            <OfflineBanner />
            <div v-if="page.props.paymentMode === 'test'" role="status" class="border-b border-warning-200 bg-warning-50 px-4 py-2 text-center text-sm text-warning-900">Paystack test-payment mode · Paystack checkout uses sandbox transactions.</div>
            <main id="workspace-content" tabindex="-1" class="mx-auto w-full max-w-[1600px] min-w-0 px-4 py-6 pb-28 focus:outline-none sm:px-7 sm:pt-8 sm:pb-28 lg:pb-12">
                <div v-if="notices.length" class="mb-6 space-y-3" aria-live="polite">
                    <div v-for="[type, message] in notices" :key="type" :role="type === 'error' ? 'alert' : 'status'" :class="['flex items-start gap-3 rounded-xl border px-4 py-3 text-sm', type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-surface-200 bg-white text-surface-700']"><CheckCircle2 v-if="type === 'success'" class="mt-0.5 h-4 w-4 shrink-0" /><AlertCircle v-else class="mt-0.5 h-4 w-4 shrink-0" /><span>{{ message }}</span></div>
                </div>
                <div v-if="errors.length" ref="errorsRef" tabindex="-1" role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                    <p class="font-semibold">Please check the following before continuing</p>
                    <ul class="mt-2 list-inside list-disc space-y-1"><li v-for="error in errors" :key="error">{{ error }}</li></ul>
                </div>
                <slot />
            </main>
        </div>

        <nav aria-label="Quick navigation" class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-around border-t border-surface-200 bg-white px-2 pb-[env(safe-area-inset-bottom)] lg:hidden">
            <Link v-for="item in primaryItems" :key="item.href" :href="item.href" :aria-current="item.href === currentItem?.href ? 'page' : undefined" :class="['flex min-h-16 min-w-16 flex-1 flex-col items-center justify-center gap-1 px-1 text-[10px] font-medium', item.href === currentItem?.href ? 'text-primary-800' : 'text-surface-500']"><component :is="item.icon" class="h-5 w-5" aria-hidden="true" /><span class="max-w-24 truncate">{{ item.label }}</span></Link>
            <button type="button" @click="mobileOpen = true" class="flex min-h-16 min-w-16 flex-1 flex-col items-center justify-center gap-1 text-[10px] font-medium text-surface-500"><Menu class="h-5 w-5" aria-hidden="true" /> All pages</button>
        </nav>

        <Dialog :open="mobileOpen" @close="mobileOpen = false" class="relative z-50 lg:hidden">
            <div class="fixed inset-0 bg-surface-950/60 backdrop-blur-sm" aria-hidden="true" />
            <DialogPanel class="fixed inset-y-0 left-0 flex w-[min(88vw,340px)] flex-col bg-surface-900 shadow-xl">
                <div class="flex items-center justify-between px-6 py-5"><div><DialogTitle class="font-display font-bold text-white">JK Michaels</DialogTitle><p class="mt-1 text-xs text-surface-400">{{ config.label }}</p></div><button type="button" @click="mobileOpen = false" aria-label="Close workspace navigation" class="grid h-11 w-11 place-items-center rounded-lg text-surface-300 hover:bg-surface-800"><X class="h-5 w-5" /></button></div>
                <WorkspaceNavigation :workspace="config" :active-href="currentItem?.href" mobile @navigate="mobileOpen = false" />
                <button type="button" @click="signOut" class="m-4 flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-surface-300 hover:bg-surface-800"><LogOut class="h-4 w-4" /> Sign out</button>
            </DialogPanel>
        </Dialog>
        <PwaInstallPrompt />
    </div>
</template>

<template>
    <div class="min-h-screen bg-surface-50">
        <!-- Desktop sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex flex-col bg-white border-r border-surface-200/60',
                'transition-all duration-300 ease-in-out',
                'hidden lg:flex',
                collapsed ? 'w-[72px]' : 'w-64',
            ]"
        >
            <!-- Logo -->
            <div :class="['flex items-center gap-3 px-4 h-16 border-b border-surface-100 shrink-0', collapsed ? 'justify-center' : '']">
                <img src="/images/logo-icon.png" alt="JK Michaels" class="w-9 h-9 shrink-0 object-contain" />
                <transition name="fade">
                    <div v-if="!collapsed" class="min-w-0">
                        <p class="font-display font-bold text-surface-900 text-sm leading-tight truncate">JK Michaels</p>
                        <p class="text-[10px] text-primary-600 font-semibold uppercase tracking-wider">Sponsor Portal</p>
                    </div>
                </transition>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <SidebarItem
                    v-for="item in mainNavItems"
                    :key="item.label"
                    :icon="item.icon"
                    :label="item.label"
                    :href="item.href"
                    :active="item.active"
                    :badge="item.badge"
                    :collapsed="collapsed"
                />

                <div class="pt-4 mt-4 border-t border-surface-100">
                    <p v-if="!collapsed" class="px-3 mb-2 text-[10px] font-semibold text-surface-400 uppercase tracking-wider">
                        Quick Access
                    </p>
                    <SidebarItem
                        v-for="item in quickNavItems"
                        :key="item.label"
                        :icon="item.icon"
                        :label="item.label"
                        :href="item.href"
                        :active="item.active"
                        :collapsed="collapsed"
                    />
                </div>
            </nav>

            <!-- Logout -->
            <button
                @click="handleLogout"
                :class="[
                    'flex items-center gap-2 mx-3 mb-2 px-3 py-2 rounded-lg text-surface-400 hover:text-danger-600 hover:bg-danger-50 transition-colors text-sm',
                    collapsed ? 'justify-center' : '',
                ]"
            >
                <LogOut class="w-4 h-4 shrink-0" />
                <span v-if="!collapsed">Log Out</span>
            </button>

            <!-- Collapse toggle -->
            <button
                @click="collapsed = !collapsed"
                class="hidden lg:flex items-center justify-center h-10 border-t border-surface-100 text-surface-400 hover:text-surface-600 hover:bg-surface-50 transition-colors"
            >
                <ChevronLeft v-if="!collapsed" class="w-4 h-4" />
                <ChevronRight v-else class="w-4 h-4" />
            </button>
        </aside>

        <!-- Mobile top bar -->
        <header class="fixed top-0 left-0 right-0 z-30 lg:hidden bg-white/80 backdrop-blur-xl border-b border-surface-200/60 h-14">
            <div class="flex items-center justify-between h-full px-4">
                <div class="flex items-center gap-3">
                    <img src="/images/logo-icon.png" alt="JK Michaels" class="w-8 h-8 object-contain" />
                    <div>
                        <span class="font-display font-bold text-surface-900 text-sm">JK Michaels</span>
                        <span class="ml-1 text-[10px] text-primary-600 font-semibold uppercase">Sponsor</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button class="relative p-2 rounded-lg hover:bg-surface-100 transition-colors">
                        <Bell class="w-5 h-5 text-surface-500" />
                    </button>
                    <button class="w-8 h-8 rounded-full bg-accent-100 flex items-center justify-center text-accent-700 font-semibold text-xs">
                        {{ userInitials }}
                    </button>
                </div>
            </div>
        </header>

        <!-- Main content -->
        <main
            :class="[
                'transition-all duration-300',
                collapsed ? 'lg:pl-[72px]' : 'lg:pl-64',
                'pt-14 lg:pt-0',
                'pb-20 lg:pb-0',
            ]"
        >
            <!-- Desktop top bar -->
            <div class="hidden lg:flex items-center justify-between h-16 px-6 border-b border-surface-100 bg-white/60 backdrop-blur-sm sticky top-0 z-20">
                <div>
                    <h1 v-if="$page.props.pageTitle" class="text-lg font-semibold text-surface-900">
                        {{ $page.props.pageTitle }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <button class="relative p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                        <Bell class="w-5 h-5" />
                    </button>
                    <button class="flex items-center gap-2.5 rounded-xl hover:bg-surface-50 p-1.5 pr-3 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-accent-500 to-primary-500 flex items-center justify-center text-white font-semibold text-xs">
                            {{ userInitials }}
                        </div>
                        <div class="text-left hidden xl:block">
                            <p class="text-sm font-medium text-surface-900 leading-tight">{{ user.name || 'Sponsor' }}</p>
                            <p class="text-[11px] text-accent-600">Sponsor</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Page content -->
            <div class="p-4 sm:p-6 lg:p-8">
                <slot />
            </div>
        </main>

        <!-- Mobile bottom navigation -->
        <nav class="fixed bottom-0 left-0 right-0 z-30 lg:hidden bg-white/80 backdrop-blur-xl border-t border-surface-200/60">
            <div class="flex items-center justify-around h-16 px-2">
                <MobileNavItem
                    v-for="item in mobileNavItems"
                    :key="item.label"
                    :icon="item.icon"
                    :label="item.label"
                    :href="item.href"
                    :active="item.active"
                    :badge="item.badge"
                />
                <button
                    @click="moreOpen = true"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 rounded-xl transition-all duration-200 relative flex-1 py-1',
                        moreOpen ? 'text-primary-600' : 'text-surface-400 active:text-surface-600',
                    ]"
                >
                    <MoreHorizontal class="w-5 h-5" />
                    <span class="text-[10px] font-semibold">More</span>
                </button>
            </div>
            <div class="h-[env(safe-area-inset-bottom)]"></div>
        </nav>

        <BottomNavMore :open="moreOpen" :items="moreMenuItems" @close="moreOpen = false" />

        <OfflineBanner />

        <!-- PWA Install Prompt -->
        <PwaInstallPrompt />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    Users,
    BarChart3,
    FileText,
    ArrowLeft,
    Bell,
    ChevronLeft,
    ChevronRight,
    User,
    LogOut,
    MoreHorizontal,
    Settings,
} from 'lucide-vue-next';
import SidebarItem from './Partials/SidebarItem.vue';
import MobileNavItem from './Partials/MobileNavItem.vue';
import BottomNavMore from './Partials/BottomNavMore.vue';
import OfflineBanner from '@/Components/OfflineBanner.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const collapsed = ref(false);
const moreOpen = ref(false);

const userInitials = computed(() => {
    if (user.value.initials) return user.value.initials;
    const name = user.value.name || 'S';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});

const currentUrl = computed(() => page.url);

const mainNavItems = computed(() => [
    { icon: ClipboardCheck, label: 'Review Queue', href: '/sponsor/review-queue', active: currentUrl.value.startsWith('/sponsor/review-queue') || currentUrl.value.startsWith('/sponsor/submissions') },
    { icon: Users, label: 'My Interns', href: '/sponsor/interns', active: currentUrl.value.startsWith('/sponsor/interns') },
    { icon: FileText, label: 'Scorecards', href: '/sponsor/scorecards', active: currentUrl.value.startsWith('/sponsor/scorecards') },
]);

const quickNavItems = computed(() => [
    { icon: ArrowLeft, label: 'Learner View', href: '/dashboard', active: false },
    { icon: User, label: 'Profile', href: '/profile', active: currentUrl.value.startsWith('/profile') },
]);

const mobileNavItems = computed(() => [
    { icon: ClipboardCheck, label: 'Reviews', href: '/sponsor/review-queue', active: currentUrl.value.startsWith('/sponsor/review') || currentUrl.value.startsWith('/sponsor/submissions') },
    { icon: Users, label: 'Interns', href: '/sponsor/interns', active: currentUrl.value.startsWith('/sponsor/interns') },
    { icon: ArrowLeft, label: 'Learner', href: '/dashboard', active: false },
]);

const moreMenuItems = computed(() => [
    { icon: FileText, label: 'Scorecards', href: '/sponsor/scorecards', active: currentUrl.value.startsWith('/sponsor/scorecards') },
    { icon: User, label: 'Profile', href: '/profile', active: currentUrl.value.startsWith('/profile') },
    { icon: Settings, label: 'Settings', href: '/settings', active: currentUrl.value.startsWith('/settings') },
]);

const handleLogout = () => {
    router.post('/logout');
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>

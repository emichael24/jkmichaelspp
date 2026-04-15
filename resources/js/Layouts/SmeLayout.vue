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
                        <p class="text-[10px] text-primary-600 font-semibold uppercase tracking-wider">SME Console</p>
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
                        Analytics
                    </p>
                    <SidebarItem
                        v-for="item in analyticsNavItems"
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
        <div class="lg:hidden fixed top-0 left-0 right-0 z-50 h-14 bg-white border-b border-surface-200/60 flex items-center px-4">
            <button @click="mobileOpen = true" class="p-2 -ml-2 text-surface-600">
                <Menu class="w-5 h-5" />
            </button>
            <div class="ml-3 flex items-center gap-2">
                <img src="/images/logo-icon.png" alt="JK Michaels" class="w-7 h-7 object-contain" />
                <p class="font-display font-bold text-surface-900 text-sm">SME Console</p>
            </div>
        </div>

        <!-- Mobile sidebar overlay -->
        <Teleport to="body">
            <transition name="fade">
                <div v-if="mobileOpen" class="lg:hidden fixed inset-0 bg-black/40 z-50" @click="mobileOpen = false" />
            </transition>
            <transition name="slide-left">
                <aside v-if="mobileOpen" class="lg:hidden fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-surface-200/60 flex flex-col">
                    <div class="flex items-center justify-between px-4 h-14 border-b border-surface-100 shrink-0">
                        <div class="flex items-center gap-2">
                            <img src="/images/logo-icon.png" alt="JK Michaels" class="w-8 h-8 object-contain" />
                            <p class="font-display font-bold text-surface-900 text-sm">SME Console</p>
                        </div>
                        <button @click="mobileOpen = false" class="p-1 text-surface-400 hover:text-surface-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                        <SidebarItem
                            v-for="item in [...mainNavItems, ...analyticsNavItems]"
                            :key="item.label"
                            :icon="item.icon"
                            :label="item.label"
                            :href="item.href"
                            :active="item.active"
                            :badge="item.badge"
                            :collapsed="false"
                            @click="mobileOpen = false"
                        />
                    </nav>
                </aside>
            </transition>
        </Teleport>

        <!-- Main content area -->
        <main :class="['transition-all duration-300 ease-in-out', collapsed ? 'lg:pl-[72px]' : 'lg:pl-64']">
            <div class="pt-14 lg:pt-0 pb-20 lg:pb-0">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                    <slot />
                </div>
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
    Library, ClipboardCheck, Flag, BarChart3, Target,
    BookOpen, ChevronLeft, ChevronRight, Menu, X,
    GitBranch, Layers, LogOut, MoreHorizontal, User, Settings,
} from 'lucide-vue-next';
import SidebarItem from '@/Layouts/Partials/SidebarItem.vue';
import MobileNavItem from '@/Layouts/Partials/MobileNavItem.vue';
import BottomNavMore from '@/Layouts/Partials/BottomNavMore.vue';
import OfflineBanner from '@/Components/OfflineBanner.vue';

const page = usePage();
const collapsed = ref(false);
const mobileOpen = ref(false);
const moreOpen = ref(false);

const currentUrl = computed(() => page.url);

const mainNavItems = computed(() => [
    {
        icon: Library,
        label: 'Content Library',
        href: '/sme',
        active: currentUrl.value === '/sme' || currentUrl.value.startsWith('/sme?'),
    },
    {
        icon: ClipboardCheck,
        label: 'Pending Review',
        href: '/sme/pending',
        active: currentUrl.value.startsWith('/sme/pending') || currentUrl.value.startsWith('/sme/review'),
    },
    {
        icon: BookOpen,
        label: 'Question Bank',
        href: '/sme/questions',
        active: currentUrl.value === '/sme/questions' || currentUrl.value.startsWith('/sme/questions?'),
    },
    {
        icon: Flag,
        label: 'Flag Queue',
        href: '/sme/flags',
        active: currentUrl.value.startsWith('/sme/flags'),
    },
    {
        icon: GitBranch,
        label: 'Versions',
        href: '#',
        active: currentUrl.value.startsWith('/sme/versions'),
    },
]);

const analyticsNavItems = computed(() => [
    {
        icon: BarChart3,
        label: 'Item Analytics',
        href: '/sme/questions/analytics-dashboard',
        active: currentUrl.value.startsWith('/sme/questions/analytics'),
    },
    {
        icon: Target,
        label: 'Blueprint Coverage',
        href: '/sme/questions/blueprint-coverage',
        active: currentUrl.value.startsWith('/sme/questions/blueprint'),
    },
]);

const mobileNavItems = computed(() => [
    { icon: Library, label: 'Content', href: '/sme', active: currentUrl.value === '/sme' || currentUrl.value.startsWith('/sme?') },
    { icon: ClipboardCheck, label: 'Reviews', href: '/sme/pending', active: currentUrl.value.startsWith('/sme/pending') },
    { icon: BookOpen, label: 'Questions', href: '/sme/questions', active: currentUrl.value === '/sme/questions' },
]);

const moreMenuItems = computed(() => [
    { icon: Flag, label: 'Flag Queue', href: '/sme/flags', active: currentUrl.value.startsWith('/sme/flags') },
    { icon: GitBranch, label: 'Versions', href: '#', active: currentUrl.value.startsWith('/sme/versions') },
    { icon: BarChart3, label: 'Item Analytics', href: '/sme/questions/analytics-dashboard', active: currentUrl.value.startsWith('/sme/questions/analytics') },
    { icon: Target, label: 'Blueprint', href: '/sme/questions/blueprint-coverage', active: currentUrl.value.startsWith('/sme/questions/blueprint') },
    { icon: User, label: 'Profile', href: '/profile', active: currentUrl.value.startsWith('/profile') },
    { icon: Settings, label: 'Settings', href: '/settings', active: currentUrl.value.startsWith('/settings') },
]);

const handleLogout = () => {
    router.post('/logout');
};
</script>

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
                        <p class="text-[10px] text-primary-600 font-medium uppercase tracking-wider">Professional Mastery</p>
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
                        Growth
                    </p>
                    <SidebarItem
                        v-for="item in growthNavItems"
                        :key="item.label"
                        :icon="item.icon"
                        :label="item.label"
                        :href="item.href"
                        :active="item.active"
                        :badge="item.badge"
                        :collapsed="collapsed"
                    />
                </div>
            </nav>

            <!-- XP Bar (at bottom of sidebar) -->
            <div v-if="!collapsed" class="border-t border-surface-100 p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-r from-xp-500 to-xp-400 flex items-center justify-center">
                            <Zap class="w-3.5 h-3.5 text-white" />
                        </div>
                        <span class="text-sm font-semibold text-surface-700">Level {{ user.level || 1 }}</span>
                    </div>
                    <span class="text-xs font-medium text-xp-600">{{ user.xp || 0 }} XP</span>
                </div>
                <div class="w-full h-1.5 rounded-full bg-surface-200 overflow-hidden">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-xp-500 to-xp-400 transition-all duration-700"
                        :style="{ width: `${xpProgress}%` }"
                    />
                </div>
            </div>

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
                    <span class="font-display font-bold text-surface-900 text-sm">JK Michaels</span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Streak -->
                    <div class="flex items-center gap-1 bg-warning-50 rounded-full px-2.5 py-1">
                        <Flame class="w-3.5 h-3.5 text-warning-500" />
                        <span class="text-xs font-bold text-warning-700">{{ user.streak || 0 }}</span>
                    </div>
                    <!-- Notifications -->
                    <button class="relative p-2 rounded-lg hover:bg-surface-100 transition-colors">
                        <Bell class="w-5 h-5 text-surface-500" />
                        <span v-if="notificationCount" class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-danger-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                            {{ notificationCount > 9 ? '9+' : notificationCount }}
                        </span>
                    </button>
                    <!-- Avatar -->
                    <button class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs">
                        {{ userInitials }}
                    </button>
                </div>
            </div>
        </header>

        <!-- Main content -->
        <main
            :class="[
                'transition-all duration-300',
                'lg:pl-64',
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
                    <!-- Streak -->
                    <div class="flex items-center gap-1.5 bg-warning-50 rounded-full px-3 py-1.5">
                        <Flame class="w-4 h-4 text-warning-500" />
                        <span class="text-sm font-bold text-warning-700">{{ user.streak || 0 }} day streak</span>
                    </div>

                    <!-- Search -->
                    <button class="p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                        <Search class="w-5 h-5" />
                    </button>

                    <!-- Notifications -->
                    <button class="relative p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                        <Bell class="w-5 h-5" />
                        <span v-if="notificationCount" class="absolute top-0.5 right-0.5 w-4 h-4 bg-danger-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                            {{ notificationCount > 9 ? '9+' : notificationCount }}
                        </span>
                    </button>

                    <!-- Profile -->
                    <button class="flex items-center gap-2.5 rounded-xl hover:bg-surface-50 p-1.5 pr-3 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-semibold text-xs">
                            {{ userInitials }}
                        </div>
                        <div class="text-left hidden xl:block">
                            <p class="text-sm font-medium text-surface-900 leading-tight">{{ user.name || 'Learner' }}</p>
                            <p class="text-[11px] text-surface-400">{{ user.role || 'Learner' }}</p>
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
                <!-- More button -->
                <button
                    @click="moreOpen = true"
                    :class="[
                        'flex flex-col items-center justify-center gap-0.5 rounded-xl transition-all duration-200 relative flex-1 py-1',
                        moreOpen ? 'text-primary-600' : 'text-surface-400 active:text-surface-600',
                    ]"
                >
                    <MoreHorizontal :class="['w-5 h-5', moreOpen ? 'text-primary-600' : 'text-surface-400']" />
                    <span :class="['text-[10px] font-semibold', moreOpen ? 'text-primary-600' : 'text-surface-400']">More</span>
                </button>
            </div>
            <!-- Safe area padding for iOS -->
            <div class="h-[env(safe-area-inset-bottom)]"></div>
        </nav>

        <!-- More menu sheet -->
        <BottomNavMore :open="moreOpen" :items="moreMenuItems" @close="moreOpen = false" />

        <!-- PWA Install Prompt -->
        <PwaInstallPrompt />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    Home,
    BookOpen,
    ClipboardCheck,
    BarChart3,
    Award,
    Briefcase,
    MessageSquare,
    User,
    Bell,
    Search,
    Flame,
    Zap,
    ChevronLeft,
    ChevronRight,
    Brain,
    Trophy,
    GraduationCap,
    LogOut,
    MoreHorizontal,
    Settings,
    Download,
    Bookmark,
    Sparkles,
} from 'lucide-vue-next';
import SidebarItem from './Partials/SidebarItem.vue';
import MobileNavItem from './Partials/MobileNavItem.vue';
import BottomNavMore from './Partials/BottomNavMore.vue';

const page = usePage();

const user = computed(() => page.props.auth?.user || {});
const notificationCount = computed(() => page.props.notificationCount || 0);
const collapsed = ref(false);
const moreOpen = ref(false);

const userInitials = computed(() => {
    if (user.value.initials) return user.value.initials;
    const name = user.value.name || 'L';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});

const xpProgress = computed(() => {
    return user.value.xp_progress || Math.min(((user.value.xp || 0) / (user.value.next_level_xp || 1000)) * 100, 100);
});

const currentUrl = computed(() => page.url);

const mainNavItems = computed(() => [
    { icon: Home, label: 'Home', href: '/dashboard', active: currentUrl.value === '/dashboard' },
    { icon: BookOpen, label: 'Courses', href: '/courses', active: currentUrl.value === '/courses' },
    { icon: GraduationCap, label: 'My Learning', href: '/my-courses', active: currentUrl.value.startsWith('/my-courses') },
    { icon: ClipboardCheck, label: 'Assessments', href: '/mock-exams', active: currentUrl.value.startsWith('/mock-exams') || currentUrl.value.startsWith('/quizzes') },
    { icon: Brain, label: 'Practice', href: '/practice', active: currentUrl.value.startsWith('/practice') || currentUrl.value.startsWith('/flashcards'), badge: page.props.dueCards || null },
    { icon: Sparkles, label: 'AI Tutor', href: '/ai/tutor', active: currentUrl.value.startsWith('/ai/tutor') },
    { icon: Briefcase, label: 'Internship', href: '/internship', active: currentUrl.value.startsWith('/internship') },
]);

const growthNavItems = computed(() => [
    { icon: BarChart3, label: 'Analytics', href: '/analytics', active: currentUrl.value.startsWith('/analytics') },
    { icon: Trophy, label: 'Badges', href: '/badges', active: currentUrl.value.startsWith('/badges') },
    { icon: Award, label: 'Missions', href: '/missions', active: currentUrl.value.startsWith('/missions') || currentUrl.value.startsWith('/leaderboard') },
    { icon: MessageSquare, label: 'Community', href: '/community', active: currentUrl.value.startsWith('/community') },
    { icon: User, label: 'Profile', href: '/profile', active: currentUrl.value.startsWith('/profile') },
]);

const mobileNavItems = computed(() => [
    { icon: Home, label: 'Home', href: '/dashboard', active: currentUrl.value === '/dashboard' },
    { icon: BookOpen, label: 'Learn', href: '/my-courses', active: currentUrl.value.startsWith('/my-courses') },
    { icon: Search, label: 'Browse', href: '/courses', active: currentUrl.value === '/courses' },
    { icon: BarChart3, label: 'Progress', href: '/analytics', active: currentUrl.value.startsWith('/analytics') },
]);

const moreMenuItems = computed(() => [
    { icon: ClipboardCheck, label: 'Assessments', href: '/mock-exams', active: currentUrl.value.startsWith('/mock-exams') },
    { icon: Brain, label: 'Practice', href: '/practice', active: currentUrl.value.startsWith('/practice'), badge: page.props.dueCards || null },
    { icon: Briefcase, label: 'Internship', href: '/internship', active: currentUrl.value.startsWith('/internship') },
    { icon: Trophy, label: 'Badges', href: '/badges', active: currentUrl.value.startsWith('/badges') },
    { icon: Award, label: 'Missions', href: '/missions', active: currentUrl.value.startsWith('/missions') },
    { icon: MessageSquare, label: 'Community', href: '/community', active: currentUrl.value.startsWith('/community') },
    { icon: Bookmark, label: 'Bookmarks', href: '/bookmarks', active: currentUrl.value.startsWith('/bookmarks') },
    { icon: Download, label: 'Downloads', href: '/downloads', active: currentUrl.value.startsWith('/downloads') },
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

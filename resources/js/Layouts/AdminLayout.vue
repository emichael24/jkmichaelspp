<template>
    <div class="min-h-screen bg-surface-50">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex flex-col bg-surface-900 text-surface-300',
                'transition-all duration-300 ease-in-out',
                'hidden lg:flex',
                collapsed ? 'w-[72px]' : 'w-64',
            ]"
        >
            <!-- Logo -->
            <div :class="['flex items-center gap-3 px-4 h-16 border-b border-surface-800 shrink-0', collapsed ? 'justify-center' : '']">
                <img src="/images/logo-icon.png" alt="JK Michaels" class="w-9 h-9 shrink-0 object-contain" />
                <div v-if="!collapsed" class="min-w-0">
                    <p class="font-display font-bold text-white text-sm leading-tight truncate">Admin Portal</p>
                    <p class="text-[10px] text-surface-500 font-medium uppercase tracking-wider">JK Michaels</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <AdminSidebarItem
                    v-for="item in navItems"
                    :key="item.label"
                    :icon="item.icon"
                    :label="item.label"
                    :href="item.href"
                    :active="item.active"
                    :collapsed="collapsed"
                />

                <div class="pt-4 mt-4 border-t border-surface-800">
                    <p v-if="!collapsed" class="px-3 mb-2 text-[10px] font-semibold text-surface-600 uppercase tracking-wider">
                        System
                    </p>
                    <AdminSidebarItem
                        v-for="item in systemNavItems"
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
                    'flex items-center gap-2 mx-3 mb-2 px-3 py-2 rounded-lg text-surface-500 hover:text-danger-400 hover:bg-surface-800 transition-colors text-sm',
                    collapsed ? 'justify-center' : '',
                ]"
            >
                <LogOut class="w-4 h-4 shrink-0" />
                <span v-if="!collapsed">Log Out</span>
            </button>

            <!-- Collapse toggle -->
            <button
                @click="collapsed = !collapsed"
                class="flex items-center justify-center h-10 border-t border-surface-800 text-surface-500 hover:text-surface-300 hover:bg-surface-800 transition-colors"
            >
                <ChevronLeft v-if="!collapsed" class="w-4 h-4" />
                <ChevronRight v-else class="w-4 h-4" />
            </button>
        </aside>

        <!-- Mobile header -->
        <header class="fixed top-0 left-0 right-0 z-30 lg:hidden bg-surface-900 h-14">
            <div class="flex items-center justify-between h-full px-4">
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen = !mobileOpen" class="p-1.5 rounded-lg text-surface-400 hover:bg-surface-800">
                        <Menu class="w-5 h-5" />
                    </button>
                    <span class="font-display font-bold text-white text-sm">Admin</span>
                </div>
                <div class="flex items-center gap-2">
                    <button class="relative p-2 rounded-lg text-surface-400 hover:bg-surface-800">
                        <Bell class="w-5 h-5" />
                    </button>
                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white font-semibold text-xs">
                        {{ userInitials }}
                    </div>
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
            <div class="hidden lg:flex items-center justify-between h-16 px-6 border-b border-surface-100 bg-white sticky top-0 z-20">
                <div>
                    <h1 v-if="$page.props.pageTitle" class="text-lg font-semibold text-surface-900">
                        {{ $page.props.pageTitle }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <button class="p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                        <Search class="w-5 h-5" />
                    </button>
                    <button class="relative p-2 rounded-lg hover:bg-surface-100 transition-colors text-surface-500">
                        <Bell class="w-5 h-5" />
                    </button>
                    <div class="w-px h-8 bg-surface-200 mx-1" />
                    <button class="flex items-center gap-2.5 rounded-xl hover:bg-surface-50 p-1.5 pr-3 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-600 to-primary-700 flex items-center justify-center text-white font-semibold text-xs">
                            {{ userInitials }}
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-medium text-surface-900 leading-tight">{{ user.name || 'Admin' }}</p>
                            <p class="text-[11px] text-surface-400">Administrator</p>
                        </div>
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-6 lg:p-8">
                <slot />
            </div>
        </main>

        <!-- Mobile bottom navigation -->
        <nav class="fixed bottom-0 left-0 right-0 z-30 lg:hidden bg-surface-900/95 backdrop-blur-xl border-t border-surface-800">
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
                        moreOpen ? 'text-primary-400' : 'text-surface-500 active:text-surface-300',
                    ]"
                >
                    <MoreHorizontal class="w-5 h-5" />
                    <span class="text-[10px] font-semibold">More</span>
                </button>
            </div>
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
    LayoutDashboard,
    Users,
    BookOpen,
    ClipboardCheck,
    Briefcase,
    ShoppingBag,
    BarChart3,
    Settings,
    Shield,
    FileText,
    ChevronLeft,
    ChevronRight,
    Bell,
    Search,
    Menu,
    ScrollText,
    HelpCircle,
    LogOut,
    MoreHorizontal,
    CheckSquare,
    Building2,
    MessageSquare,
    Sparkles,
    Bot,
} from 'lucide-vue-next';
import AdminSidebarItem from './Partials/AdminSidebarItem.vue';
import MobileNavItem from './Partials/MobileNavItem.vue';
import BottomNavMore from './Partials/BottomNavMore.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const collapsed = ref(false);
const mobileOpen = ref(false);
const moreOpen = ref(false);

const userInitials = computed(() => {
    const name = user.value.name || 'A';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});

const currentUrl = computed(() => page.url);

const navItems = computed(() => [
    { icon: LayoutDashboard, label: 'Dashboard', href: '/admin', active: currentUrl.value === '/admin' },
    { icon: Users, label: 'Users', href: '/admin/users', active: currentUrl.value.startsWith('/admin/users') },
    { icon: BookOpen, label: 'Courses', href: '/admin/courses', active: currentUrl.value.startsWith('/admin/courses') && !currentUrl.value.includes('course-approvals') },
    { icon: CheckSquare, label: 'Course Approvals', href: '/admin/course-approvals', active: currentUrl.value.startsWith('/admin/course-approvals') },
    { icon: HelpCircle, label: 'Questions', href: '/admin/questions', active: currentUrl.value.startsWith('/admin/questions') },
    { icon: ClipboardCheck, label: 'Quizzes', href: '/admin/quizzes', active: currentUrl.value.startsWith('/admin/quizzes') },
    { icon: ScrollText, label: 'Mock Exams', href: '/admin/mock-exams', active: currentUrl.value.startsWith('/admin/mock-exams') },
    { icon: Briefcase, label: 'Internships', href: '/admin/internships', active: currentUrl.value.startsWith('/admin/internships') },
    { icon: ShoppingBag, label: 'Commerce', href: '/admin/commerce', active: currentUrl.value.startsWith('/admin/commerce') },
    { icon: Building2, label: 'ERP / CRM', href: '/admin/erp', active: currentUrl.value.startsWith('/admin/erp') },
    { icon: MessageSquare, label: 'Messaging', href: '/admin/erp/messaging', active: currentUrl.value.startsWith('/admin/erp/messaging') },
    { icon: Bot, label: 'AI Insights', href: '/admin/erp/ai/insights', active: currentUrl.value.startsWith('/admin/erp/ai') },
    { icon: BarChart3, label: 'Analytics', href: '/admin/analytics', active: currentUrl.value.startsWith('/admin/analytics') },
]);

const systemNavItems = computed(() => [
    { icon: Shield, label: 'Roles & Permissions', href: '/admin/roles', active: currentUrl.value.startsWith('/admin/roles') },
    { icon: ScrollText, label: 'Audit Logs', href: '/admin/audit', active: currentUrl.value.startsWith('/admin/audit') },
    { icon: Settings, label: 'Settings', href: '/admin/settings', active: currentUrl.value.startsWith('/admin/settings') },
]);

const mobileNavItems = computed(() => [
    { icon: LayoutDashboard, label: 'Dashboard', href: '/admin', active: currentUrl.value === '/admin' },
    { icon: Users, label: 'Users', href: '/admin/users', active: currentUrl.value.startsWith('/admin/users') },
    { icon: Building2, label: 'ERP', href: '/admin/erp', active: currentUrl.value.startsWith('/admin/erp') },
    { icon: BarChart3, label: 'Analytics', href: '/admin/analytics', active: currentUrl.value.startsWith('/admin/analytics') },
]);

const moreMenuItems = computed(() => [
    { icon: BookOpen, label: 'Courses', href: '/admin/courses', active: currentUrl.value.startsWith('/admin/courses') },
    { icon: CheckSquare, label: 'Course Approvals', href: '/admin/course-approvals', active: currentUrl.value.startsWith('/admin/course-approvals') },
    { icon: HelpCircle, label: 'Questions', href: '/admin/questions', active: currentUrl.value.startsWith('/admin/questions') },
    { icon: ClipboardCheck, label: 'Quizzes', href: '/admin/quizzes', active: currentUrl.value.startsWith('/admin/quizzes') },
    { icon: ScrollText, label: 'Mock Exams', href: '/admin/mock-exams', active: currentUrl.value.startsWith('/admin/mock-exams') },
    { icon: Briefcase, label: 'Internships', href: '/admin/internships', active: currentUrl.value.startsWith('/admin/internships') },
    { icon: ShoppingBag, label: 'Commerce', href: '/admin/commerce', active: currentUrl.value.startsWith('/admin/commerce') },
    { icon: MessageSquare, label: 'Messaging', href: '/admin/erp/messaging', active: currentUrl.value.startsWith('/admin/erp/messaging') },
    { icon: Bot, label: 'AI Insights', href: '/admin/erp/ai/insights', active: currentUrl.value.startsWith('/admin/erp/ai') },
    { icon: Shield, label: 'Roles', href: '/admin/roles', active: currentUrl.value.startsWith('/admin/roles') },
    { icon: ScrollText, label: 'Audit Logs', href: '/admin/audit', active: currentUrl.value.startsWith('/admin/audit') },
    { icon: Settings, label: 'Settings', href: '/admin/settings', active: currentUrl.value.startsWith('/admin/settings') },
]);

const handleLogout = () => {
    router.post('/logout');
};
</script>

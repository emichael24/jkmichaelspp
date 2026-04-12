<template>
    <Head title="Notifications" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Notifications</h1>
                <p class="text-surface-500 mt-1 text-sm">
                    {{ unreadCount > 0 ? `${unreadCount} unread` : 'All caught up!' }}
                </p>
            </div>
            <div class="flex gap-2">
                <button v-if="unreadCount > 0" @click="markAllRead"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium text-primary-600 bg-primary-50 hover:bg-primary-100 transition-colors">
                    Mark all read
                </button>
                <a href="/notifications/preferences"
                   class="p-2 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-surface-700">
                    <Settings class="w-5 h-5" />
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex gap-2">
            <button v-for="cat in categories" :key="cat.value"
                    @click="filterCategory(cat.value)"
                    :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors', filters.category === cat.value ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-600 hover:bg-surface-200']">
                {{ cat.label }}
            </button>
            <button @click="toggleUnread"
                    :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ml-auto', filters.unread ? 'bg-warning-100 text-warning-700' : 'bg-surface-100 text-surface-600 hover:bg-surface-200']">
                Unread Only
            </button>
        </div>

        <!-- Notification list -->
        <div class="space-y-2">
            <a v-for="notif in notifications.data" :key="notif.id"
               :href="notif.action_url || '#'"
               @click="markRead(notif)"
               :class="['flex items-start gap-4 p-4 rounded-xl border transition-colors', notif.read_at ? 'bg-white border-surface-200/60' : 'bg-primary-50/50 border-primary-200/60']">
                <!-- Icon -->
                <div :class="['w-10 h-10 rounded-full flex items-center justify-center shrink-0', categoryIcon(notif.category).bg]">
                    <component :is="categoryIcon(notif.category).icon" :class="['w-5 h-5', categoryIcon(notif.category).color]" />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <p :class="['text-sm', notif.read_at ? 'text-surface-700' : 'text-surface-900 font-semibold']">
                        {{ notif.title }}
                    </p>
                    <p class="text-sm text-surface-500 mt-0.5 line-clamp-2">{{ notif.body }}</p>
                    <p class="text-xs text-surface-400 mt-1">{{ formatTime(notif.created_at) }}</p>
                </div>

                <!-- Unread dot -->
                <div v-if="!notif.read_at" class="w-2.5 h-2.5 rounded-full bg-primary-500 mt-2 shrink-0" />
            </a>
        </div>

        <EmptyState v-if="!notifications.data?.length" title="No Notifications"
                    description="You're all caught up! Check back later." />

        <!-- Pagination -->
        <div v-if="notifications.links && notifications.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in notifications.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Settings, BookOpen, Briefcase, Shield, Bell } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    notifications: { type: Object, default: () => ({ data: [] }) },
    unreadCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
});

const categories = [
    { value: '', label: 'All' },
    { value: 'learning', label: 'Learning' },
    { value: 'internship', label: 'Internship' },
    { value: 'admin', label: 'Admin' },
    { value: 'system', label: 'System' },
];

function filterCategory(cat) {
    const params = { ...props.filters, category: cat || undefined };
    router.get('/notifications', params, { preserveState: true });
}

function toggleUnread() {
    const params = { ...props.filters, unread: props.filters.unread ? undefined : true };
    router.get('/notifications', params, { preserveState: true });
}

function markRead(notif) {
    if (!notif.read_at) {
        router.put(`/notifications/${notif.id}/read`, {}, { preserveState: true, preserveScroll: true });
    }
}

function markAllRead() {
    router.put('/notifications/read-all', {}, { preserveState: false });
}

function categoryIcon(cat) {
    const map = {
        learning: { icon: BookOpen, bg: 'bg-primary-100', color: 'text-primary-600' },
        internship: { icon: Briefcase, bg: 'bg-purple-100', color: 'text-purple-600' },
        admin: { icon: Shield, bg: 'bg-warning-100', color: 'text-warning-600' },
        system: { icon: Bell, bg: 'bg-surface-200', color: 'text-surface-600' },
    };
    return map[cat] || map.system;
}

function formatTime(date) {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    const diffMs = now - d;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'Just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    const diffDays = Math.floor(diffHr / 24);
    if (diffDays < 7) return `${diffDays}d ago`;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

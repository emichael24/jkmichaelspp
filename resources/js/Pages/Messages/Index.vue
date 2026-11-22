<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, Search, Plus, User, Circle } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ conversations: Array, unreadTotal: Number });

const page = usePage();
const showNewChat = ref(false);
const userSearch = ref('');
const searchResults = ref([]);
let searchTimeout = null;

function searchUsers() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        if (userSearch.value.length < 2) { searchResults.value = []; return; }
        const res = await fetch(route('messages.searchUsers') + '?q=' + encodeURIComponent(userSearch.value));
        searchResults.value = await res.json();
    }, 300);
}

function startConversation(userId) {
    showNewChat.value = false;
    router.visit(route('messages.show', userId));
}

function timeAgo(date) {
    const diff = (Date.now() - new Date(date)) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-display font-bold text-surface-900">Messages</h1>
                <p v-if="unreadTotal > 0" class="text-primary-600 text-sm mt-1">{{ unreadTotal }} unread message{{ unreadTotal > 1 ? 's' : '' }}</p>
            </div>
            <Button @click="showNewChat = true" variant="primary" class="gap-2"><Plus class="w-4 h-4" /> New Message</Button>
        </div>

        <Link v-if="page.props.auth?.user?.roles?.includes('platform_admin')" href="/admin/erp/messaging?compose=1" class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4"><div><p class="font-semibold text-primary-900">Send a bulk campaign</p><p class="mt-1 text-sm text-primary-800">Email, WhatsApp or platform inbox · choose users, organizations or CRM contacts.</p></div><Plus class="h-5 w-5 shrink-0 text-primary-700" /></Link>
        <div class="space-y-2">
            <Card v-for="conv in conversations" :key="conv.partner?.id" class="p-4 hover:shadow-md transition cursor-pointer" @click="router.visit(route('messages.show', conv.partner?.id))">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center">
                            <img v-if="conv.partner?.avatar" :src="conv.partner.avatar" class="w-12 h-12 rounded-full object-cover" />
                            <User v-else class="w-6 h-6 text-primary-600" />
                        </div>
                        <Circle v-if="conv.unread_count > 0" class="w-3 h-3 text-primary-500 fill-primary-500 absolute -top-0.5 -right-0.5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h3 :class="['font-medium', conv.unread_count > 0 ? 'text-surface-900 font-semibold' : 'text-surface-700']">
                                {{ conv.partner?.first_name }} {{ conv.partner?.last_name }}
                            </h3>
                            <span class="text-xs text-surface-400">{{ timeAgo(conv.last_message?.created_at) }}</span>
                        </div>
                        <p class="text-sm text-surface-500 truncate mt-0.5">{{ conv.last_message?.body }}</p>
                    </div>
                    <div v-if="conv.unread_count > 0" class="w-6 h-6 rounded-full bg-primary-500 text-white text-xs font-bold flex items-center justify-center">
                        {{ conv.unread_count }}
                    </div>
                </div>
            </Card>
        </div>

        <EmptyState v-if="!conversations?.length" title="No conversations yet" description="Start a conversation by clicking 'New Message'" :icon="MessageCircle" />

        <!-- New Chat Modal -->
        <Modal :show="showNewChat" @close="showNewChat = false" title="New Message">
            <div class="space-y-4">
                <Link v-if="page.props.auth?.user?.roles?.includes('platform_admin')" href="/admin/erp/messaging?compose=1" class="block rounded-xl bg-primary-50 p-3 text-sm font-semibold text-primary-800">Message an audience → Create a bulk campaign</Link>
                <p class="text-sm text-surface-500">Or search for a person to start a private conversation.</p>
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-surface-400" />
                    <input v-model="userSearch" @input="searchUsers" type="text" placeholder="Search users..." class="w-full pl-10 pr-4 py-2.5 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                </div>
                <div class="max-h-64 overflow-y-auto space-y-1">
                    <button v-for="user in searchResults" :key="user.id" @click="startConversation(user.id)" class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-surface-50 text-left">
                        <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                            <img v-if="user.avatar" :src="user.avatar" class="w-10 h-10 rounded-full object-cover" />
                            <User v-else class="w-5 h-5 text-primary-600" />
                        </div>
                        <span class="font-medium text-surface-800">{{ user.first_name }} {{ user.last_name }}</span>
                    </button>
                    <p v-if="userSearch.length >= 2 && !searchResults.length" class="text-sm text-surface-400 text-center py-4">No users found</p>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Send, User, Check, CheckCheck } from 'lucide-vue-next';
import Button from '@/Components/UI/Button.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ partner: Object, messages: Array });
const page = usePage();
const currentUserId = page.props.auth?.user?.id;

const messageContainer = ref(null);
const form = useForm({ recipient_id: props.partner.id, body: '' });

function sendMessage() {
    if (!form.body.trim()) return;
    form.post(route('messages.store'), {
        onSuccess: () => { form.body = ''; scrollToBottom(); },
        preserveScroll: true,
    });
}

function scrollToBottom() {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
}

function formatTime(date) {
    return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDate(date) {
    const d = new Date(date);
    const today = new Date();
    if (d.toDateString() === today.toDateString()) return 'Today';
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
}

function shouldShowDate(index) {
    if (index === 0) return true;
    const current = new Date(props.messages[index].created_at).toDateString();
    const prev = new Date(props.messages[index - 1].created_at).toDateString();
    return current !== prev;
}

onMounted(scrollToBottom);
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-4 flex flex-col" style="height: calc(100vh - 80px);">
        <!-- Header -->
        <div class="flex items-center gap-3 pb-4 border-b border-surface-200">
            <button @click="router.visit(route('messages.index'))" class="p-2 rounded-lg hover:bg-surface-100">
                <ArrowLeft class="w-5 h-5 text-surface-600" />
            </button>
            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                <img v-if="partner.avatar" :src="partner.avatar" class="w-10 h-10 rounded-full object-cover" />
                <User v-else class="w-5 h-5 text-primary-600" />
            </div>
            <div>
                <h2 class="font-semibold text-surface-900">{{ partner.first_name }} {{ partner.last_name }}</h2>
            </div>
        </div>

        <!-- Messages -->
        <div ref="messageContainer" class="flex-1 overflow-y-auto py-4 space-y-1">
            <template v-for="(msg, index) in messages" :key="msg.id">
                <div v-if="shouldShowDate(index)" class="text-center my-4">
                    <span class="text-xs text-surface-400 bg-surface-100 px-3 py-1 rounded-full">{{ formatDate(msg.created_at) }}</span>
                </div>

                <div :class="['flex', msg.sender_id === currentUserId ? 'justify-end' : 'justify-start']">
                    <div :class="['max-w-[75%] rounded-2xl px-4 py-2.5 mb-1', msg.sender_id === currentUserId ? 'bg-primary-600 text-white rounded-br-md' : 'bg-surface-100 text-surface-900 rounded-bl-md']">
                        <p class="text-sm whitespace-pre-wrap">{{ msg.body }}</p>
                        <div :class="['flex items-center gap-1 mt-1 text-[10px]', msg.sender_id === currentUserId ? 'text-primary-200 justify-end' : 'text-surface-400']">
                            <span>{{ formatTime(msg.created_at) }}</span>
                            <template v-if="msg.sender_id === currentUserId">
                                <CheckCheck v-if="msg.read_at" class="w-3 h-3" />
                                <Check v-else class="w-3 h-3" />
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Input -->
        <div class="pt-3 border-t border-surface-200">
            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input v-model="form.body" type="text" placeholder="Type a message..." class="flex-1 px-4 py-3 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500" @keydown.enter.exact.prevent="sendMessage" />
                <Button type="submit" variant="primary" class="rounded-xl px-4" :disabled="form.processing || !form.body.trim()">
                    <Send class="w-5 h-5" />
                </Button>
            </form>
        </div>
    </div>
</template>

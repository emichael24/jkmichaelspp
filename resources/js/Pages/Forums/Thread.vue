<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { MessageSquare, Pin, Lock, Unlock, Trash2, Check, Reply, User, Clock } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ thread: Object });
const page = usePage();
const currentUserId = page.props.auth?.user?.id;

const replyForm = useForm({ body: '', parent_id: null });
const replyingTo = ref(null);

function submitReply() {
    replyForm.post(route('forums.replies.store', props.thread.id), {
        onSuccess: () => { replyForm.reset(); replyingTo.value = null; },
        preserveScroll: true,
    });
}

function startReply(parentId = null) {
    replyingTo.value = parentId;
    replyForm.parent_id = parentId;
}

function togglePin() { router.put(route('forums.threads.pin', props.thread.id), {}, { preserveScroll: true }); }
function toggleLock() { router.put(route('forums.threads.lock', props.thread.id), {}, { preserveScroll: true }); }
function deleteThread() { if (confirm('Delete this thread?')) router.delete(route('forums.threads.destroy', props.thread.id)); }
function deleteReply(id) { if (confirm('Delete this reply?')) router.delete(route('forums.replies.destroy', id), { preserveScroll: true }); }
function acceptAnswer(id) { router.put(route('forums.replies.accept', id), {}, { preserveScroll: true }); }

function timeAgo(date) {
    const diff = (Date.now() - new Date(date)) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <button @click="router.visit(route('forums.show', thread.forum_id))" class="text-sm text-primary-600 hover:underline mb-4 block">← Back to {{ thread.forum?.title }}</button>

        <!-- Thread Header -->
        <Card class="p-6 mb-6">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <Badge v-if="thread.is_pinned" variant="warning" size="sm"><Pin class="w-3 h-3 mr-1" />Pinned</Badge>
                        <Badge v-if="thread.is_locked" variant="error" size="sm"><Lock class="w-3 h-3 mr-1" />Locked</Badge>
                    </div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">{{ thread.title }}</h1>
                    <div class="flex items-center gap-3 mt-2 text-sm text-surface-500">
                        <div class="flex items-center gap-1.5">
                            <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center"><User class="w-3.5 h-3.5 text-primary-600" /></div>
                            {{ thread.user?.first_name }} {{ thread.user?.last_name }}
                        </div>
                        <span class="flex items-center gap-1"><Clock class="w-3.5 h-3.5" />{{ timeAgo(thread.created_at) }}</span>
                        <span>{{ thread.reply_count }} replies</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="togglePin" class="p-2 rounded-lg hover:bg-surface-100" :title="thread.is_pinned ? 'Unpin' : 'Pin'"><Pin class="w-4 h-4 text-surface-500" /></button>
                    <button @click="toggleLock" class="p-2 rounded-lg hover:bg-surface-100" :title="thread.is_locked ? 'Unlock' : 'Lock'">
                        <Lock v-if="!thread.is_locked" class="w-4 h-4 text-surface-500" />
                        <Unlock v-else class="w-4 h-4 text-surface-500" />
                    </button>
                    <button @click="deleteThread" class="p-2 rounded-lg hover:bg-error-50"><Trash2 class="w-4 h-4 text-error-500" /></button>
                </div>
            </div>
            <div class="mt-4 prose prose-sm max-w-none text-surface-700 whitespace-pre-wrap">{{ thread.body }}</div>
        </Card>

        <!-- Replies -->
        <h2 class="text-lg font-semibold text-surface-800 mb-4">Replies ({{ thread.reply_count }})</h2>

        <div class="space-y-4 mb-8">
            <div v-for="reply in thread.replies" :key="reply.id">
                <Card :class="['p-4', reply.is_accepted_answer ? 'ring-2 ring-success-500 bg-success-50' : '']">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2 text-sm text-surface-500">
                            <div class="w-7 h-7 rounded-full bg-surface-100 flex items-center justify-center"><User class="w-3.5 h-3.5 text-surface-600" /></div>
                            <span class="font-medium text-surface-700">{{ reply.user?.first_name }} {{ reply.user?.last_name }}</span>
                            <span>{{ timeAgo(reply.created_at) }}</span>
                            <Badge v-if="reply.is_accepted_answer" variant="success" size="sm"><Check class="w-3 h-3 mr-1" />Accepted</Badge>
                        </div>
                        <div class="flex gap-1">
                            <button v-if="!reply.is_accepted_answer && thread.user_id === currentUserId" @click="acceptAnswer(reply.id)" class="p-1 rounded hover:bg-success-100" title="Accept answer"><Check class="w-4 h-4 text-success-600" /></button>
                            <button v-if="!thread.is_locked" @click="startReply(reply.id)" class="p-1 rounded hover:bg-surface-100" title="Reply"><Reply class="w-4 h-4 text-surface-500" /></button>
                            <button @click="deleteReply(reply.id)" class="p-1 rounded hover:bg-error-50" title="Delete"><Trash2 class="w-4 h-4 text-error-500" /></button>
                        </div>
                    </div>
                    <div class="mt-2 text-surface-700 whitespace-pre-wrap">{{ reply.body }}</div>

                    <!-- Nested replies -->
                    <div v-if="reply.children?.length" class="mt-3 pl-4 border-l-2 border-surface-200 space-y-3">
                        <div v-for="child in reply.children" :key="child.id" class="py-2">
                            <div class="flex items-center gap-2 text-xs text-surface-500 mb-1">
                                <span class="font-medium text-surface-700">{{ child.user?.first_name }} {{ child.user?.last_name }}</span>
                                <span>{{ timeAgo(child.created_at) }}</span>
                            </div>
                            <p class="text-sm text-surface-700">{{ child.body }}</p>
                        </div>
                    </div>

                    <!-- Reply to this reply form -->
                    <div v-if="replyingTo === reply.id" class="mt-3 pt-3 border-t border-surface-200">
                        <textarea v-model="replyForm.body" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500 text-sm" placeholder="Write a reply..."></textarea>
                        <div class="flex justify-end gap-2 mt-2">
                            <Button @click="replyingTo = null" variant="secondary" size="sm">Cancel</Button>
                            <Button @click="submitReply" variant="primary" size="sm" :disabled="replyForm.processing">Reply</Button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Main Reply Form -->
        <Card v-if="!thread.is_locked" class="p-4">
            <h3 class="font-medium text-surface-800 mb-3">Post a Reply</h3>
            <form @submit.prevent="() => { replyForm.parent_id = null; submitReply(); }">
                <textarea v-model="replyForm.body" rows="4" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Share your thoughts..." required></textarea>
                <div class="flex justify-end mt-3">
                    <Button type="submit" variant="primary" :disabled="replyForm.processing">Post Reply</Button>
                </div>
            </form>
        </Card>
        <Card v-else class="p-4 bg-surface-50 text-center text-surface-500">
            <Lock class="w-5 h-5 mx-auto mb-2" /> This thread is locked. No new replies can be posted.
        </Card>
    </div>
</template>

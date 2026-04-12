<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { MessageSquare, Plus, Pin, Lock, Clock, User } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ forum: Object, threads: Object });

const showCreate = ref(false);
const form = useForm({ title: '', body: '' });

function createThread() {
    form.post(route('forums.threads.store', props.forum.id), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
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
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <button @click="router.visit(route('forums.index'))" class="text-sm text-primary-600 hover:underline mb-1">← Back to Forums</button>
                <h1 class="text-2xl font-display font-bold text-surface-900">{{ forum.title }}</h1>
                <p class="text-surface-500 mt-0.5">{{ forum.description }}</p>
            </div>
            <Button @click="showCreate = true" variant="primary" class="gap-2"><Plus class="w-4 h-4" /> New Thread</Button>
        </div>

        <div class="space-y-3">
            <Card v-for="thread in threads.data" :key="thread.id" class="p-4 hover:shadow-md transition cursor-pointer" @click="router.visit(route('forums.threads.show', thread.id))">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                        <User class="w-5 h-5 text-primary-600" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-surface-900 truncate">{{ thread.title }}</h3>
                            <Badge v-if="thread.is_pinned" variant="warning" size="sm"><Pin class="w-3 h-3 mr-1" />Pinned</Badge>
                            <Badge v-if="thread.is_locked" variant="error" size="sm"><Lock class="w-3 h-3 mr-1" />Locked</Badge>
                        </div>
                        <p class="text-sm text-surface-500 mt-1 line-clamp-2">{{ thread.body }}</p>
                        <div class="flex items-center gap-4 mt-2 text-xs text-surface-400">
                            <span>{{ thread.user?.first_name }} {{ thread.user?.last_name }}</span>
                            <span class="flex items-center gap-1"><MessageSquare class="w-3 h-3" />{{ thread.reply_count }} replies</span>
                            <span class="flex items-center gap-1"><Clock class="w-3 h-3" />{{ timeAgo(thread.last_reply_at || thread.created_at) }}</span>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <EmptyState v-if="!threads.data?.length" title="No threads yet" description="Be the first to start a discussion!" :icon="MessageSquare" />

        <!-- Create Thread Modal -->
        <Modal :show="showCreate" @close="showCreate = false" title="New Thread">
            <form @submit.prevent="createThread" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                    <input v-model="form.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" required />
                    <p v-if="form.errors.title" class="text-sm text-error-600 mt-1">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Body</label>
                    <textarea v-model="form.body" rows="6" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" required></textarea>
                    <p v-if="form.errors.body" class="text-sm text-error-600 mt-1">{{ form.errors.body }}</p>
                </div>
                <div class="flex justify-end gap-3">
                    <Button @click="showCreate = false" variant="secondary">Cancel</Button>
                    <Button type="submit" variant="primary" :disabled="form.processing">Post Thread</Button>
                </div>
            </form>
        </Modal>
    </div>
</template>

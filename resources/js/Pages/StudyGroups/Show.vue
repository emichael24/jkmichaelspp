<script setup>
import { router } from '@inertiajs/vue3';
import { Users, BookOpen, UserPlus, UserMinus, Trash2, User } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ group: Object, isMember: Boolean });

function joinGroup() { router.post(route('study-groups.join', props.group.id)); }
function leaveGroup() { if (confirm('Leave this group?')) router.post(route('study-groups.leave', props.group.id)); }
function deleteGroup() { if (confirm('Delete this study group?')) router.delete(route('study-groups.destroy', props.group.id)); }
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <button @click="router.visit(route('study-groups.index'))" class="text-sm text-primary-600 hover:underline mb-4 block">← Back to Study Groups</button>

        <Card class="p-6 mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 rounded-xl bg-accent-100 flex items-center justify-center">
                            <Users class="w-6 h-6 text-accent-600" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-display font-bold text-surface-900">{{ group.name }}</h1>
                            <p v-if="group.course" class="text-surface-500 flex items-center gap-1 mt-0.5"><BookOpen class="w-4 h-4" />{{ group.course.title }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button v-if="!isMember && !group.is_full" @click="joinGroup" variant="primary" class="gap-2"><UserPlus class="w-4 h-4" />Join</Button>
                    <Button v-if="isMember" @click="leaveGroup" variant="secondary" class="gap-2"><UserMinus class="w-4 h-4" />Leave</Button>
                    <button v-if="group.created_by === $page.props.auth?.user?.id" @click="deleteGroup" class="p-2 rounded-lg hover:bg-error-50"><Trash2 class="w-4 h-4 text-error-500" /></button>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="text-center p-3 bg-surface-50 rounded-xl">
                    <p class="text-2xl font-bold text-surface-900">{{ group.members_count }}</p>
                    <p class="text-xs text-surface-500">Members</p>
                </div>
                <div class="text-center p-3 bg-surface-50 rounded-xl">
                    <p class="text-2xl font-bold text-surface-900">{{ group.max_members }}</p>
                    <p class="text-xs text-surface-500">Max Capacity</p>
                </div>
                <div class="text-center p-3 bg-surface-50 rounded-xl">
                    <Badge :variant="group.is_public ? 'success' : 'warning'">{{ group.is_public ? 'Public' : 'Private' }}</Badge>
                </div>
            </div>
        </Card>

        <!-- Members List -->
        <Card class="p-6">
            <h2 class="text-lg font-semibold text-surface-800 mb-4">Members ({{ group.members?.length || 0 }})</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                <div v-for="member in group.members" :key="member.id" class="flex items-center gap-3 p-3 bg-surface-50 rounded-xl">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                        <img v-if="member.avatar" :src="member.avatar" class="w-10 h-10 rounded-full object-cover" />
                        <User v-else class="w-5 h-5 text-primary-600" />
                    </div>
                    <div>
                        <p class="font-medium text-surface-800">{{ member.first_name }} {{ member.last_name }}</p>
                        <p v-if="member.id === group.created_by" class="text-xs text-accent-600">Creator</p>
                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>

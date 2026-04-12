<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Users, Plus, BookOpen, Globe, Lock } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ groups: Object, filter: String });

const showCreate = ref(false);
const form = useForm({ name: '', course_id: '', max_members: 10, is_public: true });

function createGroup() {
    form.post(route('study-groups.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

function setFilter(f) {
    router.get(route('study-groups.index'), { filter: f }, { preserveState: true });
}
</script>

<template>
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-display font-bold text-surface-900">Study Groups</h1>
                <p class="text-surface-500 mt-1">Learn together with peers</p>
            </div>
            <Button @click="showCreate = true" variant="primary" class="gap-2"><Plus class="w-4 h-4" /> Create Group</Button>
        </div>

        <!-- Filters -->
        <div class="flex gap-2 mb-6">
            <Button @click="setFilter(null)" :variant="!filter ? 'primary' : 'secondary'" size="sm">All</Button>
            <Button @click="setFilter('mine')" :variant="filter === 'mine' ? 'primary' : 'secondary'" size="sm">My Groups</Button>
            <Button @click="setFilter('available')" :variant="filter === 'available' ? 'primary' : 'secondary'" size="sm">Available</Button>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card v-for="group in groups.data" :key="group.id" class="p-5 hover:shadow-md transition cursor-pointer" @click="router.visit(route('study-groups.show', group.id))">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-accent-100 flex items-center justify-center">
                        <Users class="w-5 h-5 text-accent-600" />
                    </div>
                    <Badge v-if="group.is_public" variant="success" size="sm"><Globe class="w-3 h-3 mr-1" />Public</Badge>
                    <Badge v-else variant="warning" size="sm"><Lock class="w-3 h-3 mr-1" />Private</Badge>
                </div>
                <h3 class="font-semibold text-surface-900">{{ group.name }}</h3>
                <p v-if="group.course" class="text-sm text-surface-500 mt-1 flex items-center gap-1"><BookOpen class="w-3.5 h-3.5" />{{ group.course.title }}</p>
                <div class="flex items-center justify-between mt-3 text-sm text-surface-400">
                    <span>{{ group.members_count }}/{{ group.max_members }} members</span>
                    <span>by {{ group.creator?.first_name }}</span>
                </div>
                <div class="mt-3 bg-surface-100 rounded-full h-2 overflow-hidden">
                    <div class="bg-accent-500 h-full rounded-full transition-all" :style="{ width: (group.members_count / group.max_members * 100) + '%' }"></div>
                </div>
            </Card>
        </div>

        <EmptyState v-if="!groups.data?.length" title="No study groups found" description="Create a group or change your filter" :icon="Users" />

        <!-- Create Modal -->
        <Modal :show="showCreate" @close="showCreate = false" title="Create Study Group">
            <form @submit.prevent="createGroup" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Group Name</label>
                    <input v-model="form.name" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Max Members</label>
                    <input v-model="form.max_members" type="number" min="2" max="50" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500" />
                </div>
                <div class="flex items-center gap-2">
                    <input v-model="form.is_public" type="checkbox" id="is_public" class="rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    <label for="is_public" class="text-sm text-surface-700">Public (anyone can join)</label>
                </div>
                <div class="flex justify-end gap-3">
                    <Button @click="showCreate = false" variant="secondary">Cancel</Button>
                    <Button type="submit" variant="primary" :disabled="form.processing">Create Group</Button>
                </div>
            </form>
        </Modal>
    </div>
</template>

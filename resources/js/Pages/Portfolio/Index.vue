<template>
    <Head title="My Portfolio" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-display font-bold text-surface-900">My Portfolio</h1>
            <div class="flex gap-2">
                <a href="/portfolio/export" class="px-4 py-2 bg-surface-100 text-surface-700 rounded-lg text-sm font-medium hover:bg-surface-200">
                    <Download class="w-4 h-4 inline mr-1" /> Export PDF
                </a>
                <button @click="showAdd = true" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    <Plus class="w-4 h-4 inline mr-1" /> Add Item
                </button>
            </div>
        </div>

        <!-- Portfolio Items -->
        <div v-if="items.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="font-semibold text-surface-900">{{ item.title }}</h3>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium capitalize', typeColor(item.type)]">{{ item.type.replace(/_/g, ' ') }}</span>
                    </div>
                    <div class="flex gap-1">
                        <button @click="editItem(item)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-600">
                            <Pencil class="w-3.5 h-3.5" />
                        </button>
                        <button @click="deleteItem(item)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <p v-if="item.description" class="text-sm text-surface-500 mb-3 line-clamp-3">{{ item.description }}</p>

                <div v-if="item.skills_demonstrated?.length" class="flex flex-wrap gap-1 mb-3">
                    <span v-for="(s, i) in item.skills_demonstrated" :key="i" class="px-1.5 py-0.5 bg-primary-50 text-primary-700 rounded text-[10px] font-medium">{{ s }}</span>
                </div>

                <div class="flex items-center justify-between text-xs text-surface-400">
                    <span>{{ item.enrollment?.track?.title }}</span>
                    <div class="flex items-center gap-2">
                        <span v-if="item.sponsor_score != null" class="font-medium text-accent-600">{{ item.sponsor_score }}/5</span>
                        <span :class="item.is_public ? 'text-success-600' : 'text-surface-400'">
                            {{ item.is_public ? 'Public' : 'Private' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <Briefcase class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <h2 class="text-lg font-semibold text-surface-700 mb-2">Build Your Portfolio</h2>
            <p class="text-sm text-surface-500 mb-4">Showcase your internship work by adding deliverables and reflections.</p>
            <button @click="showAdd = true" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                Add Your First Item
            </button>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showAdd || editingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">{{ editingItem ? 'Edit' : 'Add' }} Portfolio Item</h3>
                <form @submit.prevent="submitItem" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                        <input v-model="itemForm.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                        <select v-model="itemForm.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                            <option value="deliverable">Deliverable</option>
                            <option value="presentation">Presentation</option>
                            <option value="report">Report</option>
                            <option value="case_study">Case Study</option>
                            <option value="reflection">Reflection</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                        <textarea v-model="itemForm.description" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
                    </div>
                    <div v-if="!editingItem">
                        <label class="block text-sm font-medium text-surface-700 mb-1">File URL</label>
                        <input v-model="itemForm.file_url" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="https://..." />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Skills (comma-separated)</label>
                        <input v-model="skillsInput" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="itemForm.is_public" type="checkbox" id="is_public" class="rounded border-surface-300 text-primary-600" />
                        <label for="is_public" class="text-sm text-surface-700">Make public</label>
                    </div>
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" @click="closeModal" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                            {{ editingItem ? 'Save' : 'Add' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Plus, Download, Pencil, Trash2, Briefcase } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    items: { type: Array, default: () => [] },
});

const showAdd = ref(false);
const editingItem = ref(null);
const skillsInput = ref('');

const itemForm = useForm({
    enrollment_id: null,
    title: '',
    description: '',
    type: 'deliverable',
    file_url: '',
    skills_demonstrated: [],
    is_public: false,
});

function editItem(item) {
    editingItem.value = item;
    itemForm.title = item.title;
    itemForm.description = item.description || '';
    itemForm.type = item.type;
    itemForm.skills_demonstrated = item.skills_demonstrated || [];
    itemForm.is_public = item.is_public;
    skillsInput.value = (item.skills_demonstrated || []).join(', ');
}

function closeModal() {
    showAdd.value = false;
    editingItem.value = null;
    itemForm.reset();
    skillsInput.value = '';
}

function submitItem() {
    itemForm.skills_demonstrated = skillsInput.value.split(',').map(s => s.trim()).filter(Boolean);

    if (editingItem.value) {
        itemForm.put(`/portfolio/${editingItem.value.id}`, { onSuccess: closeModal });
    } else {
        itemForm.post('/portfolio', { onSuccess: closeModal });
    }
}

function deleteItem(item) {
    if (!confirm(`Remove "${item.title}" from portfolio?`)) return;
    router.delete(`/portfolio/${item.id}`);
}

function typeColor(type) {
    const map = {
        deliverable: 'bg-primary-50 text-primary-700',
        presentation: 'bg-accent-50 text-accent-700',
        report: 'bg-warning-50 text-warning-700',
        case_study: 'bg-success-50 text-success-700',
        reflection: 'bg-surface-100 text-surface-600',
    };
    return map[type] || 'bg-surface-100 text-surface-500';
}
</script>

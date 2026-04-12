<template>
    <Head title="Category Management" />

    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Categories</h1>
            <p class="text-surface-500 mt-1 text-sm">Manage course categories</p>
        </div>

        <!-- Add Category -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
            <form @submit.prevent="addCategory" class="flex flex-col sm:flex-row gap-3">
                <Input v-model="createForm.name" placeholder="Category name" class="flex-1" :error="createForm.errors.name" />
                <Input v-model="createForm.description" placeholder="Description (optional)" class="flex-1" />
                <Input v-model="createForm.icon" placeholder="Icon name" class="w-32" />
                <Button type="submit" variant="primary" size="md" :disabled="createForm.processing || !createForm.name">
                    <Plus class="w-4 h-4 mr-1" /> Add
                </Button>
            </form>
        </div>

        <!-- Categories List -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div v-if="categories.length" class="divide-y divide-surface-100">
                <div
                    v-for="(cat, i) in categories"
                    :key="cat.id"
                    class="flex items-center justify-between px-5 py-4 hover:bg-surface-50 transition-colors"
                >
                    <div class="flex items-center gap-4">
                        <!-- Reorder -->
                        <div class="flex flex-col gap-0.5 text-surface-300">
                            <button class="hover:text-surface-600 transition-colors" @click="moveCategory(i, -1)" :disabled="i === 0">
                                <ChevronUp class="w-4 h-4" />
                            </button>
                            <button class="hover:text-surface-600 transition-colors" @click="moveCategory(i, 1)" :disabled="i === categories.length - 1">
                                <ChevronDown class="w-4 h-4" />
                            </button>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-surface-900 text-sm">{{ cat.name }}</h3>
                                <Badge v-if="!cat.is_active" variant="warning" size="xs">Inactive</Badge>
                            </div>
                            <p v-if="cat.description" class="text-xs text-surface-400 mt-0.5">{{ cat.description }}</p>
                            <p class="text-xs text-surface-400">{{ cat.courses_count || 0 }} courses</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600 transition-colors"
                            @click="startEdit(cat)"
                        >
                            <Pencil class="w-4 h-4" />
                        </button>
                        <button
                            class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-danger-600 transition-colors"
                            @click="deleteCategory(cat)"
                            :disabled="cat.courses_count > 0"
                            :class="{ 'opacity-30 cursor-not-allowed': cat.courses_count > 0 }"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="p-12 text-center">
                <FolderOpen class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No categories yet</p>
                <p class="text-sm text-surface-400 mt-1">Create your first category above.</p>
            </div>
        </div>

        <!-- Edit Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Edit Category</h3>
                <form @submit.prevent="updateCategory" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Name</label>
                        <Input v-model="editForm.name" :error="editForm.errors.name" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Description</label>
                        <textarea v-model="editForm.description" rows="2" class="w-full rounded-xl border-surface-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Icon</label>
                        <Input v-model="editForm.icon" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-surface-700">
                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                        Active
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="ghost" @click="showEditModal = false">Cancel</Button>
                        <Button type="submit" variant="primary" :disabled="editForm.processing">Save</Button>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Badge from '@/Components/UI/Badge.vue';
import Modal from '@/Components/UI/Modal.vue';
import { Plus, Pencil, Trash2, ChevronUp, ChevronDown, FolderOpen } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

// Create form
const createForm = useForm({ name: '', description: '', icon: '' });

const addCategory = () => {
    createForm.post('/admin/categories', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

// Edit form
const showEditModal = ref(false);
const editingCategory = ref(null);
const editForm = useForm({ name: '', description: '', icon: '', is_active: true });

const startEdit = (cat) => {
    editingCategory.value = cat;
    editForm.name = cat.name;
    editForm.description = cat.description || '';
    editForm.icon = cat.icon || '';
    editForm.is_active = cat.is_active;
    showEditModal.value = true;
};

const updateCategory = () => {
    editForm.put(`/admin/categories/${editingCategory.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const deleteCategory = (cat) => {
    if (cat.courses_count > 0) return;
    if (confirm(`Delete category "${cat.name}"?`)) {
        router.delete(`/admin/categories/${cat.id}`, { preserveScroll: true });
    }
};

const moveCategory = (index, direction) => {
    const cats = [...props.categories];
    const newIndex = index + direction;
    [cats[index], cats[newIndex]] = [cats[newIndex], cats[index]];
    const order = cats.map(c => c.id);
    router.post('/admin/categories/reorder', { order }, { preserveScroll: true });
};
</script>

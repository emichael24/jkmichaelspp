<template>
    <Head title="Create Course" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <Link href="/admin/courses" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-400" />
            </Link>
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Create Course</h1>
                <p class="text-surface-500 text-sm mt-0.5">Add a new course to the platform</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Information -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Basic Information</h2>
                <div class="grid gap-5">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Title *</label>
                        <Input v-model="form.title" placeholder="e.g., PMP Certification Prep" :error="form.errors.title" />
                        <p v-if="form.errors.title" class="text-danger-500 text-xs mt-1">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Subtitle</label>
                        <Input v-model="form.subtitle" placeholder="A short description that appears in search" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500 text-sm"
                            placeholder="Full course description..."
                        />
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Category *</label>
                            <select v-model="form.category_id" class="w-full rounded-xl border-surface-200 text-sm">
                                <option value="">Select category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-danger-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Level *</label>
                            <select v-model="form.level" class="w-full rounded-xl border-surface-200 text-sm">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing & Type -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Pricing & Type</h2>
                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Type *</label>
                        <select v-model="form.type" class="w-full rounded-xl border-surface-200 text-sm">
                            <option value="free">Free</option>
                            <option value="premium">Premium</option>
                            <option value="certified">Certified</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Duration (hours)</label>
                        <Input v-model="form.duration_hours" type="number" step="0.5" min="0" placeholder="e.g., 35" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Price (NGN)</label>
                        <Input v-model="form.price" type="number" min="0" placeholder="0 for free courses" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Discount Price</label>
                        <Input v-model="form.discount_price" type="number" min="0" placeholder="Optional sale price" />
                    </div>
                </div>
            </div>

            <!-- Thumbnail -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Thumbnail</h2>
                <div class="flex items-center gap-5">
                    <div class="w-32 h-20 rounded-xl bg-surface-100 overflow-hidden flex items-center justify-center border border-dashed border-surface-300">
                        <img v-if="thumbnailPreview" :src="thumbnailPreview" class="w-full h-full object-cover" />
                        <ImageIcon v-else class="w-8 h-8 text-surface-300" />
                    </div>
                    <div>
                        <input
                            ref="thumbnailInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleThumbnail"
                        />
                        <Button type="button" variant="secondary" size="sm" @click="$refs.thumbnailInput.click()">
                            Choose Image
                        </Button>
                        <p class="text-xs text-surface-400 mt-1">Recommended: 800x500px, max 2MB</p>
                    </div>
                </div>
            </div>

            <!-- Learning Outcomes -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Learning Outcomes</h2>
                <div class="space-y-3">
                    <div v-for="(outcome, i) in form.learning_outcomes" :key="i" class="flex items-center gap-2">
                        <Input v-model="form.learning_outcomes[i]" :placeholder="`Outcome ${i + 1}`" class="flex-1" />
                        <button type="button" @click="form.learning_outcomes.splice(i, 1)" class="p-2 text-surface-400 hover:text-danger-500">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <Button type="button" variant="ghost" size="sm" @click="form.learning_outcomes.push('')">
                        <Plus class="w-4 h-4 mr-1" /> Add Outcome
                    </Button>
                </div>
            </div>

            <!-- Prerequisites -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Prerequisites</h2>
                <div class="space-y-3">
                    <div v-for="(prereq, i) in form.prerequisites" :key="i" class="flex items-center gap-2">
                        <Input v-model="form.prerequisites[i]" :placeholder="`Prerequisite ${i + 1}`" class="flex-1" />
                        <button type="button" @click="form.prerequisites.splice(i, 1)" class="p-2 text-surface-400 hover:text-danger-500">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <Button type="button" variant="ghost" size="sm" @click="form.prerequisites.push('')">
                        <Plus class="w-4 h-4 mr-1" /> Add Prerequisite
                    </Button>
                </div>
            </div>

            <!-- Tags -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-surface-900 mb-5">Tags</h2>
                <div class="flex flex-wrap gap-2 mb-3">
                    <span
                        v-for="(tag, i) in form.tags"
                        :key="i"
                        class="inline-flex items-center gap-1 bg-primary-50 text-primary-700 text-xs font-medium px-2.5 py-1 rounded-full"
                    >
                        {{ tag }}
                        <button type="button" @click="form.tags.splice(i, 1)">
                            <X class="w-3 h-3" />
                        </button>
                    </span>
                </div>
                <div class="flex gap-2">
                    <Input v-model="newTag" placeholder="Add a tag..." @keydown.enter.prevent="addTag" class="flex-1" />
                    <Button type="button" variant="secondary" size="sm" @click="addTag">Add</Button>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3">
                <Link href="/admin/courses">
                    <Button type="button" variant="ghost">Cancel</Button>
                </Link>
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating...' : 'Create Course' }}
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { ArrowLeft, Plus, X, Image as ImageIcon } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const form = useForm({
    title: '',
    subtitle: '',
    description: '',
    category_id: '',
    level: 'beginner',
    type: 'free',
    duration_hours: '',
    price: '',
    discount_price: '',
    learning_outcomes: [''],
    prerequisites: [],
    tags: [],
    thumbnail: null,
});

const newTag = ref('');
const thumbnailPreview = ref(null);

const addTag = () => {
    const tag = newTag.value.trim();
    if (tag && !form.tags.includes(tag)) {
        form.tags.push(tag);
        newTag.value = '';
    }
};

const handleThumbnail = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.thumbnail = file;
        thumbnailPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    // Filter empty outcomes/prerequisites
    form.learning_outcomes = form.learning_outcomes.filter(o => o.trim());
    form.prerequisites = form.prerequisites.filter(p => p.trim());

    form.post('/admin/courses', {
        forceFormData: true,
    });
};
</script>

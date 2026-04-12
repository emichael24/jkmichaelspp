<template>
    <Head title="Create Course" />

    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/facilitator/courses" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                <ArrowLeft class="w-5 h-5 text-surface-500" />
            </Link>
            <div>
                <h1 class="text-xl font-display font-bold text-surface-900">Create Course</h1>
                <p class="text-sm text-surface-500">Fill in course details. You can add modules and lessons after saving.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Basic Information</h2>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Course Title <span class="text-danger-600">*</span></label>
                    <Input v-model="form.title" placeholder="e.g. Introduction to Web Development" :error="form.errors.title" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Subtitle</label>
                    <Input v-model="form.subtitle" placeholder="A brief tagline for your course" :error="form.errors.subtitle" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Description <span class="text-danger-600">*</span></label>
                    <textarea
                        v-model="form.description"
                        rows="5"
                        placeholder="Describe what students will learn..."
                        class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                    ></textarea>
                    <p v-if="form.errors.description" class="text-xs text-danger-600 mt-1">{{ form.errors.description }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Category <span class="text-danger-600">*</span></label>
                        <select
                            v-model="form.category_id"
                            class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <option value="">Select category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <p v-if="form.errors.category_id" class="text-xs text-danger-600 mt-1">{{ form.errors.category_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Level <span class="text-danger-600">*</span></label>
                        <select v-model="form.level" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Type <span class="text-danger-600">*</span></label>
                        <select v-model="form.type" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="self_paced">Self-paced</option>
                            <option value="cohort_based">Cohort-based</option>
                            <option value="live">Live</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Duration (hours)</label>
                        <Input v-model="form.duration_hours" type="number" min="0" placeholder="e.g. 10" />
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
                <h2 class="font-semibold text-surface-800">Pricing</h2>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_free" v-model="isFree" class="rounded text-primary-600" />
                    <label for="is_free" class="text-sm font-medium text-surface-700">Free course</label>
                </div>

                <div v-if="!isFree" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Price (₦) <span class="text-danger-600">*</span></label>
                        <Input v-model="form.price" type="number" min="0" step="100" placeholder="e.g. 15000" :error="form.errors.price" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Discounted Price (₦)</label>
                        <Input v-model="form.discounted_price" type="number" min="0" step="100" placeholder="Optional" />
                    </div>
                </div>
            </div>

            <!-- Learning Outcomes -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
                <h2 class="font-semibold text-surface-800">Learning Outcomes</h2>
                <p class="text-sm text-surface-500">What will students be able to do after completing this course?</p>

                <div v-for="(outcome, i) in form.learning_outcomes" :key="i" class="flex gap-2">
                    <Input
                        v-model="form.learning_outcomes[i]"
                        :placeholder="`Outcome ${i + 1}`"
                        class="flex-1"
                    />
                    <button type="button" @click="removeOutcome(i)" class="p-2 text-surface-400 hover:text-danger-600 rounded-lg hover:bg-danger-50 flex-shrink-0">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <button type="button" @click="addOutcome" class="flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium">
                    <Plus class="w-4 h-4" />
                    Add outcome
                </button>
            </div>

            <!-- Tags -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
                <h2 class="font-semibold text-surface-800">Tags</h2>
                <Input
                    v-model="tagsInput"
                    placeholder="Comma-separated tags e.g. javascript, web, frontend"
                    @blur="parseTags"
                />
                <div v-if="form.tags.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="(tag, i) in form.tags"
                        :key="i"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-primary-50 text-primary-700 rounded-full text-xs font-medium"
                    >
                        {{ tag }}
                        <button type="button" @click="removeTag(i)" class="hover:text-primary-900">
                            <X class="w-3 h-3" />
                        </button>
                    </span>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3">
                <Link href="/facilitator/courses">
                    <Button variant="ghost">Cancel</Button>
                </Link>
                <Button type="submit" variant="primary" :disabled="form.processing">
                    <span v-if="form.processing">Saving...</span>
                    <span v-else>Save Course</span>
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const isFree = ref(true);
const tagsInput = ref('');

const form = useForm({
    title: '',
    subtitle: '',
    description: '',
    category_id: '',
    level: 'beginner',
    type: 'self_paced',
    duration_hours: '',
    price: '',
    discounted_price: '',
    learning_outcomes: [''],
    tags: [],
});

watch(isFree, (val) => {
    if (val) { form.price = ''; form.discounted_price = ''; }
});

function addOutcome() { form.learning_outcomes.push(''); }
function removeOutcome(i) { form.learning_outcomes.splice(i, 1); }

function parseTags() {
    const parsed = tagsInput.value.split(',').map(t => t.trim()).filter(Boolean);
    form.tags = [...new Set([...form.tags, ...parsed])];
    tagsInput.value = '';
}

function removeTag(i) { form.tags.splice(i, 1); }

function submit() {
    form.learning_outcomes = form.learning_outcomes.filter(o => o.trim());
    form.post('/facilitator/courses');
}
</script>

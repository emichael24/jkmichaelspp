<template>
    <Head :title="`Weekly Plans – ${cohort.name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/facilitator/cohorts/${cohort.id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Weekly Plans</h1>
                    <p class="text-sm text-surface-500">{{ cohort.name }} · {{ cohort.course?.title }}</p>
                </div>
            </div>
            <button @click="showAddWeek = true" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <Plus class="w-4 h-4" />
                Add Week
            </button>
        </div>

        <!-- AI Suggestions -->
        <div v-if="suggestions.length" class="bg-accent-50 border border-accent-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <Sparkles class="w-5 h-5 text-accent-600 shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-accent-800 mb-2">Suggested Items (Based on Cohort Weaknesses)</p>
                    <div class="space-y-1.5">
                        <div v-for="s in suggestions" :key="`${s.type}-${s.id}`" class="flex items-center justify-between bg-white rounded-lg p-2.5">
                            <div>
                                <p class="text-sm font-medium text-surface-900">{{ s.title }}</p>
                                <p class="text-xs text-surface-500">{{ s.reason }}</p>
                            </div>
                            <span class="px-2 py-0.5 bg-accent-100 text-accent-700 text-[10px] rounded-full font-semibold uppercase">{{ s.type }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Plan Cards -->
        <div v-if="plans.length" class="space-y-6">
            <div v-for="plan in plans" :key="plan.id" class="bg-white rounded-xl border border-surface-200/60 overflow-hidden">
                <!-- Week header -->
                <div class="flex items-center justify-between px-6 py-4 bg-surface-50 border-b border-surface-100">
                    <div>
                        <h3 class="font-semibold text-surface-900">
                            Week {{ plan.week_number }}
                            <span v-if="plan.title" class="text-surface-500 font-normal">– {{ plan.title }}</span>
                        </h3>
                        <p class="text-xs text-surface-400 mt-0.5">
                            {{ formatDate(plan.start_date) }} – {{ formatDate(plan.end_date) }}
                            <span v-if="isCurrentWeek(plan)" class="ml-2 px-1.5 py-0.5 bg-success-50 text-success-700 text-[10px] rounded-full font-semibold">CURRENT</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="openAddItem(plan)"
                            class="text-xs font-medium text-primary-600 hover:text-primary-700 px-2 py-1 rounded hover:bg-primary-50"
                        >+ Add Item</button>
                        <button
                            @click="confirmDeleteWeek(plan)"
                            class="text-xs text-surface-400 hover:text-danger-600 px-2 py-1 rounded hover:bg-danger-50"
                        ><Trash2 class="w-3.5 h-3.5" /></button>
                    </div>
                </div>

                <!-- Items -->
                <div class="p-4">
                    <div v-if="plan.items && plan.items.length" class="space-y-2">
                        <div
                            v-for="(item, index) in plan.items"
                            :key="item.id"
                            class="flex items-center gap-3 p-3 rounded-lg bg-surface-50 hover:bg-surface-100 transition-colors group"
                        >
                            <div class="cursor-move text-surface-300 hover:text-surface-500">
                                <GripVertical class="w-4 h-4" />
                            </div>
                            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', itemTypeBg(item.itemable_type)]">
                                <component :is="itemTypeIcon(item.itemable_type)" class="w-4 h-4 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-surface-900 truncate">{{ itemLabel(item) }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[10px] text-surface-400 uppercase font-semibold">{{ itemTypeName(item.itemable_type) }}</span>
                                    <span v-if="item.is_mandatory" class="text-[10px] text-danger-600 font-semibold">REQUIRED</span>
                                    <span v-if="item.due_date" class="text-[10px] text-surface-400">Due: {{ formatDate(item.due_date) }}</span>
                                </div>
                            </div>
                            <button
                                @click="removeItem(item)"
                                class="opacity-0 group-hover:opacity-100 text-surface-400 hover:text-danger-600 transition-all"
                            ><X class="w-4 h-4" /></button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-surface-400 text-center py-4">No items in this week yet. Click "+ Add Item" to get started.</p>
                </div>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
            <CalendarDays class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500">No weekly plans yet. Click "Add Week" to create one.</p>
        </div>

        <!-- Add Week Modal -->
        <div v-if="showAddWeek" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAddWeek = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Add Week</h3>
                <form @submit.prevent="addWeek">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Week Number</label>
                            <input v-model="weekForm.week_number" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Title (optional)</label>
                            <input v-model="weekForm.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="e.g. Integration & Scope" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Start Date</label>
                                <input v-model="weekForm.start_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">End Date</label>
                                <input v-model="weekForm.end_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAddWeek = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="weekForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            Add Week
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Item Modal -->
        <div v-if="addItemPlan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="addItemPlan = null">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Add Item to Week {{ addItemPlan.week_number }}</h3>
                <form @submit.prevent="addItem">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                            <select v-model="itemForm.itemable_type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" @change="onTypeChange">
                                <option value="App\Models\Lesson">Lesson</option>
                                <option value="App\Models\Quiz">Quiz</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Item</label>
                            <select v-model="itemForm.itemable_id" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                <option value="">Select...</option>
                                <option v-for="opt in availableItems" :key="opt.id" :value="opt.id">{{ opt.title }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="itemForm.is_mandatory" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                                Mandatory
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Due Date (optional)</label>
                            <input v-model="itemForm.due_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="addItemPlan = null" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, Trash2, X, GripVertical,
    CalendarDays, BookOpen, ClipboardCheck, Sparkles, FileText,
} from 'lucide-vue-next';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    cohort: { type: Object, required: true },
    plans: { type: Array, default: () => [] },
    lessons: { type: Array, default: () => [] },
    quizzes: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
});

const showAddWeek = ref(false);
const addItemPlan = ref(null);

const weekForm = useForm({
    week_number: (props.plans.length || 0) + 1,
    title: '',
    start_date: '',
    end_date: '',
});

const itemForm = useForm({
    itemable_type: 'App\\Models\\Lesson',
    itemable_id: '',
    is_mandatory: true,
    due_date: '',
});

const availableItems = computed(() => {
    if (itemForm.itemable_type === 'App\\Models\\Lesson') return props.lessons;
    if (itemForm.itemable_type === 'App\\Models\\Quiz') return props.quizzes;
    return [];
});

function onTypeChange() {
    itemForm.itemable_id = '';
}

function addWeek() {
    weekForm.post(`/facilitator/cohorts/${props.cohort.id}/plans`, {
        onSuccess: () => {
            showAddWeek.value = false;
            weekForm.reset();
            weekForm.week_number = (props.plans.length || 0) + 2;
        },
    });
}

function openAddItem(plan) {
    addItemPlan.value = plan;
    itemForm.reset();
    itemForm.itemable_type = 'App\\Models\\Lesson';
    itemForm.is_mandatory = true;
}

function addItem() {
    itemForm.post(`/facilitator/cohorts/${props.cohort.id}/plans/${addItemPlan.value.id}/items`, {
        onSuccess: () => {
            addItemPlan.value = null;
            itemForm.reset();
        },
    });
}

function removeItem(item) {
    if (confirm('Remove this item from the plan?')) {
        router.delete(`/facilitator/cohorts/${props.cohort.id}/items/${item.id}`);
    }
}

function confirmDeleteWeek(plan) {
    if (confirm(`Delete Week ${plan.week_number}? This will remove all items in this week.`)) {
        router.delete(`/facilitator/cohorts/${props.cohort.id}/plans/${plan.id}`);
    }
}

function isCurrentWeek(plan) {
    const today = new Date().toISOString().split('T')[0];
    return plan.start_date <= today && plan.end_date >= today;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function itemTypeName(type) {
    if (type?.includes('Lesson')) return 'Lesson';
    if (type?.includes('Quiz')) return 'Quiz';
    if (type?.includes('MockExam')) return 'Mock Exam';
    return 'Item';
}

function itemTypeBg(type) {
    if (type?.includes('Lesson')) return 'bg-primary-500';
    if (type?.includes('Quiz')) return 'bg-warning-500';
    if (type?.includes('MockExam')) return 'bg-accent-500';
    return 'bg-surface-400';
}

function itemTypeIcon(type) {
    if (type?.includes('Lesson')) return BookOpen;
    if (type?.includes('Quiz')) return ClipboardCheck;
    if (type?.includes('MockExam')) return FileText;
    return FileText;
}

function itemLabel(item) {
    // The item might just have the ID; a better version would eager-load the itemable name
    return `${itemTypeName(item.itemable_type)} #${item.itemable_id}`;
}
</script>

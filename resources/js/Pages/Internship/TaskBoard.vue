<template>
    <Head :title="`Task Board – ${enrollment.track?.title || 'Internship'}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/internship" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Task Board</h1>
                    <p class="text-sm text-surface-500">{{ enrollment.track?.title }} · {{ enrollment.project_title || '' }}</p>
                </div>
            </div>
            <!-- Filter -->
            <div class="flex items-center gap-2">
                <button
                    v-for="f in filterOptions"
                    :key="f.value"
                    @click="activeFilter = f.value"
                    :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors', activeFilter === f.value ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-600 hover:bg-surface-200']"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <!-- Kanban Board -->
        <div class="overflow-x-auto pb-4">
            <div class="flex gap-4 min-w-max">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    class="w-72 shrink-0"
                >
                    <!-- Column header -->
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', col.color]"></div>
                            <h3 class="text-xs font-semibold text-surface-600 uppercase tracking-wider">{{ col.label }}</h3>
                        </div>
                        <span class="text-xs text-surface-400 bg-surface-100 px-2 py-0.5 rounded-full">
                            {{ filteredTasks(col.key).length }}
                        </span>
                    </div>

                    <!-- Task cards -->
                    <div class="space-y-2 min-h-[200px] bg-surface-50 rounded-xl p-2">
                        <div
                            v-for="task in filteredTasks(col.key)"
                            :key="task.id"
                            class="bg-white rounded-lg border border-surface-200/60 p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                            @click="openTask(task)"
                        >
                            <div class="flex items-start justify-between mb-1">
                                <p class="text-sm font-medium text-surface-900 line-clamp-2 flex-1">{{ task.title }}</p>
                                <GripVertical class="w-4 h-4 text-surface-300 shrink-0 ml-1" />
                            </div>

                            <p v-if="task.description" class="text-xs text-surface-500 line-clamp-2 mb-2">{{ task.description }}</p>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] font-semibold', typeBadge(task.type)]">{{ task.type }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] font-semibold', priorityBadge(task.priority)]">{{ task.priority }}</span>
                                </div>
                                <span v-if="task.due_date" :class="['text-[10px] font-medium', isOverdue(task) ? 'text-danger-600' : 'text-surface-400']">
                                    {{ formatDate(task.due_date) }}
                                </span>
                            </div>

                            <!-- Milestone tag -->
                            <div v-if="task.milestone" class="mt-2">
                                <span class="text-[10px] px-1.5 py-0.5 bg-primary-50 text-primary-600 rounded font-medium">
                                    {{ task.milestone.title }}
                                </span>
                            </div>
                        </div>

                        <div v-if="!filteredTasks(col.key).length" class="py-8 text-center">
                            <p class="text-xs text-surface-400">No tasks</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Detail Modal -->
        <div v-if="selectedTask" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="selectedTask = null">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-surface-900">{{ selectedTask.title }}</h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span :class="['px-2 py-0.5 rounded text-xs font-medium', typeBadge(selectedTask.type)]">{{ selectedTask.type }}</span>
                            <span :class="['px-2 py-0.5 rounded text-xs font-medium', priorityBadge(selectedTask.priority)]">{{ selectedTask.priority }}</span>
                            <span v-if="selectedTask.due_date" class="text-xs text-surface-400">Due {{ formatDate(selectedTask.due_date) }}</span>
                        </div>
                    </div>
                    <button @click="selectedTask = null" class="p-1 text-surface-400 hover:text-surface-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <p v-if="selectedTask.description" class="text-sm text-surface-600 mb-4">{{ selectedTask.description }}</p>

                <div v-if="selectedTask.milestone" class="mb-4">
                    <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Milestone</p>
                    <p class="text-sm text-surface-700">{{ selectedTask.milestone.title }}</p>
                </div>

                <!-- Move to status -->
                <div>
                    <p class="text-xs font-semibold text-surface-500 uppercase mb-2">Move To</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="col in columns"
                            :key="col.key"
                            @click="moveTask(selectedTask, col.key)"
                            :disabled="selectedTask.status === col.key"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                selectedTask.status === col.key ? 'bg-primary-600 text-white' : 'bg-surface-100 text-surface-600 hover:bg-surface-200',
                            ]"
                        >
                            {{ col.label }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, GripVertical, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    enrollment: { type: Object, required: true },
    kanban: { type: Object, default: () => ({}) },
});

const activeFilter = ref('all');
const selectedTask = ref(null);

const filterOptions = [
    { label: 'All', value: 'all' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Monthly', value: 'monthly' },
];

const columns = [
    { key: 'backlog', label: 'Backlog', color: 'bg-surface-400' },
    { key: 'this_week', label: 'This Week', color: 'bg-primary-400' },
    { key: 'today', label: 'Today', color: 'bg-accent-400' },
    { key: 'in_progress', label: 'In Progress', color: 'bg-warning-400' },
    { key: 'in_review', label: 'In Review', color: 'bg-xp-400' },
    { key: 'needs_revision', label: 'Needs Revision', color: 'bg-danger-400' },
    { key: 'done', label: 'Done', color: 'bg-success-400' },
];

function filteredTasks(colKey) {
    const tasks = props.kanban[colKey] || [];
    if (activeFilter.value === 'all') return tasks;
    return tasks.filter(t => t.type === activeFilter.value);
}

function openTask(task) {
    selectedTask.value = { ...task };
}

function moveTask(task, newStatus) {
    router.put(`/internship/tasks/${task.id}/status`, { status: newStatus }, {
        onSuccess: () => { selectedTask.value = null; },
    });
}

function typeBadge(type) {
    const map = { daily: 'bg-primary-50 text-primary-700', weekly: 'bg-accent-50 text-accent-700', monthly: 'bg-warning-50 text-warning-700' };
    return map[type] || 'bg-surface-100 text-surface-600';
}

function priorityBadge(priority) {
    const map = { low: 'bg-surface-100 text-surface-600', medium: 'bg-primary-50 text-primary-600', high: 'bg-warning-50 text-warning-700', urgent: 'bg-danger-50 text-danger-700' };
    return map[priority] || map.medium;
}

function isOverdue(task) {
    return task.due_date && new Date(task.due_date) < new Date() && !['approved', 'done'].includes(task.status);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

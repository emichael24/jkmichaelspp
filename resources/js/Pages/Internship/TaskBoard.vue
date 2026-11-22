<template>
    <Head :title="`Task Board – ${enrollment.track?.title || 'Internship'}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="/internship" aria-label="Back to internship overview" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">Task Board</h1>
                    <p class="text-sm text-surface-500">{{ enrollment.track?.title }}<span v-if="enrollment.project_title"> · {{ enrollment.project_title }}</span></p>
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

        <div class="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-surface-200 bg-white p-4"><label for="task-stage" class="text-sm font-medium">Task stage<select id="task-stage" v-model="stageFilter" class="mt-2 block w-full min-w-44 rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm"><option value="">All stages</option><option v-for="column in columns" :key="column.key" :value="column.key">{{ column.label }}</option></select></label><p class="max-w-md text-xs leading-relaxed text-surface-500">Open a task to organize your work or submit it. Your sponsor moves reviewed work to Done.</p></div>
        <!-- Kanban Board -->
        <div class="overflow-x-auto pb-4">
            <div class="flex gap-4 min-w-max max-sm:min-w-0 max-sm:flex-col">
                <div
                    v-for="col in columns.filter(c => !stageFilter || c.key === stageFilter)"
                    :key="col.key"
                    class="w-72 shrink-0 max-sm:w-full"
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
                            class="bg-white rounded-lg border border-surface-200/60 p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer focus-visible:outline-2 focus-visible:outline-primary-500"
                            role="button" tabindex="0" :aria-label="`Open ${task.title}`" @keydown.enter="openTask(task)" @keydown.space.prevent="openTask(task)"
                            @click="openTask(task)"
                        >
                            <div class="flex items-start justify-between mb-1">
                                <p class="text-sm font-medium text-surface-900 line-clamp-2 flex-1">{{ task.title }}</p>
                                <ArrowUpRight class="w-4 h-4 text-surface-300 shrink-0 ml-1" />
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
        <Modal :open="!!selectedTask" :title="selectedTask?.title || 'Task details'" :closeable="!submissionForm.processing" @close="selectedTask = null"><div v-if="selectedTask">
                <p v-if="selectedTask.description" class="text-sm text-surface-600 mb-4">{{ selectedTask.description }}</p>

                <div v-if="selectedTask.milestone" class="mb-4">
                    <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Milestone</p>
                    <p class="text-sm text-surface-700">{{ selectedTask.milestone.title }}</p>
                </div>

                <p v-if="taskError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ taskError }}</p>
                <p v-if="['in_review', 'approved', 'done'].includes(selectedTask.status)" class="rounded-xl bg-surface-50 p-4 text-sm text-surface-600">Submitted work is managed through sponsor review. <a href="/internship/submissions" class="font-medium text-primary-700 underline">View submissions and feedback</a></p>
                <div v-else>
                    <p class="text-xs font-semibold text-surface-500 uppercase mb-2">Move To</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="col in columns.filter(c => ['backlog', 'this_week', 'today', 'in_progress'].includes(c.key))"
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
                <form v-if="!['in_review', 'approved', 'done'].includes(selectedTask.status)" @submit.prevent="submitWork" class="mt-6 space-y-4 border-t border-surface-100 pt-5"><h4 class="font-semibold">Submit your work</h4><p class="text-xs leading-relaxed text-surface-500">Send your work to your sponsor for review. Each resubmission keeps the previous version.</p><p v-for="error in submissionForm.errors" :key="error" role="alert" class="text-sm text-red-700">{{ error }}</p><label class="block text-sm font-medium">Work and reflection<textarea v-model="submissionForm.content" rows="5" maxlength="10000" class="mt-2 w-full rounded-xl border border-surface-200 p-3 font-normal" /></label><label class="block text-sm font-medium">Supporting file links<textarea v-model="fileLinks" rows="2" class="mt-2 w-full rounded-xl border border-surface-200 p-3 font-normal" placeholder="One https:// link per line, up to five" /><span class="mt-1 block text-xs font-normal text-surface-500">Use links your sponsor can access. Do not put passwords or access tokens in links.</span></label><label class="block text-sm font-medium">Time spent (minutes)<input v-model.number="submissionForm.time_log_minutes" type="number" min="0" max="10080" class="mt-2 block w-36 rounded-xl border border-surface-200 p-3" /></label><button :disabled="submissionForm.processing" class="w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ submissionForm.processing ? 'Submitting…' : 'Submit for review' }}</button></form>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/UI/Modal.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    enrollment: { type: Object, required: true },
    kanban: { type: Object, default: () => ({}) },
});

const activeFilter = ref('all');
const stageFilter = ref('');
const selectedTask = ref(null);
const submissionForm = useForm({ content: '', file_urls: [], time_log_minutes: null });
const fileLinks = ref('');
const taskError = ref('');

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
    taskError.value = ''; submissionForm.reset(); submissionForm.clearErrors(); fileLinks.value = '';
}

function moveTask(task, newStatus) {
    router.put(`/internship/tasks/${task.id}/status`, { status: newStatus }, {
        onSuccess: () => { selectedTask.value = null; },
        onError: errors => { taskError.value = Object.values(errors).join(' '); },
    });
}

function submitWork() {
    submissionForm.file_urls = fileLinks.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    submissionForm.post(`/internship/tasks/${selectedTask.value.id}/submit`, { onSuccess: () => { selectedTask.value = null; stageFilter.value = 'in_review'; } });
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

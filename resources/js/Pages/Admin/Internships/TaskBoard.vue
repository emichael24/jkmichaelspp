<template>
    <Head :title="`Tasks — ${enrollment.user?.first_name} ${enrollment.user?.last_name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a :href="`/admin/internships/${enrollment.track_id}`" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">
                        {{ enrollment.user?.first_name }} {{ enrollment.user?.last_name }}'s Task Board
                    </h1>
                    <p class="text-sm text-surface-500">
                        {{ enrollment.track?.title }} · {{ enrollment.project_title || 'No project title' }}
                    </p>
                </div>
            </div>
            <button @click="showAddTask = true" class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                <Plus class="w-4 h-4" />
                Add Task
            </button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-surface-900">{{ allTasks.length }}</p>
                <p class="text-xs text-surface-500">Total Tasks</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ doneTasks }}</p>
                <p class="text-xs text-surface-500">Completed</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ inProgressTasks }}</p>
                <p class="text-xs text-surface-500">In Progress</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/60 p-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ overdueTasks }}</p>
                <p class="text-xs text-surface-500">Overdue</p>
            </div>
        </div>

        <!-- Task List Table -->
        <div class="bg-white rounded-xl border border-surface-200/60 overflow-hidden">
            <div class="p-5 border-b border-surface-100">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-surface-900">Tasks</h2>
                    <div class="flex gap-2">
                        <select v-model="filterStatus" class="px-3 py-1.5 border border-surface-200 rounded-lg text-sm text-surface-700">
                            <option value="">All Statuses</option>
                            <option v-for="s in statusOptions" :key="s" :value="s">{{ formatStatus(s) }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="filteredTasks.length" class="divide-y divide-surface-100">
                <div v-for="task in filteredTasks" :key="task.id"
                     class="px-5 py-4 flex items-center justify-between hover:bg-surface-50/50 transition-colors">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <span :class="['w-2 h-2 rounded-full mt-2 shrink-0', priorityDot(task.priority)]"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-surface-900 truncate">{{ task.title }}</p>
                            <p v-if="task.description" class="text-xs text-surface-400 mt-0.5 truncate">{{ task.description }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span :class="['px-2 py-0.5 rounded text-[10px] font-medium', typeClass(task.type)]">{{ task.type }}</span>
                                <span v-if="task.milestone" class="text-[10px] text-surface-400">{{ task.milestone.title }}</span>
                                <span v-if="task.due_date" :class="['text-[10px]', isOverdue(task) ? 'text-red-600 font-medium' : 'text-surface-400']">
                                    Due {{ formatDate(task.due_date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0 ml-4">
                        <!-- Status dropdown -->
                        <select :value="task.status"
                                @change="updateTaskStatus(task, $event.target.value)"
                                :class="['px-2 py-1 rounded text-xs font-medium border-0 cursor-pointer', statusClass(task.status)]">
                            <option v-for="s in statusOptions" :key="s" :value="s">{{ formatStatus(s) }}</option>
                        </select>

                        <!-- Priority dropdown -->
                        <select :value="task.priority"
                                @change="updateTaskPriority(task, $event.target.value)"
                                :class="['px-2 py-1 rounded text-xs font-medium border-0 cursor-pointer', priorityClass(task.priority)]">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>

                        <!-- Edit -->
                        <button @click="startEdit(task)" class="p-1 text-surface-400 hover:text-primary-600">
                            <Pencil class="w-4 h-4" />
                        </button>

                        <!-- Delete -->
                        <button @click="deleteTask(task)" class="p-1 text-surface-400 hover:text-red-600">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="p-12 text-center">
                <ClipboardList class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="text-sm font-medium text-surface-500">No tasks yet</p>
                <p class="text-xs text-surface-400 mt-1">Click "Add Task" to assign work to this intern.</p>
            </div>
        </div>

        <!-- Add Task Modal -->
        <div v-if="showAddTask" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAddTask = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Add Task</h3>
                <form @submit.prevent="submitTask">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Title *</label>
                            <input v-model="taskForm.title" type="text" required
                                   class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="e.g. Draft project charter" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                            <textarea v-model="taskForm.description" rows="3"
                                      class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                      placeholder="What the intern should deliver..."></textarea>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Type *</label>
                                <select v-model="taskForm.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Status *</label>
                                <select v-model="taskForm.status" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option v-for="s in statusOptions" :key="s" :value="s">{{ formatStatus(s) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Priority *</label>
                                <select v-model="taskForm.priority" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Milestone</label>
                                <select v-model="taskForm.milestone_id" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option :value="null">— None —</option>
                                    <option v-for="ms in enrollment.track?.milestones" :key="ms.id" :value="ms.id">
                                        W{{ ms.week_number }}: {{ ms.title }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Due Date</label>
                                <input v-model="taskForm.due_date" type="date"
                                       class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAddTask = false"
                                class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="taskForm.processing"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ taskForm.processing ? 'Adding...' : 'Add Task' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Task Modal -->
        <div v-if="showEditTask" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showEditTask = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Edit Task</h3>
                <form @submit.prevent="submitEditTask">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Title *</label>
                            <input v-model="editTaskForm.title" type="text" required
                                   class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                            <textarea v-model="editTaskForm.description" rows="3"
                                      class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                                <select v-model="editTaskForm.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Status</label>
                                <select v-model="editTaskForm.status" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option v-for="s in statusOptions" :key="s" :value="s">{{ formatStatus(s) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Priority</label>
                                <select v-model="editTaskForm.priority" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Milestone</label>
                                <select v-model="editTaskForm.milestone_id" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option :value="null">— None —</option>
                                    <option v-for="ms in enrollment.track?.milestones" :key="ms.id" :value="ms.id">
                                        W{{ ms.week_number }}: {{ ms.title }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Due Date</label>
                                <input v-model="editTaskForm.due_date" type="date"
                                       class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showEditTask = false"
                                class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="editTaskForm.processing"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ editTaskForm.processing ? 'Saving...' : 'Save Changes' }}
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
import { ArrowLeft, Plus, Pencil, Trash2, ClipboardList } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    enrollment: { type: Object, required: true },
});

const showAddTask = ref(false);
const showEditTask = ref(false);
const editingTaskId = ref(null);
const filterStatus = ref('');

const statusOptions = ['backlog', 'this_week', 'today', 'in_progress', 'in_review', 'approved', 'needs_revision', 'done'];

const allTasks = computed(() => props.enrollment.tasks || []);
const filteredTasks = computed(() => {
    if (!filterStatus.value) return allTasks.value;
    return allTasks.value.filter(t => t.status === filterStatus.value);
});

const doneTasks = computed(() => allTasks.value.filter(t => ['approved', 'done'].includes(t.status)).length);
const inProgressTasks = computed(() => allTasks.value.filter(t => t.status === 'in_progress').length);
const overdueTasks = computed(() => allTasks.value.filter(t => isOverdue(t)).length);

const taskForm = useForm({
    title: '',
    description: '',
    type: 'weekly',
    status: 'backlog',
    priority: 'medium',
    milestone_id: null,
    due_date: '',
});

const editTaskForm = useForm({
    title: '',
    description: '',
    type: 'weekly',
    status: 'backlog',
    priority: 'medium',
    milestone_id: null,
    due_date: '',
});

function submitTask() {
    taskForm.post(`/admin/enrollments/${props.enrollment.id}/tasks`, {
        onSuccess: () => { showAddTask.value = false; taskForm.reset(); },
    });
}

function startEdit(task) {
    editingTaskId.value = task.id;
    editTaskForm.title = task.title;
    editTaskForm.description = task.description || '';
    editTaskForm.type = task.type;
    editTaskForm.status = task.status;
    editTaskForm.priority = task.priority;
    editTaskForm.milestone_id = task.milestone_id;
    editTaskForm.due_date = task.due_date ? task.due_date.split('T')[0] : '';
    showEditTask.value = true;
}

function submitEditTask() {
    editTaskForm.put(`/admin/tasks/${editingTaskId.value}`, {
        onSuccess: () => { showEditTask.value = false; },
    });
}

function updateTaskStatus(task, status) {
    router.put(`/admin/tasks/${task.id}`, { status }, { preserveScroll: true });
}

function updateTaskPriority(task, priority) {
    router.put(`/admin/tasks/${task.id}`, { priority }, { preserveScroll: true });
}

function deleteTask(task) {
    if (!confirm(`Delete task "${task.title}"?`)) return;
    router.delete(`/admin/tasks/${task.id}`, { preserveScroll: true });
}

function isOverdue(task) {
    if (!task.due_date) return false;
    if (['approved', 'done'].includes(task.status)) return false;
    return new Date(task.due_date) < new Date();
}

function formatStatus(s) {
    const map = {
        backlog: 'Backlog', this_week: 'This Week', today: 'Today',
        in_progress: 'In Progress', in_review: 'In Review', approved: 'Approved',
        needs_revision: 'Needs Revision', done: 'Done',
    };
    return map[s] || s;
}

function statusClass(s) {
    const map = {
        backlog: 'bg-surface-100 text-surface-600',
        this_week: 'bg-blue-50 text-blue-700',
        today: 'bg-amber-50 text-amber-700',
        in_progress: 'bg-indigo-50 text-indigo-700',
        in_review: 'bg-purple-50 text-purple-700',
        approved: 'bg-green-50 text-green-700',
        needs_revision: 'bg-red-50 text-red-700',
        done: 'bg-emerald-50 text-emerald-700',
    };
    return map[s] || 'bg-surface-100 text-surface-600';
}

function priorityDot(p) {
    return { low: 'bg-surface-300', medium: 'bg-blue-500', high: 'bg-orange-500', urgent: 'bg-red-600' }[p] || 'bg-surface-300';
}

function priorityClass(p) {
    return { low: 'bg-surface-100 text-surface-600', medium: 'bg-blue-50 text-blue-700', high: 'bg-orange-50 text-orange-700', urgent: 'bg-red-50 text-red-700' }[p] || 'bg-surface-100 text-surface-600';
}

function typeClass(t) {
    return { daily: 'bg-green-100 text-green-700', weekly: 'bg-blue-100 text-blue-700', monthly: 'bg-purple-100 text-purple-700' }[t] || 'bg-surface-100 text-surface-600';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

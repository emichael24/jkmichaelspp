<template>
    <Head :title="track.title" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/admin/internships" class="p-2 rounded-lg hover:bg-surface-100 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-surface-500" />
                </a>
                <div>
                    <h1 class="text-xl font-display font-bold text-surface-900">{{ track.title }}</h1>
                    <p class="text-sm text-surface-500">{{ track.duration_weeks }} weeks · {{ formatPrice(track.price, track.currency) }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button @click="showEdit = true" class="px-4 py-2 bg-surface-100 text-surface-700 rounded-lg text-sm font-medium hover:bg-surface-200">Edit</button>
                <button @click="deleteTrack" class="px-4 py-2 bg-danger-50 text-danger-700 rounded-lg text-sm font-medium hover:bg-danger-100">Delete</button>
            </div>
        </div>

        <!-- Info -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5 space-y-3">
            <p v-if="track.description" class="text-sm text-surface-600">{{ track.description }}</p>
            <p v-if="track.expectations" class="text-sm text-surface-600"><span class="font-medium">Expectations:</span> {{ track.expectations }}</p>
            <div v-if="track.skills_covered?.length" class="flex flex-wrap gap-1">
                <span v-for="(s, i) in track.skills_covered" :key="i" class="px-2 py-1 bg-primary-50 text-primary-700 rounded-full text-xs font-medium">{{ s }}</span>
            </div>
            <div v-if="track.tools_required?.length" class="flex flex-wrap gap-1">
                <span v-for="(t, i) in track.tools_required" :key="i" class="px-2 py-1 bg-accent-50 text-accent-700 rounded-full text-xs font-medium">{{ t }}</span>
            </div>
        </div>

        <!-- Milestones -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-surface-900">Milestones</h2>
                <button @click="showAddMilestone = true" class="text-xs text-primary-600 font-medium hover:underline">+ Add Milestone</button>
            </div>

            <div v-if="track.milestones?.length" class="space-y-3">
                <div v-for="ms in track.milestones" :key="ms.id" class="flex items-start justify-between p-3 bg-surface-50 rounded-lg">
                    <div class="flex items-start gap-3">
                        <span class="text-xs font-bold text-primary-600 bg-primary-50 px-2 py-1 rounded">W{{ ms.week_number }}</span>
                        <div>
                            <p class="text-sm font-medium text-surface-900">{{ ms.title }}</p>
                            <p v-if="ms.description" class="text-xs text-surface-500 mt-0.5">{{ ms.description }}</p>
                            <div v-if="ms.deliverables?.length" class="flex flex-wrap gap-1 mt-1">
                                <span v-for="(d, di) in ms.deliverables" :key="di" class="px-1.5 py-0.5 bg-white rounded text-[10px] text-surface-500 border border-surface-200">{{ d }}</span>
                            </div>
                        </div>
                    </div>
                    <button @click="deleteMilestone(ms)" class="p-1 text-surface-400 hover:text-danger-600">
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
            <p v-else class="text-sm text-surface-400 italic">No milestones yet.</p>
        </div>

        <!-- Enrollments -->
        <div class="bg-white rounded-xl border border-surface-200/60 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-surface-900">Enrollments ({{ enrollments.length }})</h2>
                <button @click="showEnroll = true" class="text-xs text-primary-600 font-medium hover:underline">+ Enroll Learner</button>
            </div>

            <div v-if="enrollments.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-surface-500 uppercase tracking-wider border-b border-surface-100">
                            <th class="pb-2 font-medium">Learner</th>
                            <th class="pb-2 font-medium">Sponsor</th>
                            <th class="pb-2 font-medium">Project</th>
                            <th class="pb-2 font-medium">Dates</th>
                            <th class="pb-2 font-medium">Tasks</th>
                            <th class="pb-2 font-medium">Status</th>
                            <th class="pb-2 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="enr in enrollments" :key="enr.id">
                            <td class="py-2">{{ enr.user?.first_name }} {{ enr.user?.last_name }}</td>
                            <td class="py-2 text-surface-500">{{ enr.sponsor?.first_name || '—' }} {{ enr.sponsor?.last_name || '' }}</td>
                            <td class="py-2 text-surface-500">{{ enr.project_title || '—' }}</td>
                            <td class="py-2 text-xs text-surface-400">{{ formatDate(enr.start_date) }} – {{ formatDate(enr.end_date) }}</td>
                            <td class="py-2">
                                <span class="text-xs font-medium">
                                    <span class="text-green-600">{{ enr.done_tasks_count || 0 }}</span>
                                    <span class="text-surface-400">/{{ enr.tasks_count || 0 }}</span>
                                </span>
                            </td>
                            <td class="py-2">
                                <select :value="enr.status" @change="updateEnrollmentStatus(enr, $event.target.value)"
                                        :class="['px-2 py-1 rounded text-xs font-medium border-0', enrollStatusClass(enr.status)]">
                                    <option value="enrolled">Enrolled</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="withdrawn">Withdrawn</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </td>
                            <td class="py-2">
                                <div class="flex items-center gap-2">
                                    <a :href="`/admin/enrollments/${enr.id}/tasks`" class="px-2.5 py-1 bg-primary-50 text-primary-700 rounded text-xs font-medium hover:bg-primary-100 transition-colors">View Board</a>
                                    <button @click="openAddTask(enr)" class="px-2.5 py-1 bg-green-50 text-green-700 rounded text-xs font-medium hover:bg-green-100 transition-colors">+ Task</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-surface-400 italic">No enrollments yet.</p>
        </div>

        <!-- Add Milestone Modal -->
        <div v-if="showAddMilestone" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAddMilestone = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Add Milestone</h3>
                <form @submit.prevent="submitMilestone">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Title</label>
                            <input v-model="msForm.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Week Number</label>
                            <input v-model.number="msForm.week_number" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                            <textarea v-model="msForm.description" rows="2" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" @click="showAddMilestone = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="msForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Add</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enroll Learner Modal -->
        <div v-if="showEnroll" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showEnroll = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Enroll Learner</h3>
                <form @submit.prevent="submitEnrollment">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Learner User ID</label>
                            <input v-model.number="enrollForm.user_id" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="User ID" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Sponsor User ID (optional)</label>
                            <input v-model.number="enrollForm.sponsor_id" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Project Title</label>
                            <input v-model="enrollForm.project_title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Start Date</label>
                                <input v-model="enrollForm.start_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">End Date</label>
                                <input v-model="enrollForm.end_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" @click="showEnroll = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="enrollForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Enroll</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Track Modal -->
        <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showEdit = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Edit Track</h3>
                <form @submit.prevent="submitEdit">
                    <label class="block text-sm font-medium">Review criteria<input v-model="rubricInput" class="mt-2 w-full rounded-lg border border-surface-200 p-2 text-sm" /><span class="mt-1 block text-xs font-normal text-surface-500">Comma-separated. Every criterion is scored 0–5.</span></label><p v-for="error in editForm.errors" :key="error" role="alert" class="text-xs text-red-700">{{ error }}</p>
                    <div class="space-y-3">
                        <div><label class="block text-sm font-medium text-surface-700 mb-1">Title</label><input v-model="editForm.title" type="text" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-surface-700 mb-1">Description</label><textarea v-model="editForm.description" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm"></textarea></div>
                        <div class="grid grid-cols-3 gap-3">
                            <div><label class="block text-sm font-medium text-surface-700 mb-1">Weeks</label><input v-model.number="editForm.duration_weeks" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" /></div>
                            <div><label class="block text-sm font-medium text-surface-700 mb-1">Price</label><input v-model.number="editForm.price" type="number" min="0" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" /></div>
                            <div><label class="block text-sm font-medium text-surface-700 mb-1">Max</label><input v-model.number="editForm.max_participants" type="number" min="1" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" /></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="editForm.is_published" type="checkbox" id="edit_published" class="rounded border-surface-300 text-primary-600" />
                            <label for="edit_published" class="text-sm text-surface-700">Published</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Task Modal -->
        <div v-if="showAddTask" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAddTask = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-surface-900 mb-4">Add Task to Intern</h3>
                <form @submit.prevent="submitTask">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Task Title *</label>
                            <input v-model="taskForm.title" type="text" required class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="e.g. Create project charter" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                            <textarea v-model="taskForm.description" rows="3" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" placeholder="What the intern needs to do..."></textarea>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                                <select v-model="taskForm.type" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Status</label>
                                <select v-model="taskForm.status" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="backlog">Backlog</option>
                                    <option value="this_week">This Week</option>
                                    <option value="today">Today</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="review">Review</option>
                                    <option value="done">Done</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Priority</label>
                                <select v-model="taskForm.priority" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Milestone</label>
                                <select v-model="taskForm.milestone_id" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm">
                                    <option :value="null">— None —</option>
                                    <option v-for="ms in track.milestones" :key="ms.id" :value="ms.id">W{{ ms.week_number }}: {{ ms.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1">Due Date</label>
                                <input v-model="taskForm.due_date" type="date" class="w-full px-3 py-2 border border-surface-200 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" @click="showAddTask = false" class="px-4 py-2 text-sm text-surface-700 hover:bg-surface-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="taskForm.processing" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                            {{ taskForm.processing ? 'Adding...' : 'Add Task' }}
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
import { ArrowLeft, Trash2, Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    track: { type: Object, required: true },
    enrollments: { type: Array, default: () => [] },
});

const showAddMilestone = ref(false);
const showEnroll = ref(false);
const showEdit = ref(false);
const rubricInput = ref((props.track.review_rubric || ['Quality', 'Timeliness', 'Communication', 'Initiative', 'Technical skill']).join(', '));
const showAddTask = ref(false);
const activeEnrollmentId = ref(null);

const msForm = useForm({ title: '', week_number: 1, description: '' });
const taskForm = useForm({
    title: '',
    description: '',
    type: 'weekly',
    status: 'backlog',
    priority: 'medium',
    milestone_id: null,
    due_date: '',
});
const enrollForm = useForm({ user_id: null, sponsor_id: null, project_title: '', start_date: '', end_date: '' });
const editForm = useForm({
    title: props.track.title,
    description: props.track.description || '',
    duration_weeks: props.track.duration_weeks,
    price: props.track.price,
    max_participants: props.track.max_participants,
    is_published: props.track.is_published,
    review_rubric: props.track.review_rubric || [],
});

function submitMilestone() {
    msForm.post(`/admin/internships/${props.track.id}/milestones`, {
        onSuccess: () => { showAddMilestone.value = false; msForm.reset(); },
    });
}

function deleteMilestone(ms) {
    if (!confirm(`Delete milestone "${ms.title}"?`)) return;
    router.delete(`/admin/internships/${props.track.id}/milestones/${ms.id}`);
}

function submitEnrollment() {
    enrollForm.post(`/admin/internships/${props.track.id}/enrollments`, {
        onSuccess: () => { showEnroll.value = false; enrollForm.reset(); },
    });
}

function updateEnrollmentStatus(enr, status) {
    router.put(`/admin/enrollments/${enr.id}`, { status });
}

function openAddTask(enr) {
    activeEnrollmentId.value = enr.id;
    taskForm.reset();
    showAddTask.value = true;
}

function submitTask() {
    taskForm.post(`/admin/enrollments/${activeEnrollmentId.value}/tasks`, {
        onSuccess: () => { showAddTask.value = false; taskForm.reset(); },
    });
}

function submitEdit() {
    editForm.review_rubric = rubricInput.value.split(',').map(s => s.trim()).filter(Boolean);
    editForm.put(`/admin/internships/${props.track.id}`, {
        onSuccess: () => { showEdit.value = false; },
    });
}

function deleteTrack() {
    if (!confirm('Delete this track?')) return;
    router.delete(`/admin/internships/${props.track.id}`);
}

function enrollStatusClass(status) {
    const map = {
        enrolled: 'bg-primary-50 text-primary-700',
        active: 'bg-success-50 text-success-700',
        completed: 'bg-accent-50 text-accent-700',
        withdrawn: 'bg-surface-100 text-surface-500',
        failed: 'bg-danger-50 text-danger-700',
    };
    return map[status] || 'bg-surface-100 text-surface-500';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatPrice(price, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(price || 0);
}
</script>

<template>
    <Head :title="`Edit: ${simulation.title}`" />

    <div class="max-w-5xl mx-auto space-y-6">
        <a :href="`/admin/simulations/${simulation.id}`" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to simulation
        </a>

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-display font-bold text-surface-900">Edit: {{ simulation.title }}</h1>
            <div class="flex items-center gap-2">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(simulation.status)]">
                    {{ simulation.status }}
                </span>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-surface-200 gap-6">
            <button v-for="tab in tabs" :key="tab.key"
                    @click="activeTab = tab.key"
                    :class="['pb-2 text-sm font-medium border-b-2 -mb-px transition-colors', activeTab === tab.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-surface-500 hover:text-surface-700']">
                {{ tab.label }}
            </button>
        </div>

        <!-- Details Tab -->
        <form v-if="activeTab === 'details'" @submit.prevent="updateDetails" class="space-y-6">
            <Card title="Basic Information">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Title *</label>
                        <input v-model="detailsForm.title" type="text" required
                               class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Description</label>
                        <textarea v-model="detailsForm.description" rows="3"
                                  class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Context Narrative *</label>
                        <textarea v-model="detailsForm.context_narrative" rows="5" required
                                  class="w-full rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500" />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                            <select v-model="detailsForm.type" class="w-full rounded-lg border-surface-300 text-sm">
                                <option value="branching_scenario">Branching Scenario</option>
                                <option value="role_play">Role Play</option>
                                <option value="case_study_lab">Case Study Lab</option>
                                <option value="decision_room">Decision Room</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Difficulty</label>
                            <select v-model="detailsForm.difficulty" class="w-full rounded-lg border-surface-300 text-sm">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Status</label>
                            <select v-model="detailsForm.status" class="w-full rounded-lg border-surface-300 text-sm">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Course</label>
                            <select v-model="detailsForm.course_id" class="w-full rounded-lg border-surface-300 text-sm">
                                <option :value="null">— None —</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">Est. Minutes</label>
                            <input v-model.number="detailsForm.estimated_minutes" type="number" min="1"
                                   class="w-full rounded-lg border-surface-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1">XP Reward</label>
                            <input v-model.number="detailsForm.xp_reward" type="number" min="0"
                                   class="w-full rounded-lg border-surface-300 text-sm" />
                        </div>
                    </div>
                </div>
            </Card>
            <div class="flex justify-end">
                <button type="submit" :disabled="detailsForm.processing"
                        class="px-6 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 transition-colors disabled:opacity-50">
                    {{ detailsForm.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </form>

        <!-- Nodes Tab -->
        <div v-if="activeTab === 'nodes'" class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-display font-semibold text-surface-900">Scenario Nodes</h2>
                <button @click="showAddNode = true"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    <Plus class="w-4 h-4" /> Add Node
                </button>
            </div>

            <!-- Node map / list -->
            <div class="space-y-3">
                <div v-for="node in nodes" :key="node.id"
                     :class="['bg-white rounded-xl border p-4', node.is_start ? 'border-success-300 ring-1 ring-success-200' : 'border-surface-200']">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-bold uppercase', nodeTypeBadge(node.type)]">
                                    {{ node.type }}
                                </span>
                                <span v-if="node.is_start" class="px-2 py-0.5 rounded-full text-xs font-bold bg-success-100 text-success-700">START</span>
                                <span v-if="node.is_end" class="px-2 py-0.5 rounded-full text-xs font-bold bg-error-100 text-error-700">END</span>
                                <span class="text-xs text-surface-400">Order: {{ node.order_index }}</span>
                            </div>
                            <p class="text-sm text-surface-700 line-clamp-3">{{ node.content }}</p>

                            <!-- Choices -->
                            <div v-if="node.choices?.length" class="mt-3 space-y-1">
                                <p class="text-xs font-semibold text-surface-500 uppercase">Choices:</p>
                                <div v-for="choice in node.choices" :key="choice.id"
                                     class="flex items-center gap-2 text-sm text-surface-600 bg-surface-50 rounded-lg px-3 py-1.5">
                                    <span class="font-medium">{{ choice.label }}</span>
                                    <span v-if="choice.next_node_id" class="text-xs text-surface-400">→ Node #{{ choice.next_node_id }}</span>
                                    <span v-if="choice.score_impact" class="text-xs text-primary-600">
                                        {{ formatScoreImpact(choice.score_impact) }}
                                    </span>
                                    <div class="flex-1" />
                                    <button @click="deleteChoice(node.id, choice.id)" class="text-error-400 hover:text-error-600">
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1 ml-4 shrink-0">
                            <button @click="openAddChoice(node)"
                                    class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-surface-700" title="Add choice">
                                <GitBranch class="w-4 h-4" />
                            </button>
                            <button @click="editNode(node)"
                                    class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-surface-700">
                                <Pencil class="w-4 h-4" />
                            </button>
                            <button @click="deleteNode(node)"
                                    class="p-1.5 rounded-lg hover:bg-error-50 text-surface-500 hover:text-error-600">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <EmptyState v-if="!nodes.length" title="No Nodes" description="Add the first node to build the scenario tree." />
        </div>

        <!-- Add/Edit Node Modal -->
        <Modal :show="showAddNode || !!editingNode" @close="closeNodeModal">
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-display font-bold text-surface-900">
                    {{ editingNode ? 'Edit Node' : 'Add Node' }}
                </h3>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Type *</label>
                    <select v-model="nodeForm.type" class="w-full rounded-lg border-surface-300 text-sm">
                        <option value="story">Story</option>
                        <option value="decision">Decision</option>
                        <option value="consequence">Consequence</option>
                        <option value="feedback">Feedback</option>
                        <option value="end">End</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Content *</label>
                    <textarea v-model="nodeForm.content" rows="5" required
                              class="w-full rounded-lg border-surface-300 text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Order Index</label>
                        <input v-model.number="nodeForm.order_index" type="number"
                               class="w-full rounded-lg border-surface-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Media URL</label>
                        <input v-model="nodeForm.media_url" type="text"
                               class="w-full rounded-lg border-surface-300 text-sm" />
                    </div>
                </div>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="nodeForm.is_start" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                        Start node
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="nodeForm.is_end" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                        End node
                    </label>
                </div>
                <div class="flex gap-3 justify-end">
                    <button @click="closeNodeModal" class="px-4 py-2 rounded-lg text-sm bg-surface-100 text-surface-700 hover:bg-surface-200">Cancel</button>
                    <button @click="saveNode" :disabled="nodeForm.processing"
                            class="px-6 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50">
                        {{ nodeForm.processing ? 'Saving...' : editingNode ? 'Update' : 'Add Node' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Add Choice Modal -->
        <Modal :show="showAddChoice" @close="showAddChoice = false">
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-display font-bold text-surface-900">Add Choice</h3>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Choice Label *</label>
                    <input v-model="choiceForm.label" type="text" required
                           class="w-full rounded-lg border-surface-300 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Next Node</label>
                    <select v-model="choiceForm.next_node_id" class="w-full rounded-lg border-surface-300 text-sm">
                        <option :value="null">— None —</option>
                        <option v-for="n in nodes" :key="n.id" :value="n.id">
                            #{{ n.id }} — {{ truncate(n.content, 60) }} ({{ n.type }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Score Impact (JSON)</label>
                    <textarea v-model="choiceForm.score_impact_json" rows="3"
                              placeholder='{"judgment": 5, "ethics": -10}'
                              class="w-full rounded-lg border-surface-300 text-sm font-mono" />
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="choiceForm.is_best_practice" type="checkbox" class="rounded border-surface-300 text-primary-600" />
                        Best practice path
                    </label>
                </div>
                <div class="flex gap-3 justify-end">
                    <button @click="showAddChoice = false" class="px-4 py-2 rounded-lg text-sm bg-surface-100 text-surface-700 hover:bg-surface-200">Cancel</button>
                    <button @click="saveChoice" :disabled="choiceForm.processing"
                            class="px-6 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50">
                        {{ choiceForm.processing ? 'Saving...' : 'Add Choice' }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Plus, Pencil, Trash2, GitBranch, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    simulation: { type: Object, required: true },
    nodes: { type: Array, default: () => [] },
    courses: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'details', label: 'Details' },
    { key: 'nodes', label: 'Scenario Nodes' },
];
const activeTab = ref('details');

// Details form
const detailsForm = useForm({
    title: props.simulation.title,
    description: props.simulation.description || '',
    context_narrative: props.simulation.context_narrative || '',
    type: props.simulation.type,
    difficulty: props.simulation.difficulty,
    status: props.simulation.status,
    course_id: props.simulation.course_id,
    estimated_minutes: props.simulation.estimated_minutes || 15,
    xp_reward: props.simulation.xp_reward || 50,
});

function updateDetails() {
    detailsForm.put(`/admin/simulations/${props.simulation.id}`);
}

// Node management
const showAddNode = ref(false);
const editingNode = ref(null);
const nodeForm = useForm({
    type: 'story',
    content: '',
    order_index: 0,
    media_url: '',
    is_start: false,
    is_end: false,
});

function editNode(node) {
    editingNode.value = node;
    nodeForm.type = node.type;
    nodeForm.content = node.content;
    nodeForm.order_index = node.order_index;
    nodeForm.media_url = node.media_url || '';
    nodeForm.is_start = !!node.is_start;
    nodeForm.is_end = !!node.is_end;
}

function closeNodeModal() {
    showAddNode.value = false;
    editingNode.value = null;
    nodeForm.reset();
}

function saveNode() {
    if (editingNode.value) {
        nodeForm.put(`/admin/simulations/${props.simulation.id}/nodes/${editingNode.value.id}`, {
            onSuccess: closeNodeModal,
        });
    } else {
        nodeForm.post(`/admin/simulations/${props.simulation.id}/nodes`, {
            onSuccess: closeNodeModal,
        });
    }
}

function deleteNode(node) {
    if (confirm(`Delete this ${node.type} node?`)) {
        router.delete(`/admin/simulations/${props.simulation.id}/nodes/${node.id}`);
    }
}

// Choice management
const showAddChoice = ref(false);
const choiceParentNodeId = ref(null);
const choiceForm = useForm({
    label: '',
    next_node_id: null,
    score_impact_json: '',
    is_best_practice: false,
});

function openAddChoice(node) {
    choiceParentNodeId.value = node.id;
    showAddChoice.value = true;
    choiceForm.reset();
}

function saveChoice() {
    const data = {
        label: choiceForm.label,
        next_node_id: choiceForm.next_node_id,
        is_best_practice: choiceForm.is_best_practice,
    };
    try {
        if (choiceForm.score_impact_json) {
            data.score_impact = JSON.parse(choiceForm.score_impact_json);
        }
    } catch {
        alert('Invalid JSON for score impact.');
        return;
    }
    router.post(`/admin/simulations/${props.simulation.id}/nodes/${choiceParentNodeId.value}/choices`, data, {
        onSuccess: () => { showAddChoice.value = false; choiceForm.reset(); },
    });
}

function deleteChoice(nodeId, choiceId) {
    if (confirm('Delete this choice?')) {
        router.delete(`/admin/simulations/${props.simulation.id}/nodes/${nodeId}/choices/${choiceId}`);
    }
}

function statusBadge(s) {
    return { draft: 'bg-surface-100 text-surface-600', published: 'bg-success-50 text-success-700', archived: 'bg-warning-50 text-warning-700' }[s] || 'bg-surface-100 text-surface-600';
}

function nodeTypeBadge(type) {
    const map = {
        story: 'bg-primary-50 text-primary-700',
        decision: 'bg-warning-50 text-warning-700',
        consequence: 'bg-error-50 text-error-700',
        feedback: 'bg-success-50 text-success-700',
        end: 'bg-surface-200 text-surface-700',
    };
    return map[type] || 'bg-surface-100 text-surface-600';
}

function formatScoreImpact(impact) {
    if (!impact || typeof impact !== 'object') return '';
    return Object.entries(impact).map(([k, v]) => `${k.substring(0, 3)}: ${v > 0 ? '+' : ''}${v}`).join(', ');
}

function truncate(text, len = 60) {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '…' : text;
}
</script>

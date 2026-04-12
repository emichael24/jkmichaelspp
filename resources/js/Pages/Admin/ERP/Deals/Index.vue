<template>
    <Head title="Deals Pipeline" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Deals Pipeline</h1>
                <p class="text-surface-500 mt-1 text-sm">
                    {{ openDeals.length }} open deals · Total ₦{{ totalPipelineValue }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <!-- View Toggle -->
                <div class="flex bg-surface-100 rounded-xl p-1">
                    <button @click="viewMode = 'kanban'" :class="['px-3 py-1.5 rounded-lg text-xs font-medium', viewMode === 'kanban' ? 'bg-white shadow-sm' : 'text-surface-500 hover:text-surface-700']">
                        <LayoutGrid class="w-3.5 h-3.5" />
                    </button>
                    <button @click="viewMode = 'table'" :class="['px-3 py-1.5 rounded-lg text-xs font-medium', viewMode === 'table' ? 'bg-white shadow-sm' : 'text-surface-500 hover:text-surface-700']">
                        <List class="w-3.5 h-3.5" />
                    </button>
                </div>
                <button @click="showNewDeal = true" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 flex items-center gap-2">
                    <Plus class="w-4 h-4" /> New Deal
                </button>
            </div>
        </div>

        <!-- ─── KANBAN VIEW ─── -->
        <div v-if="viewMode === 'kanban'" class="flex gap-4 overflow-x-auto pb-4">
            <div v-for="stage in stages" :key="stage.value" class="flex-shrink-0 w-72">
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <div :class="['w-2.5 h-2.5 rounded-full', stage.dot]"></div>
                        <span class="text-sm font-semibold text-surface-800">{{ stage.label }}</span>
                    </div>
                    <span class="text-xs text-surface-400 font-medium">{{ dealsByStage[stage.value]?.length || 0 }}</span>
                </div>

                <div class="space-y-3 min-h-20">
                    <div
                        v-for="deal in dealsByStage[stage.value]"
                        :key="deal.id"
                        @click="openDeal(deal)"
                        class="bg-white rounded-xl border border-surface-200/60 shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-primary-200 transition-all"
                    >
                        <p class="font-semibold text-surface-900 text-sm">{{ deal.name }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-700 flex-shrink-0">
                                {{ (deal.contact?.first_name?.[0] || '') + (deal.contact?.last_name?.[0] || '') }}
                            </div>
                            <span class="text-xs text-surface-500 truncate">{{ deal.contact?.first_name }} {{ deal.contact?.last_name }}</span>
                        </div>
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-sm font-bold text-success-700">₦{{ Number(deal.amount).toLocaleString() }}</span>
                            <span v-if="deal.expected_close_date" class="text-xs text-surface-400">
                                {{ formatDate(deal.expected_close_date) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-xs text-surface-400 text-center">
                    <span v-if="dealsByStage[stage.value]?.length">
                        ₦{{ stageTotal(stage.value) }} total
                    </span>
                    <span v-else class="italic">No deals</span>
                </div>
            </div>
        </div>

        <!-- ─── TABLE VIEW ─── -->
        <div v-else class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Deal</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Contact</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Stage</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Value</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Close Date</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="deal in openDeals" :key="deal.id" class="hover:bg-surface-50 cursor-pointer" @click="openDeal(deal)">
                            <td class="px-5 py-4 font-medium text-surface-900">{{ deal.name }}</td>
                            <td class="px-5 py-4 text-surface-600">{{ deal.contact?.first_name }} {{ deal.contact?.last_name }}</td>
                            <td class="px-5 py-4">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', stageBadgeClass(deal.stage)]">{{ deal.stage }}</span>
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-success-700">₦{{ Number(deal.amount).toLocaleString() }}</td>
                            <td class="px-5 py-4 text-surface-500 text-xs">{{ deal.expected_close_date ? formatDate(deal.expected_close_date) : '—' }}</td>
                            <td class="px-5 py-4 text-right">
                                <button @click.stop="openDeal(deal)" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-primary-600">
                                    <Eye class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!openDeals.length" class="p-16 text-center">
                <Briefcase class="w-12 h-12 text-surface-300 mx-auto mb-3" />
                <p class="font-semibold text-surface-700">No deals yet</p>
                <p class="text-sm text-surface-400 mt-1">Start tracking your sales pipeline.</p>
            </div>
        </div>
    </div>

    <!-- Deal Detail / Edit Modal -->
    <Modal :show="!!activeDeal" @close="activeDeal = null" maxWidth="lg">
        <div v-if="activeDeal" class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-surface-900">{{ activeDeal.name }}</h3>
                <button @click="activeDeal = null" class="p-1 rounded-lg hover:bg-surface-100">
                    <X class="w-4 h-4 text-surface-500" />
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-surface-500">Contact</p>
                    <p class="font-medium">{{ activeDeal.contact?.first_name }} {{ activeDeal.contact?.last_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-surface-500">Value</p>
                    <p class="font-bold text-success-700 text-base">₦{{ Number(activeDeal.amount).toLocaleString() }}</p>
                </div>
            </div>

            <!-- Stage Selector -->
            <div>
                <label class="text-xs text-surface-600 mb-2 block">Move to Stage</label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="stage in stages"
                        :key="stage.value"
                        @click="updateStage(activeDeal, stage.value)"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                            activeDeal.stage === stage.value
                                ? 'bg-primary-600 text-white border-primary-600'
                                : 'bg-white text-surface-600 border-surface-200 hover:border-primary-400',
                        ]"
                    >{{ stage.label }}</button>
                </div>
            </div>

            <div v-if="activeDeal.notes" class="bg-surface-50 rounded-xl p-3">
                <p class="text-xs text-surface-500 mb-1">Notes</p>
                <p class="text-sm text-surface-700">{{ activeDeal.notes }}</p>
            </div>

            <div class="flex justify-end">
                <Link :href="`/admin/erp/contacts/${activeDeal.contact_id}`">
                    <Button variant="secondary" size="sm">View Contact</Button>
                </Link>
            </div>
        </div>
    </Modal>

    <!-- New Deal Modal -->
    <Modal :show="showNewDeal" @close="showNewDeal = false">
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-surface-900">New Deal</h3>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Deal Name *</label>
                <Input v-model="newDealForm.name" :error="newDealForm.errors.name" />
            </div>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Contact</label>
                <select v-model="newDealForm.contact_id" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                    <option value="">Select contact</option>
                    <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.first_name }} {{ c.last_name }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Value (₦)</label>
                    <Input v-model="newDealForm.amount" type="number" min="0" />
                </div>
                <div>
                    <label class="text-xs font-medium text-surface-700 mb-1 block">Stage</label>
                    <select v-model="newDealForm.stage" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm">
                        <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-medium text-surface-700 mb-1 block">Expected Close</label>
                <Input v-model="newDealForm.expected_close_date" type="date" />
            </div>
            <div class="flex justify-end gap-3">
                <Button variant="ghost" @click="showNewDeal = false">Cancel</Button>
                <Button variant="primary" @click="saveDeal" :disabled="newDealForm.processing">Add Deal</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Modal from '@/Components/UI/Modal.vue';
import { Plus, LayoutGrid, List, Eye, Briefcase, X } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    openDeals: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
});

const viewMode = ref('kanban');
const activeDeal = ref(null);
const showNewDeal = ref(false);

const stages = [
    { value: 'new', label: 'New', dot: 'bg-surface-400' },
    { value: 'qualified', label: 'Qualified', dot: 'bg-blue-400' },
    { value: 'proposal', label: 'Proposal', dot: 'bg-amber-400' },
    { value: 'negotiation', label: 'Negotiation', dot: 'bg-orange-400' },
    { value: 'closed_won', label: 'Won', dot: 'bg-success-500' },
    { value: 'closed_lost', label: 'Lost', dot: 'bg-danger-400' },
];

const dealsByStage = computed(() => {
    const grouped = {};
    stages.forEach(s => { grouped[s.value] = []; });
    props.openDeals.forEach(d => { if (grouped[d.stage]) grouped[d.stage].push(d); });
    return grouped;
});

const totalPipelineValue = computed(() => {
    return Number(props.openDeals.filter(d => d.stage !== 'closed_lost').reduce((sum, d) => sum + Number(d.amount || 0), 0)).toLocaleString();
});

function stageTotal(stage) {
    return Number(dealsByStage.value[stage]?.reduce((s, d) => s + Number(d.amount || 0), 0) || 0).toLocaleString();
}

function stageBadgeClass(stage) {
    return { new: 'bg-surface-100 text-surface-700', qualified: 'bg-blue-50 text-blue-700', proposal: 'bg-amber-50 text-amber-700', negotiation: 'bg-orange-50 text-orange-700', closed_won: 'bg-success-50 text-success-700', closed_lost: 'bg-danger-50 text-danger-700' }[stage] || 'bg-surface-100 text-surface-600';
}

function openDeal(deal) { activeDeal.value = { ...deal }; }

function updateStage(deal, newStage) {
    router.patch(`/admin/erp/deals/${deal.id}`, { stage: newStage }, {
        preserveScroll: true,
        onSuccess: () => { activeDeal.value = null; },
    });
}

function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

const newDealForm = useForm({ name: '', contact_id: '', amount: '', stage: 'new', expected_close_date: '', notes: '' });
function saveDeal() {
    newDealForm.post('/admin/erp/deals', { onSuccess: () => { showNewDeal.value = false; newDealForm.reset(); } });
}
</script>

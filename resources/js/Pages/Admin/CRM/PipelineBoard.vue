<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    pipelines: Array,
    deals: Object,
    stages: Array,
});

const draggingDeal = ref(null);

const form = useForm({
    new_stage: ''
});

function handleDragStart(deal, event) {
    draggingDeal.value = deal;
    event.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
}

function handleDrop(stage, event) {
    event.preventDefault();
    
    if (!draggingDeal.value) return;

    if (draggingDeal.value.stage !== stage) {
        form.new_stage = stage;
        form.post(route('admin.crm.pipeline.stage', draggingDeal.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                draggingDeal.value = null;
            }
        });
    } else {
        draggingDeal.value = null;
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">B2B Deal Pipeline</h2>
        </template>

        <div class="py-12">
            <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
                <div class="flex overflow-x-auto gap-4 pb-4">
                    <div 
                        v-for="stage in stages" 
                        :key="stage"
                        class="min-w-[300px] w-[300px] bg-gray-100 rounded-lg p-4"
                        @dragover="handleDragOver"
                        @drop="(e) => handleDrop(stage, e)"
                    >
                        <h3 class="font-bold text-gray-700 mb-3">{{ stage }} ({{ deals[stage] ? deals[stage].length : 0 }})</h3>
                        
                        <div class="space-y-3 min-h-[500px]">
                            <div 
                                v-for="deal in (deals[stage] || [])" 
                                :key="deal.id"
                                draggable="true"
                                @dragstart="(e) => handleDragStart(deal, e)"
                                class="bg-white p-3 rounded shadow cursor-grab active:cursor-grabbing border border-gray-200"
                            >
                                <div class="flex justify-between items-start">
                                    <h4 class="font-semibold text-sm">{{ deal.title }}</h4>
                                    <span class="text-xs font-bold text-green-600">${{ deal.value }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-2">
                                    <p v-if="deal.organization"><i class="fas fa-building mr-1"></i> {{ deal.organization.name }}</p>
                                    <p v-if="deal.contact"><i class="fas fa-user mr-1"></i> {{ deal.contact.first_name }} {{ deal.contact.last_name }}</p>
                                </div>
                                <div class="mt-3 flex justify-between items-center text-xs">
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ deal.probability }}%</span>
                                    <span class="text-gray-400">Close: {{ deal.expected_close_date || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

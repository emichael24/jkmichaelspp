<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    plans: Array,
});

const isModalOpen = ref(false);
const selectedPlan = ref(null);

const form = useForm({
    plan_id: '',
    email: '',
    organization_name: '',
});

function openInvoiceModal(plan) {
    selectedPlan.value = plan;
    form.plan_id = plan.id;
    isModalOpen.value = true;
}

function generateInvoice() {
    form.post(route('admin.subscriptions.invoice'), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SaaS Subscription Plans</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div v-if="$page.props.flash.success" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ $page.props.flash.success }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="plan in plans" :key="plan.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="p-6">
                            <h3 class="text-2xl font-bold text-gray-900 text-center">{{ plan.name }}</h3>
                            <p class="text-center text-gray-500 mt-2">{{ plan.description }}</p>
                            <div class="text-center mt-4">
                                <span class="text-4xl font-extrabold">${{ plan.price }}</span>
                                <span class="text-gray-500">/{{ plan.billing_period === 'year' ? 'yr' : 'mo' }}</span>
                            </div>

                            <ul class="mt-6 space-y-3">
                                <li v-for="feature in plan.features" :key="feature.id" class="flex items-start">
                                    <svg class="h-6 w-6 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-gray-700">{{ feature.name }} <span v-if="feature.value">({{ feature.value }})</span></span>
                                </li>
                            </ul>

                            <div class="mt-8 text-center">
                                <button @click="openInvoiceModal(plan)" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Generate B2B Invoice Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Modal -->
        <div v-if="isModalOpen" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="isModalOpen = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form @submit.prevent="generateInvoice">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Generate Invoice for {{ selectedPlan?.name }}</h3>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Organization Name</label>
                                <input v-model="form.organization_name" type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            </div>

                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Billing Email</label>
                                <input v-model="form.email" type="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" :disabled="form.processing" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                Generate Link
                            </button>
                            <button type="button" @click="isModalOpen = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

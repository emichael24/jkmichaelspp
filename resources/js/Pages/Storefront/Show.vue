<template>
    <Head :title="product.name" />

    <div class="max-w-5xl mx-auto space-y-6">
        <a href="/storefront" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to storefront
        </a>

        <div class="grid lg:grid-cols-5 gap-8">
            <!-- Left: details (3 cols) -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Thumbnail -->
                <div class="rounded-xl overflow-hidden bg-surface-100 aspect-video flex items-center justify-center">
                    <img v-if="product.productable?.thumbnail" :src="product.productable.thumbnail"
                         class="w-full h-full object-cover" alt="" />
                    <BookOpen v-else class="w-16 h-16 text-surface-300" />
                </div>

                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-600">
                        {{ product.type }}
                    </span>
                    <span v-if="product.has_discount"
                          class="px-2 py-0.5 rounded-full text-xs font-medium bg-error-50 text-error-700">
                        {{ product.savings_pct }}% OFF
                    </span>
                </div>

                <h1 class="text-3xl font-display font-bold text-surface-900">{{ product.name }}</h1>

                <div v-if="product.productable?.description" class="prose prose-sm max-w-none text-surface-600">
                    <p class="whitespace-pre-line">{{ product.productable.description }}</p>
                </div>

                <!-- Course details if applicable -->
                <Card v-if="product.productable?.modules" title="What You'll Learn">
                    <ul class="space-y-2">
                        <li v-for="mod in product.productable.modules" :key="mod.id"
                            class="flex items-start gap-2 text-sm text-surface-700">
                            <CheckCircle class="w-4 h-4 text-success-500 mt-0.5 shrink-0" />
                            {{ mod.title }}
                        </li>
                    </ul>
                </Card>
            </div>

            <!-- Right: pricing card (2 cols) -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-surface-200/60 p-6 sticky top-24 space-y-5">
                    <div>
                        <div class="flex items-baseline gap-3">
                            <span class="text-3xl font-bold text-surface-900">
                                {{ formatCurrency(product.current_price, product.currency) }}
                            </span>
                            <span v-if="product.has_discount" class="text-lg text-surface-400 line-through">
                                {{ formatCurrency(product.price, product.currency) }}
                            </span>
                        </div>
                        <p v-if="product.has_discount" class="text-sm text-error-600 font-medium mt-1">
                            Save {{ product.savings_pct }}%
                        </p>
                    </div>

                    <!-- Discount code -->
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Discount Code</label>
                        <div class="flex gap-2">
                            <input v-model="discountCode" type="text" placeholder="Enter code"
                                   class="flex-1 rounded-lg border-surface-300 text-sm" />
                            <button @click="validateCode" :disabled="validating || !discountCode"
                                    class="px-3 py-2 rounded-lg text-sm font-medium bg-surface-100 text-surface-700 hover:bg-surface-200 disabled:opacity-50">
                                {{ validating ? '...' : 'Apply' }}
                            </button>
                        </div>
                        <p v-if="discountMessage" :class="['text-xs mt-1', discountValid ? 'text-success-600' : 'text-error-600']">
                            {{ discountMessage }}
                        </p>
                    </div>

                    <!-- Gateway selection -->
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-2">Payment Method</label>
                        <div class="space-y-2">
                            <label v-for="gw in gateways" :key="gw.id"
                                   class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                   :class="selectedGateway === gw.id ? 'border-primary-500 bg-primary-50' : 'border-surface-200 hover:border-surface-300'">
                                <input type="radio" v-model="selectedGateway" :value="gw.id"
                                       class="text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <p class="text-sm font-medium text-surface-900">{{ gw.name }}</p>
                                    <p class="text-xs text-surface-500">{{ gw.desc }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button @click="checkout" :disabled="purchasing"
                            class="w-full py-3 rounded-xl bg-primary-600 text-white font-semibold text-lg hover:bg-primary-700 transition-colors disabled:opacity-50">
                        {{ purchasing ? 'Processing...' : 'Buy Now' }}
                    </button>

                    <p class="text-xs text-surface-400 text-center">
                        30-day money-back guarantee · Instant access
                    </p>
                </div>
            </div>
        </div>

        <!-- Related products -->
        <div v-if="related.length" class="space-y-4 pt-8 border-t border-surface-200">
            <h2 class="text-lg font-display font-semibold text-surface-900">You May Also Like</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a v-for="r in related" :key="r.id" :href="`/storefront/${r.slug}`"
                   class="bg-white rounded-xl border border-surface-200/60 p-4 hover:shadow-md transition-shadow">
                    <p class="text-xs text-surface-400 mb-1">{{ r.type }}</p>
                    <p class="font-semibold text-sm text-surface-900 line-clamp-2">{{ r.name }}</p>
                    <p class="text-primary-700 font-bold mt-2">{{ formatCurrency(r.current_price, r.currency) }}</p>
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, BookOpen, CheckCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    related: { type: Array, default: () => [] },
});

const gateways = [
    { id: 'paystack', name: 'Paystack', desc: 'Card, Bank Transfer, USSD (Nigeria)' },
    { id: 'flutterwave', name: 'Flutterwave', desc: 'Card, Bank Transfer, Mobile Money (Africa)' },
    { id: 'stripe', name: 'Stripe', desc: 'International card payments' },
];

const selectedGateway = ref('paystack');
const discountCode = ref('');
const discountMessage = ref('');
const discountValid = ref(false);
const validating = ref(false);
const purchasing = ref(false);

async function validateCode() {
    validating.value = true;
    try {
        const res = await fetch('/cart/validate-discount', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ code: discountCode.value, product_id: props.product.id }),
        });
        const data = await res.json();
        discountValid.value = data.valid;
        discountMessage.value = data.message;
    } catch {
        discountMessage.value = 'Error validating code.';
        discountValid.value = false;
    } finally {
        validating.value = false;
    }
}

function checkout() {
    purchasing.value = true;
    router.post('/orders', {
        items: [{ product_id: props.product.id, quantity: 1 }],
        discount_code: discountValid.value ? discountCode.value : null,
        payment_gateway: selectedGateway.value,
    }, {
        onFinish: () => { purchasing.value = false; },
    });
}

function formatCurrency(amount, currency = 'NGN') {
    const symbols = { NGN: '₦', USD: '$', GBP: '£', EUR: '€' };
    const symbol = symbols[currency] || currency + ' ';
    return symbol + Number(amount).toLocaleString('en', { minimumFractionDigits: 0 });
}
</script>

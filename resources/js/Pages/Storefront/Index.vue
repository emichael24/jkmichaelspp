<template>
    <Head title="Storefront" />

    <div class="space-y-8">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Courses & Programs</h1>
            <p class="text-surface-500 mt-1 text-sm">Invest in your professional development with world-class certifications.</p>
        </div>

        <!-- Filters -->
        <div class="flex gap-3 flex-wrap">
            <input v-model="form.search" type="text" placeholder="Search courses..."
                   @input="debouncedFilter"
                   class="rounded-lg border-surface-300 text-sm focus:ring-primary-500 focus:border-primary-500 w-64" />
            <select v-model="form.type" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">All Types</option>
                <option value="Course">Courses</option>
                <option value="Bundle">Bundles</option>
                <option value="InternshipTrack">Internships</option>
            </select>
            <select v-model="form.sort" @change="applyFilters" class="rounded-lg border-surface-300 text-sm">
                <option value="">Newest</option>
                <option value="price_asc">Price: Low → High</option>
                <option value="price_desc">Price: High → Low</option>
            </select>
        </div>

        <!-- Bundles highlight -->
        <div v-if="bundles.length" class="space-y-3">
            <h2 class="text-lg font-display font-semibold text-surface-900">Course Bundles — Save More</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a v-for="bundle in bundles" :key="bundle.id"
                   :href="`/storefront/${bundle.slug}`"
                   class="bg-gradient-to-br from-primary-50 to-primary-100/50 rounded-xl border border-primary-200/60 p-5 hover:shadow-lg transition-shadow group">
                    <div class="flex items-center gap-2 mb-2">
                        <Package class="w-5 h-5 text-primary-600" />
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700">
                            {{ bundle.course_count }} courses
                        </span>
                    </div>
                    <h3 class="font-semibold text-surface-900 group-hover:text-primary-700 transition-colors">
                        {{ bundle.name }}
                    </h3>
                    <p class="text-sm text-surface-500 mt-1 line-clamp-2">{{ bundle.description }}</p>
                    <p v-if="bundle.product" class="mt-3 text-lg font-bold text-primary-700">
                        {{ formatCurrency(bundle.product.current_price, bundle.product.currency) }}
                    </p>
                </a>
            </div>
        </div>

        <!-- Product grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <a v-for="product in products.data" :key="product.id"
               :href="`/storefront/${product.slug}`"
               class="bg-white rounded-xl border border-surface-200/60 overflow-hidden hover:shadow-lg transition-shadow group">
                <!-- Thumbnail -->
                <div class="h-40 bg-surface-100 flex items-center justify-center">
                    <img v-if="product.productable?.thumbnail" :src="product.productable.thumbnail"
                         class="w-full h-full object-cover" alt="" />
                    <BookOpen v-else class="w-10 h-10 text-surface-300" />
                </div>

                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-600">
                            {{ product.type }}
                        </span>
                        <span v-if="product.has_discount"
                              class="px-2 py-0.5 rounded-full text-xs font-medium bg-error-50 text-error-700">
                            -{{ product.savings_pct }}%
                        </span>
                    </div>

                    <h3 class="font-semibold text-sm text-surface-900 line-clamp-2 group-hover:text-primary-600 transition-colors">
                        {{ product.name }}
                    </h3>

                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-lg font-bold text-surface-900">
                            {{ formatCurrency(product.current_price, product.currency) }}
                        </span>
                        <span v-if="product.has_discount" class="text-sm text-surface-400 line-through">
                            {{ formatCurrency(product.price, product.currency) }}
                        </span>
                    </div>
                </div>
            </a>
        </div>

        <EmptyState v-if="!products.data?.length" title="No Products" description="No products available yet." />

        <!-- Pagination -->
        <div v-if="products.links && products.last_page > 1" class="flex justify-center gap-1">
            <a v-for="link in products.links" :key="link.label" :href="link.url"
               :class="['px-3 py-1.5 rounded-lg text-sm', link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100', !link.url ? 'opacity-50 pointer-events-none' : '']"
               v-html="link.label" />
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Package, BookOpen } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    products: { type: Object, default: () => ({ data: [] }) },
    bundles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ ...props.filters });

let debounceTimer = null;
function debouncedFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}

function applyFilters() {
    const params = {};
    Object.entries(form).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/storefront', params, { preserveState: true });
}

function formatCurrency(amount, currency = 'NGN') {
    const symbols = { NGN: '₦', USD: '$', GBP: '£', EUR: '€' };
    const symbol = symbols[currency] || currency + ' ';
    return symbol + Number(amount).toLocaleString('en', { minimumFractionDigits: 0 });
}
</script>

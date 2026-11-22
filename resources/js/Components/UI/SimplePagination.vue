<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ page: Object });
</script>
<template>
    <nav v-if="page.total" aria-label="Results pages" class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm text-surface-600">
        <p>{{ page.from }}–{{ page.to }} of {{ page.total }}</p>
        <div class="flex gap-3"><Link v-if="page.prev_page_url" :href="page.prev_page_url" class="rounded-xl border border-surface-200 bg-white px-4 py-2">Previous</Link><Link v-if="page.next_page_url" :href="page.next_page_url" class="rounded-xl border border-surface-200 bg-white px-4 py-2">Next</Link></div>
    </nav>
</template>

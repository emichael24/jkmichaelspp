<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { personalLinks } from '@/navigation/workspaces';

const props = defineProps({ workspace: Object, activeHref: String, mobile: Boolean });
defineEmits(['navigate']);
const query = ref('');
const groups = computed(() => [...props.workspace.groups, { label: 'Personal', items: personalLinks }]
    .map(group => ({ ...group, items: group.items.filter(item => item.label.toLowerCase().includes(query.value.trim().toLowerCase())) }))
    .filter(group => group.items.length));
</script>

<template>
    <div class="px-4 pb-4">
        <label :for="mobile ? 'mobile-nav-search' : 'nav-search'" class="sr-only">Find a workspace page</label>
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-surface-400" aria-hidden="true" />
            <input :id="mobile ? 'mobile-nav-search' : 'nav-search'" v-model="query" type="search" placeholder="Find a page…" class="h-10 w-full rounded-lg border border-surface-700 bg-surface-800 pl-9 pr-3 text-sm text-white placeholder:text-surface-400 focus:border-surface-400" />
        </div>
    </div>
    <nav :aria-label="`${workspace.label} navigation`" class="flex-1 overflow-y-auto px-3 pb-5">
        <div v-for="group in groups" :key="group.label" class="mb-6 last:mb-0">
            <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.14em] text-surface-400">{{ group.label }}</p>
            <Link v-for="item in group.items" :key="item.href" :href="item.href" :aria-current="activeHref === item.href ? 'page' : undefined" @click="$emit('navigate')"
                :class="['mb-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors', activeHref === item.href ? 'bg-white text-surface-900 font-semibold shadow-sm' : 'text-surface-300 hover:bg-surface-800 hover:text-white']">
                <component :is="item.icon" :class="['h-[18px] w-[18px] shrink-0', activeHref === item.href ? 'text-primary-700' : 'text-surface-400']" aria-hidden="true" />
                <span>{{ item.label }}</span>
            </Link>
        </div>
        <p v-if="!groups.length" role="status" class="px-3 py-6 text-sm text-surface-300">No pages match “{{ query }}”. Try another name.</p>
    </nav>
</template>

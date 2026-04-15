<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    items: { type: Object, default: () => ({}) },
});

const updatingId = ref(null);

const gates = computed(() => ({
    beta: props.items?.beta || [],
    launch: props.items?.launch || [],
}));

const progress = (items) => {
    if (!items.length) return 0;
    const done = items.filter((i) => i.status === 'complete').length;
    return Math.round((done / items.length) * 100);
};

function statusVariant(status) {
    if (status === 'complete') return 'success';
    if (status === 'in_progress') return 'primary';
    if (status === 'blocked') return 'danger';
    return 'secondary';
}

function updateItem(item, updates) {
    updatingId.value = item.id;
    router.put(route('admin.launch.checklist.update', item.id), updates, {
        preserveScroll: true,
        onFinish: () => { updatingId.value = null; },
    });
}
</script>

<template>
    <Head title="Launch Checklist" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Launch Checklist</h1>
            <p class="text-surface-500 mt-1 text-sm">Track beta and launch gate readiness across product, QA, payments, and operations.</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <Card title="Beta Gate">
                <template #header-actions>
                    <Badge variant="primary">{{ progress(gates.beta) }}% Complete</Badge>
                </template>
                <div class="space-y-4">
                    <div v-for="item in gates.beta" :key="item.id" class="rounded-xl border border-surface-200 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs text-surface-400 uppercase">{{ item.area }}</p>
                                <p class="text-sm font-semibold text-surface-900">{{ item.title }}</p>
                            </div>
                            <Badge :variant="statusVariant(item.status)" size="sm">{{ item.status }}</Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                class="rounded-lg border border-surface-200 px-2 py-1 text-xs"
                                :value="item.status"
                                @change="updateItem(item, { status: $event.target.value, owner_name: item.owner_name, notes: item.notes })"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="complete">Complete</option>
                                <option value="blocked">Blocked</option>
                            </select>
                            <input
                                class="rounded-lg border border-surface-200 px-2 py-1 text-xs"
                                type="text"
                                :value="item.owner_name || ''"
                                placeholder="Owner"
                                @blur="updateItem(item, { status: item.status, owner_name: $event.target.value, notes: item.notes })"
                            />
                            <Button
                                size="xs"
                                variant="ghost"
                                :loading="updatingId === item.id"
                                @click="updateItem(item, { status: 'complete', owner_name: item.owner_name, notes: item.notes })"
                            >
                                Mark Done
                            </Button>
                        </div>
                        <textarea
                            class="w-full rounded-lg border border-surface-200 px-3 py-2 text-xs"
                            rows="2"
                            placeholder="Notes / blockers"
                            :value="item.notes || ''"
                            @blur="updateItem(item, { status: item.status, owner_name: item.owner_name, notes: $event.target.value })"
                        />
                    </div>
                </div>
            </Card>

            <Card title="Launch Gate">
                <template #header-actions>
                    <Badge variant="accent">{{ progress(gates.launch) }}% Complete</Badge>
                </template>
                <div class="space-y-4">
                    <div v-for="item in gates.launch" :key="item.id" class="rounded-xl border border-surface-200 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs text-surface-400 uppercase">{{ item.area }}</p>
                                <p class="text-sm font-semibold text-surface-900">{{ item.title }}</p>
                            </div>
                            <Badge :variant="statusVariant(item.status)" size="sm">{{ item.status }}</Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                class="rounded-lg border border-surface-200 px-2 py-1 text-xs"
                                :value="item.status"
                                @change="updateItem(item, { status: $event.target.value, owner_name: item.owner_name, notes: item.notes })"
                            >
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="complete">Complete</option>
                                <option value="blocked">Blocked</option>
                            </select>
                            <input
                                class="rounded-lg border border-surface-200 px-2 py-1 text-xs"
                                type="text"
                                :value="item.owner_name || ''"
                                placeholder="Owner"
                                @blur="updateItem(item, { status: item.status, owner_name: $event.target.value, notes: item.notes })"
                            />
                            <Button
                                size="xs"
                                variant="ghost"
                                :loading="updatingId === item.id"
                                @click="updateItem(item, { status: 'complete', owner_name: item.owner_name, notes: item.notes })"
                            >
                                Mark Done
                            </Button>
                        </div>
                        <textarea
                            class="w-full rounded-lg border border-surface-200 px-3 py-2 text-xs"
                            rows="2"
                            placeholder="Notes / blockers"
                            :value="item.notes || ''"
                            @blur="updateItem(item, { status: item.status, owner_name: item.owner_name, notes: $event.target.value })"
                        />
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>

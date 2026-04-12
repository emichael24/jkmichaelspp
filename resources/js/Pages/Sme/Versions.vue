<template>
    <Head title="Version History" />

    <div class="space-y-6">
        <a :href="`/sme`" class="inline-flex items-center gap-1 text-sm text-surface-500 hover:text-surface-700">
            <ArrowLeft class="w-4 h-4" /> Back to library
        </a>

        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">Version History</h1>
            <p class="text-surface-500 text-sm mt-1">
                {{ contentType }} · {{ content.title || content.stem || 'Content' }}
            </p>
        </div>

        <!-- Version list -->
        <div class="space-y-4">
            <div v-for="version in versions" :key="version.id"
                 class="bg-white rounded-xl border border-surface-200/60 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700">
                                v{{ version.version_number }}
                            </span>
                            <span class="text-sm text-surface-400">{{ formatDate(version.created_at) }}</span>
                        </div>
                        <p class="text-sm text-surface-700">
                            <span class="font-medium">{{ version.creator?.first_name }} {{ version.creator?.last_name }}</span>
                        </p>
                        <p v-if="version.change_summary" class="text-sm text-surface-500 mt-1">
                            {{ version.change_summary }}
                        </p>
                    </div>
                    <div class="ml-4 flex gap-2 shrink-0">
                        <button @click="showData = showData === version.id ? null : version.id"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium text-surface-600 hover:bg-surface-100 transition-colors">
                            {{ showData === version.id ? 'Hide' : 'View' }} Data
                        </button>
                        <button @click="revert(version.id)"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium text-warning-700 bg-warning-50 hover:bg-warning-100 transition-colors">
                            Revert
                        </button>
                    </div>
                </div>

                <!-- Expanded data -->
                <div v-if="showData === version.id" class="mt-4 bg-surface-50 rounded-lg p-4 font-mono text-xs max-h-96 overflow-auto">
                    <pre>{{ JSON.stringify(version.data, null, 2) }}</pre>
                </div>
            </div>
        </div>

        <EmptyState v-if="!versions?.length" title="No Versions" description="This content has no version history yet." />
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    content: { type: Object, required: true },
    versions: { type: Array, default: () => [] },
    contentType: { type: String, default: '' },
});

const showData = ref(null);

function revert(versionId) {
    if (!confirm('Are you sure you want to revert to this version? A new version will be created.')) return;
    router.post(`/sme/versions/${props.contentType}/${props.content.id}/revert/${versionId}`);
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

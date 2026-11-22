<script setup>
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
const props = defineProps({ course: Object, workspace: String });
const busy = ref(false);
const frozen = computed(() => props.course.is_published || props.course.published_at || props.course.approval_status === 'approved');
function act(action) {
    busy.value = true;
    router.post(`/${props.workspace}/courses/${props.course.id}/${action}`, {}, { onFinish: () => busy.value = false });
}
</script>
<template>
    <section v-if="frozen || course.approval_status === 'pending_review' || course.revision_of_id" class="rounded-2xl border border-primary-200 bg-primary-50 p-5 flex flex-wrap items-center justify-between gap-4" aria-label="Curriculum revision">
        <div class="min-w-0 flex-1">
            <h2 class="font-semibold text-surface-900">{{ frozen ? `Protected edition ${course.version}` : course.approval_status === 'pending_review' ? 'Review in progress' : `Draft edition ${course.version}` }}</h2>
            <p class="mt-1 text-sm text-surface-600">{{ frozen ? 'Create a draft revision to update the curriculum. Current enrollments, assessment records and certificates stay with this edition.' : course.approval_status === 'pending_review' ? 'The submitted curriculum is protected. Recall it to make changes, then submit it again.' : 'Review and publish this draft when it is ready for new enrollments.' }}</p>
        </div>
        <button v-if="frozen" type="button" @click="act('revisions')" :disabled="busy" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">{{ busy ? 'Opening draft…' : 'Create / open draft revision' }}</button>
        <button v-else-if="course.approval_status === 'pending_review'" type="button" @click="act('recall')" :disabled="busy" class="rounded-xl border border-primary-300 bg-white px-4 py-2.5 text-sm font-semibold text-primary-800 disabled:opacity-60">{{ busy ? 'Recalling…' : 'Recall review' }}</button>
    </section>
</template>

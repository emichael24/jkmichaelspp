<template>
  <section class="relative min-h-[600px] rounded-xl border border-surface-200 overflow-hidden" aria-label="Interactive lesson">
    <p v-if="error" role="alert" class="p-4 bg-red-50 text-red-800">{{ error }} <button class="underline font-semibold" @click="launch">Reload activity</button></p>
    <p v-else-if="!source" role="status" class="p-6 text-surface-500">Preparing your interactive lesson…</p>
    <iframe v-if="source" ref="frame" :src="source" sandbox="allow-scripts" referrerpolicy="no-referrer" title="Interactive lesson activity" class="h-[600px] w-full border-0" />
    <p class="px-4 py-2 text-xs text-surface-500" role="status">{{ saving ? 'Saving progress…' : saved ? 'Progress saved' : 'Your progress saves when the activity sends an update.' }}</p>
  </section>
</template>
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
const props = defineProps({ lessonId: { type: [Number, String], required: true }, entryPath: String, version: String, initialStatus: String, initialScore: [Number, String], initialLocation: String, initialSuspendData: [Object, String] });
const emit = defineEmits(['scorm-completed', 'scorm-progress']);
const source = ref(''), frame = ref(null), error = ref(''), saving = ref(false), saved = ref(false);
let channel = '', pending = Promise.resolve();
async function launch() {
  error.value = ''; source.value = '';
  try { const {data} = await axios.get(`/lessons/${props.lessonId}/scorm-launch`); channel = data.channel; source.value = data.url; }
  catch { error.value = 'This activity is unavailable. Your saved progress is safe.'; }
}
function receive(event) {
  if (event.source !== frame.value?.contentWindow || event.origin !== 'null' || event.data?.type !== 'jk-scorm-commit' || event.data.channel !== channel) return;
  const state = event.data.state;
  if (!state || JSON.stringify(state).length > 100000) return;
  pending = pending.then(async () => {
    saving.value = true;
    try {
      const {data} = await axios.post(`/api/scorm/commit/${props.lessonId}`, {
        cmi_core_lesson_status: state['cmi.core.lesson_status'] || 'incomplete',
        cmi_core_score_raw: state['cmi.core.score.raw'] === '' ? null : Number(state['cmi.core.score.raw']),
        cmi_core_lesson_location: state['cmi.core.lesson_location'] || '',
        suspend_data: {data: state['cmi.suspend_data'] || ''},
      });
      saved.value = true; error.value = ''; emit('scorm-progress', data);
      if (data.completed) emit('scorm-completed', data);
    } catch { error.value = 'Progress could not be saved. Check your connection and save again in the activity.'; }
    finally { saving.value = false; }
  });
}
onMounted(() => { window.addEventListener('message', receive); launch(); });
onBeforeUnmount(() => window.removeEventListener('message', receive));
</script>

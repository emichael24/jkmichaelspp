<template>
  <div class="scorm-container w-full h-full relative" style="min-height: 600px;">
    <!-- SCORM Loading State -->
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 z-10">
      <span class="text-gray-500">Loading interactive content...</span>
    </div>

    <!-- The actual SCORM player iframe -->
    <iframe
      ref="scormIframe"
      :src="entryPath"
      class="w-full h-full border-0 absolute inset-0"
      @load="handleIframeLoad"
    ></iframe>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
  lessonId: {
    type: [Number, String],
    required: true,
  },
  entryPath: {
    type: String,
    required: true,
  }
});

const emit = defineEmits(['scorm-completed', 'scorm-progress']);

const loading = ref(true);
const scormIframe = ref(null);

// Here we define the window.API (SCORM 1.2) or window.API_1484_11 (SCORM 2004) wrapper
const setupScormApi = () => {
  // A very basic SCORM 1.2 API mock that communicates with our Laravel ScormController
  window.API = {
    cmi: {
      core: {
        lesson_status: 'not attempted',
        score: { raw: '' },
        lesson_location: ''
      },
      suspend_data: ''
    },
    LMSInitialize: function() {
      console.log('SCORM: LMSInitialize');
      return "true";
    },
    LMSGetValue: function(param) {
      console.log('SCORM: LMSGetValue', param);
      if (param === 'cmi.core.lesson_status') return window.API.cmi.core.lesson_status;
      if (param === 'cmi.core.lesson_location') return window.API.cmi.core.lesson_location;
      if (param === 'cmi.suspend_data') return window.API.suspend_data;
      return "";
    },
    LMSSetValue: function(param, value) {
      console.log('SCORM: LMSSetValue', param, value);
      if (param === 'cmi.core.lesson_status') window.API.cmi.core.lesson_status = value;
      if (param === 'cmi.core.score.raw') window.API.cmi.core.score.raw = value;
      if (param === 'cmi.core.lesson_location') window.API.cmi.core.lesson_location = value;
      if (param === 'cmi.suspend_data') window.API.suspend_data = value;
      return "true";
    },
    LMSCommit: function() {
      console.log('SCORM: LMSCommit');
      
      // Send the commit data to our backend ScormController
      axios.post(`/api/scorm/commit/${props.lessonId}`, {
        cmi_core_lesson_status: window.API.cmi.core.lesson_status,
        cmi_core_score_raw: window.API.cmi.core.score.raw ? parseInt(window.API.cmi.core.score.raw) : null,
        cmi_core_lesson_location: window.API.cmi.core.lesson_location,
        suspend_data: window.API.suspend_data ? { data: window.API.suspend_data } : null,
      }).then(response => {
        emit('scorm-progress', response.data);
        if (['passed', 'completed'].includes(window.API.cmi.core.lesson_status)) {
          emit('scorm-completed');
        }
      }).catch(err => {
        console.error('SCORM API Sync Failed:', err);
      });
      return "true";
    },
    LMSFinish: function() {
      console.log('SCORM: LMSFinish');
      this.LMSCommit();
      return "true";
    },
    LMSGetLastError: function() { return "0"; },
    LMSGetErrorString: function() { return "No Error"; },
    LMSGetDiagnostic: function() { return "No Error"; }
  };
};

onMounted(() => {
  setupScormApi();
});

const handleIframeLoad = () => {
  loading.val = false;
};

// Cleanup window properties when component unmounts
onBeforeUnmount(() => {
  if (window.API) {
    delete window.API;
  }
});
</script>

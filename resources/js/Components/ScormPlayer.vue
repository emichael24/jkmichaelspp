<template>
  <div class="scorm-container relative h-full w-full" style="min-height: 600px;">
    <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-gray-100">
      <span class="text-gray-500">Loading interactive content...</span>
    </div>

    <iframe
      :src="entryPath"
      class="absolute inset-0 h-full w-full border-0"
      @load="handleIframeLoad"
    ></iframe>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  lessonId: {
    type: [Number, String],
    required: true,
  },
  entryPath: {
    type: String,
    required: true,
  },
  version: {
    type: String,
    default: '1.2',
  },
  initialStatus: {
    type: String,
    default: 'not attempted',
  },
  initialScore: {
    type: [Number, String, null],
    default: null,
  },
  initialLocation: {
    type: String,
    default: '',
  },
  initialSuspendData: {
    type: [Object, String, null],
    default: null,
  },
});

const emit = defineEmits(['scorm-completed', 'scorm-progress']);

const loading = ref(true);
const state = {
  lessonStatus: props.initialStatus || 'not attempted',
  scoreRaw: props.initialScore ?? '',
  lessonLocation: props.initialLocation || '',
  suspendData: typeof props.initialSuspendData === 'string'
    ? props.initialSuspendData
    : props.initialSuspendData?.data || '',
};

async function commitProgress() {
  try {
    const response = await axios.post(`/api/scorm/commit/${props.lessonId}`, {
      cmi_core_lesson_status: state.lessonStatus,
      cmi_core_score_raw: state.scoreRaw === '' || state.scoreRaw === null ? null : parseInt(state.scoreRaw, 10),
      cmi_core_lesson_location: state.lessonLocation,
      suspend_data: state.suspendData ? { data: state.suspendData } : null,
    });

    emit('scorm-progress', response.data);

    if (['passed', 'completed'].includes(String(state.lessonStatus).toLowerCase())) {
      emit('scorm-completed', response.data);
    }
  } catch (error) {
    console.error('SCORM API sync failed:', error);
  }
}

function buildScorm12Api() {
  return {
    LMSInitialize() {
      return 'true';
    },
    LMSGetValue(param) {
      if (param === 'cmi.core.lesson_status') return state.lessonStatus;
      if (param === 'cmi.core.lesson_location') return state.lessonLocation;
      if (param === 'cmi.core.score.raw') return state.scoreRaw;
      if (param === 'cmi.suspend_data') return state.suspendData;
      return '';
    },
    LMSSetValue(param, value) {
      if (param === 'cmi.core.lesson_status') state.lessonStatus = value;
      if (param === 'cmi.core.score.raw') state.scoreRaw = value;
      if (param === 'cmi.core.lesson_location') state.lessonLocation = value;
      if (param === 'cmi.suspend_data') state.suspendData = value;
      return 'true';
    },
    LMSCommit() {
      void commitProgress();
      return 'true';
    },
    LMSFinish() {
      void commitProgress();
      return 'true';
    },
    LMSGetLastError() {
      return '0';
    },
    LMSGetErrorString() {
      return 'No Error';
    },
    LMSGetDiagnostic() {
      return 'No Error';
    },
  };
}

function buildScorm2004Api() {
  return {
    Initialize() {
      return 'true';
    },
    GetValue(param) {
      if (param === 'cmi.completion_status' || param === 'cmi.success_status') return state.lessonStatus;
      if (param === 'cmi.location') return state.lessonLocation;
      if (param === 'cmi.score.raw') return state.scoreRaw;
      if (param === 'cmi.suspend_data') return state.suspendData;
      return '';
    },
    SetValue(param, value) {
      if (param === 'cmi.completion_status' || param === 'cmi.success_status') state.lessonStatus = value;
      if (param === 'cmi.score.raw') state.scoreRaw = value;
      if (param === 'cmi.location') state.lessonLocation = value;
      if (param === 'cmi.suspend_data') state.suspendData = value;
      return 'true';
    },
    Commit() {
      void commitProgress();
      return 'true';
    },
    Terminate() {
      void commitProgress();
      return 'true';
    },
    GetLastError() {
      return '0';
    },
    GetErrorString() {
      return 'No Error';
    },
    GetDiagnostic() {
      return 'No Error';
    },
  };
}

function setupScormApi() {
  window.API = buildScorm12Api();
  window.API_1484_11 = buildScorm2004Api();
}

function handleIframeLoad() {
  loading.value = false;
}

onMounted(() => {
  setupScormApi();
});

onBeforeUnmount(() => {
  if (window.API) {
    delete window.API;
  }

  if (window.API_1484_11) {
    delete window.API_1484_11;
  }
});
</script>

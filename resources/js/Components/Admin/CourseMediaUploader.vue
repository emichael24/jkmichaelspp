<template>
  <div class="media-uploader border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition-colors relative">
    
    <div v-if="!isUploading && !uploadSuccess">
      <input 
        type="file" 
        :accept="acceptFormat" 
        @change="handleFileSelect" 
        class="hidden" 
        ref="fileInput" 
      />
      <div 
        @click="$refs.fileInput.click()" 
        class="cursor-pointer flex flex-col items-center justify-center space-y-3"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <span class="text-sm text-gray-600 font-medium">Click to select {{ typeLabel }}</span>
        <span class="text-xs text-gray-400">{{ acceptFormat }} (Max 500MB)</span>
      </div>
    </div>

    <!-- Upload Progress -->
    <div v-if="isUploading" class="upload-progress w-full max-w-md mx-auto">
      <div class="flex justify-between items-center mb-2">
        <span class="text-sm font-medium text-blue-600">Uploading {{ fileToUpload?.name }}...</span>
        <span class="text-sm font-medium text-blue-600">{{ progress }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2.5">
        <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" :style="{ width: progress + '%' }"></div>
      </div>
    </div>

    <!-- Success State -->
    <div v-if="uploadSuccess" class="upload-success text-green-600 flex flex-col items-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <span class="font-medium text-gray-800">Upload Complete!</span>
      <div class="mt-4 flex gap-3">
         <button @click="resetUploader" class="text-sm text-blue-600 hover:text-blue-800 underline">Upload another</button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  uploadType: {
    type: String,
    required: true,
    validator: (value) => ['video', 'scorm'].includes(value)
  },
  lessonId: {
    type: [Number, String],
    default: null
  }
});

const emit = defineEmits(['upload-finished', 'upload-error']);

const fileInput = ref(null);
const fileToUpload = ref(null);
const isUploading = ref(false);
const uploadSuccess = ref(false);
const progress = ref(0);

const acceptFormat = computed(() => props.uploadType === 'video' ? 'video/mp4, video/quicktime' : '.zip');
const typeLabel = computed(() => props.uploadType === 'video' ? 'Video File (.mp4)' : 'SCORM Package (.zip)');

const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks

const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  // Basic validation
  if (props.uploadType === 'scorm' && file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
    alert("Please select a valid .zip file for SCORM.");
    return;
  }

  fileToUpload.value = file;
  
  if (props.uploadType === 'video') {
    uploadVideoInChunks(file);
  } else {
    uploadScormPackage(file);
  }
};

const uploadScormPackage = async (file) => {
  isUploading.value = true;
  progress.value = 0;

  const formData = new FormData();
  formData.append('file', file);
  
  try {
    const response = await axios.post('/admin/media/scorm', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        progress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
      }
    });

    isUploading.value = false;
    uploadSuccess.value = true;
    emit('upload-finished', {
      type: 'scorm',
      scorm_path: response.data.scorm_path,
      scorm_version: response.data.scorm_version
    });

  } catch (error) {
    isUploading.value = false;
    emit('upload-error', error.response?.data?.message || 'Failed to upload SCORM package.');
    alert("SCORM Upload failed: " + (error.response?.data?.error || error.message));
  }
};

const uploadVideoInChunks = async (file) => {
  isUploading.value = true;
  progress.value = 0;

  const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
  const fileName = `${Date.now()}_${file.name.replace(/[^a-zA-Z0-9.]/g, '_')}`;

  for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
    const start = chunkIndex * CHUNK_SIZE;
    const end = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);

    const formData = new FormData();
    formData.append('file', chunk);
    formData.append('fileName', fileName);
    formData.append('chunkIndex', chunkIndex);
    formData.append('totalChunks', totalChunks);

    try {
      const response = await axios.post('/admin/media/video', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });

      progress.value = Math.round(((chunkIndex + 1) / totalChunks) * 100);

      // If last chunk, handle success
      if (chunkIndex === totalChunks - 1 && response.data.status === 'success') {
        isUploading.value = false;
        uploadSuccess.value = true;
        emit('upload-finished', {
          type: 'video',
          video_url: response.data.video_url,
          video_provider: response.data.video_provider
        });
      }
    } catch (error) {
      isUploading.value = false;
      emit('upload-error', 'Video chunk upload failed.');
      alert('Video upload failed. Please try again.');
      break;
    }
  }
};

const resetUploader = () => {
  uploadSuccess.value = false;
  isUploading.value = false;
  progress.value = 0;
  fileToUpload.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};
</script>

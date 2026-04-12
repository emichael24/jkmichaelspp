<template>
  <div class="universal-video-wrapper relative w-full mb-6">
    <!-- Interactive Quiz Overlay -->
    <div 
      v-if="activeQuiz" 
      class="absolute inset-0 bg-black/80 z-20 flex items-center justify-center p-4 text-white"
    >
      <div class="bg-white text-gray-900 rounded-lg p-6 max-w-lg w-full">
        <h3 class="text-xl font-bold mb-4">Knowledge Check</h3>
        <p class="mb-4">{{ activeQuiz.question.text }}</p>
        
        <div class="space-y-2 mb-6">
          <button 
            v-for="(option, index) in activeQuiz.question.options" 
            :key="index"
            class="w-full text-left p-3 border rounded hover:bg-blue-50 focus:ring-2"
            @click="submitQuizAnswer(option)"
          >
            {{ option.text }}
          </button>
        </div>
      </div>
    </div>

    <!-- Plyr or Videojs container -->
    <div ref="playerContainer" class="rounded-lg overflow-hidden shadow-lg border border-gray-200">
      
      <!-- HTML5 / MP4 / HLS -->
      <video v-if="provider === 's3' || provider === 'mux' || !provider" 
             ref="videoElement" 
             class="plyr" 
             controls 
             crossorigin>
        <source :src="url" :type="url.endsWith('.m3u8') ? 'application/x-mpegURL' : 'video/mp4'" />
      </video>

      <!-- YouTube -->
      <div v-else-if="provider === 'youtube'" 
           class="plyr__video-embed" 
           ref="videoElement">
        <iframe
          :src="youtubeEmbedUrl"
          allowfullscreen
          allowtransparency
          allow="autoplay">
        </iframe>
      </div>

      <!-- Vimeo -->
      <div v-else-if="provider === 'vimeo'" 
           class="plyr__video-embed" 
           ref="videoElement">
        <iframe
          :src="vimeoEmbedUrl"
          allowfullscreen
          allowtransparency
          allow="autoplay">
        </iframe>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';

const props = defineProps({
  url: {
    type: String,
    required: true,
  },
  provider: {
    type: String,
    default: 's3', // 's3', 'youtube', 'vimeo', 'mux'
  },
  quizzes: {
    type: Array,
    default: () => [], // Array of lesson_video_quizzes
  },
  resumeAt: {
    type: Number,
    default: 0
  }
});

const emit = defineEmits(['progress-update', 'video-completed']);

const playerContainer = ref(null);
const videoElement = ref(null);
let player = null;
const activeQuiz = ref(null);
const completedQuizzes = ref(new Set());

// --- Computed Embed URLs for Iframes ---
const youtubeEmbedUrl = computed(() => {
  if (props.provider !== 'youtube') return '';
  const videoId = props.url.split('v=')[1] || props.url.split('/').pop();
  return `https://www.youtube.com/embed/${videoId}?origin=https://plyr.io&iv_load_policy=3&modestbranding=1&playsinline=1&showinfo=0&rel=0&enablejsapi=1`;
});

const vimeoEmbedUrl = computed(() => {
  if (props.provider !== 'vimeo') return '';
  const videoId = props.url.split('/').pop();
  return `https://player.vimeo.com/video/${videoId}?loop=false&byline=false&portrait=false&title=false&speed=true&transparent=0&gesture=media`;
});

// --- Lifecycle hooks ---
onMounted(() => {
  initializePlayer();
});

onBeforeUnmount(() => {
  if (player) {
    player.destroy();
  }
});

const initializePlayer = () => {
  player = new Plyr(videoElement.value, {
    captions: { active: true, update: true, language: 'en' },
    tooltips: { controls: true, seek: true },
    youtube: { noCookie: false, rel: 0, showinfo: 0, iv_load_policy: 3 },
  });

  player.on('ready', () => {
    if (props.resumeAt > 0) {
      player.currentTime = props.resumeAt;
    }
  });

  player.on('timeupdate', handleTimeUpdate);
  player.on('ended', () => {
    emit('video-completed');
  });
};

// --- Time Update & Quiz Interception logic ---
const handleTimeUpdate = (event) => {
  const currentTime = player.currentTime;

  emit('progress-update', Math.floor(currentTime));

  // Check if we need to trigger an interactive quiz
  if (props.quizzes && props.quizzes.length > 0 && !activeQuiz.value) {
    const upcomingQuiz = props.quizzes.find((q) => {
      // If within a 1-second window of the timestamp and it hasn't been completed yet
      return (
        currentTime >= q.timestamp_seconds &&
        currentTime < q.timestamp_seconds + 1 &&
        !completedQuizzes.value.has(q.id)
      );
    });

    if (upcomingQuiz) {
      triggerQuiz(upcomingQuiz);
    }
  }
};

const triggerQuiz = (quiz) => {
  player.pause();
  activeQuiz.value = quiz;
  // Enter fullscreen if you aren't or adjust layout
};

const submitQuizAnswer = (option) => {
  // Normally, hit the backend to check if the answer is correct
  // For safety, let's assume `option.is_correct` exists if checking frontend
  if (option.is_correct) {
    completedQuizzes.value.add(activeQuiz.value.id);
    activeQuiz.value = null; // Dismiss modal
    player.play(); // Resume playback
  } else {
    alert("Incorrect! Please try again to continue.");
  }
};
</script>

<template>
  <div class="exam-player-container min-h-screen bg-gray-50 flex flex-col select-none">
    
    <!-- Top Nav: Secure UI (Hides typical navigation) -->
    <header class="bg-indigo-900 text-white p-4 flex justify-between items-center shadow-md drop-shadow z-10 sticky top-0">
      <div>
        <h1 class="text-xl font-bold">{{ examTitle }}</h1>
        <p class="text-sm text-indigo-200">Question {{ currentIndex + 1 }} of {{ questions.length }}</p>
      </div>

      <!-- Secure Server-Validated Timer -->
      <div class="flex items-center gap-4 bg-indigo-800 px-4 py-2 rounded-lg font-mono text-lg" :class="{'text-red-400': timeRemaining < 300}">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ formattedTime }}
      </div>

      <button @click="confirmSubmit" class="bg-green-500 hover:bg-green-600 px-6 py-2 rounded-md font-semibold transition-colors">
        End Exam
      </button>
    </header>

    <!-- Main Exam Body -->
    <div class="flex-1 flex overflow-hidden">
      
      <!-- Main Activity Area -->
      <main class="flex-1 overflow-y-auto p-8 flex justify-center">
        <div class="max-w-3xl w-full bg-white rounded-xl shadow-sm border border-gray-200 p-8 h-fit">
          <div v-if="currentQuestion">
            <!-- Domain Tag -->
            <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded mb-4 uppercase tracking-wide">
              {{ currentQuestion.domain }}
            </span>
            
            <h2 class="text-2xl font-medium text-gray-900 mb-6 leading-relaxed">
              {{ currentQuestion.stem }}
            </h2>

            <div class="space-y-3">
              <label 
                v-for="option in currentQuestion.options" 
                :key="option.id"
                class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-indigo-50 transition-colors"
                :class="{'bg-indigo-50 border-indigo-300 ring-1 ring-indigo-300': isSelected(option.id)}"
              >
                <div class="flex items-center h-5">
                  <input 
                    :type="currentQuestion.type === 'multiple_select' ? 'checkbox' : 'radio'" 
                    :name="'q_' + currentQuestion.id"
                    :value="option.id"
                    v-model="answers[currentQuestion.id]"
                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                    
                  >
                </div>
                <div class="ml-3 text-sm">
                  <span class="font-medium text-gray-900 block">{{ option.text }}</span>
                </div>
              </label>
            </div>
          </div>
        </div>
      </main>

      <!-- Sidebar Outline / Map Navigator -->
      <aside class="w-80 bg-white border-l border-gray-200 flex flex-col hidden md:flex">
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
          <h3 class="font-semibold text-gray-700">Exam Navigator</h3>
          <span class="text-xs font-medium bg-gray-200 px-2 py-1 rounded text-gray-600">
            {{ answeredCount }} / {{ questions.length }} Answered
          </span>
        </div>
        
        <div class="flex-1 overflow-y-auto p-4">
          <div class="grid grid-cols-5 gap-2">
            <button 
              v-for="(q, index) in questions" 
              :key="q.id"
              @click="currentIndex = index"
              class="h-10 w-10 rounded-md font-medium text-sm flex items-center justify-center transition-all"
              :class="[
                currentIndex === index ? 'ring-2 ring-indigo-600 ring-offset-2 scale-110 z-10' : '',
                answers[q.id] && answers[q.id].length > 0 ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-white text-gray-500 border border-gray-300 hover:bg-gray-50'
              ]"
            >
              {{ index + 1 }}
            </button>
          </div>
        </div>

        <!-- Footer Nav -->
        <div class="p-4 border-t border-gray-200 flex gap-3">
          <button @click="prev" :disabled="currentIndex === 0" class="flex-1 bg-white border border-gray-300 rounded px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
            Previous
          </button>
          <button @click="next" :disabled="currentIndex === questions.length - 1" class="flex-1 bg-indigo-600 border border-transparent rounded px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
            Next
          </button>
        </div>
      </aside>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
  attemptId: Number,
  examTitle: String,
  timeLimitMinutes: Number,
  initialQuestions: Array
});

const questions = ref(props.initialQuestions || []);
const answers = ref({}); // Maps question_id => selected option ID(s)
const currentIndex = ref(0);
const timeRemaining = ref(props.timeLimitMinutes * 60);
let timerInterval = null;
let saveInterval = null;

const currentQuestion = computed(() => questions.value[currentIndex.value]);

const answeredCount = computed(() => {
  return Object.values(answers.value).filter(val => {
    if (Array.isArray(val)) return val.length > 0;
    return val !== null && val !== undefined;
  }).length;
});

const formattedTime = computed(() => {
  const minutes = Math.floor(timeRemaining.value / 60);
  const seconds = timeRemaining.value % 60;
  return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

onMounted(() => {
  // Initialize answers object based on multiple select vs multiple choice
  questions.value.forEach(q => {
    answers.value[q.id] = q.type === 'multiple_select' ? [] : null;
  });

  // Start Secure Timer
  timerInterval = setInterval(() => {
    if (timeRemaining.value > 0) {
      timeRemaining.value--;
    } else {
      autoSubmit();
    }
  }, 1000);

  // Auto-saver (Every 1 minute)
  saveInterval = setInterval(() => {
    axios.post(`/api/exams/attempts/${props.attemptId}/pause`, { current_answers: answers.value });
  }, 60000);

  // Anti-cheating logic (simplified)
  window.addEventListener('blur', handleBlur);
});

onBeforeUnmount(() => {
  clearInterval(timerInterval);
  clearInterval(saveInterval);
  window.removeEventListener('blur', handleBlur);
});

const handleBlur = () => {
  console.warn("Possible cheating detected: user moved away from the tab.");
  // Optional: Auto-pause or flag attempt
};

const isSelected = (optionId) => {
  const val = answers.value[currentQuestion.value.id];
  if (Array.isArray(val)) return val.includes(optionId);
  return val === optionId;
};

const prev = () => { if (currentIndex.value > 0) currentIndex.value--; };
const next = () => { if (currentIndex.value < questions.value.length - 1) currentIndex.value++; };

const formatPayload = () => {
  return Object.keys(answers.value).map(qId => ({
    question_id: parseInt(qId),
    selected_ids: Array.isArray(answers.value[qId]) ? answers.value[qId] : [answers.value[qId]]
  }));
};

const confirmSubmit = () => {
  if (confirm("Are you sure you want to completely submit your exam? You cannot change your answers after this.")) {
    submitExam();
  }
};

const autoSubmit = () => {
  alert("Time is up! Your exam and current progress have been automatically submitted.");
  submitExam();
};

const submitExam = async () => {
  clearInterval(timerInterval);
  try {
    const response = await axios.post(`/api/exams/attempts/${props.attemptId}/submit`, {
      responses: formatPayload()
    });
    // Redirect to results page via Inertia usually
    alert(`Exam completed! Score: ${response.data.score}%`);
  } catch (err) {
    console.error("Submission failed", err);
    alert("Connection error! Continuing to hold progress.");
  }
};
</script>

<style scoped>
/* Disable copying to prevent prompt stealing */
.select-none {
  user-select: none;
}
</style>

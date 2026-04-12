<template>
  <div class="p-6 max-w-7xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Grading Portal</h1>
      <p class="text-sm text-gray-500">Review and grade pending learner assignments.</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Learner</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200 cursor-pointer">
          <tr v-for="sub in submissions.data" :key="sub.id" class="hover:bg-gray-50" @click="openGradingModal(sub)">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">{{ sub.user.name }}</div>
              <div class="text-sm text-gray-500">{{ sub.user.email }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ sub.assignment.title }}</div>
              <div class="text-xs text-gray-500">{{ sub.assignment.course.title }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ new Date(sub.submitted_at).toLocaleDateString() }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-3 py-1 rounded">Grade</button>
            </td>
          </tr>
          <tr v-if="submissions.data.length === 0">
            <td colspan="4" class="px-6 py-12 text-center text-gray-500">All caught up! No pending assignments.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Quick Grading Modal -->
    <div v-if="activeSubmission" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-lg font-bold text-gray-900">Grade Assignment</h3>
          <button @click="activeSubmission = null" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <div class="mb-6 p-4 bg-gray-50 rounded border text-sm text-gray-800 h-48 overflow-y-auto w-full">
          <strong>Submission Content:</strong><br/>
          {{ activeSubmission.content }}
        </div>

        <form @submit.prevent="submitGrade">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div class="md:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-1">Score (0-100)</label>
              <input type="number" v-model="form.score" required min="0" max="100" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700 mb-1">Feedback</label>
              <textarea v-model="form.feedback" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Constructive feedback..."></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="activeSubmission = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 w-32 flex justify-center">
              <span v-if="processing">Grading...</span>
              <span v-else>Save Grade</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  submissions: Object
});

const activeSubmission = ref(null);
const processing = ref(false);

const form = ref({
  score: null,
  feedback: ''
});

const openGradingModal = (sub) => {
  activeSubmission.value = sub;
  form.value.score = null;
  form.value.feedback = '';
};

const submitGrade = () => {
  processing.value = true;
  router.post(`/facilitator/grading/${activeSubmission.value.id}`, form.value, {
    onSuccess: () => {
      activeSubmission.value = null;
      processing.value = false;
    },
    onError: () => {
      processing.value = false;
    }
  });
};
</script>

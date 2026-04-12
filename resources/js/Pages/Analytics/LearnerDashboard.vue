<template>
  <div class="learner-dashboard p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">Performance Analytics</h1>
        <p class="text-gray-500 mt-1">Track your readiness and domain mastery.</p>
      </div>
      <div>
        <button class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 shadow-sm transition">
          Take Mock Exam
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Readiness Score Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center justify-center relative overflow-hidden">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-6 w-full text-center">Exam Readiness</h3>
        
        <!-- Simple CSS Donut representation -->
        <div class="relative w-40 h-40 flex items-center justify-center rounded-full" 
             :style="`background: conic-gradient(${readinessColor} ${readinessScore}%, #f3f4f6 ${readinessScore}% 100%);`">
          <div class="absolute inset-2 bg-white rounded-full flex flex-col items-center justify-center">
            <span class="text-4xl font-extrabold text-gray-900">{{ readinessScore }}%</span>
            <span class="text-xs text-gray-500 font-medium">Readiness</span>
          </div>
        </div>

        <p class="mt-6 text-center text-sm text-gray-600 px-4">
          {{ readinessMessage }}
        </p>
      </div>

      <!-- Domain Mastery Progress Bars -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
        <h3 class="text-md font-bold text-gray-800 mb-6 border-b pb-2">Domain Mastery Breakdown</h3>
        
        <div class="space-y-5 max-h-72 overflow-y-auto pr-2 custom-scrollbar">
          <div v-for="(data, domainName) in domainMastery" :key="domainName">
            <div class="flex justify-between text-sm mb-1">
              <span class="font-medium text-gray-700">{{ domainName }}</span>
              <span :class="getBandColor(data.status)" class="font-semibold">{{ data.percentage }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
              <div 
                class="h-2.5 rounded-full transition-all duration-1000" 
                :class="getBarColor(data.percentage)"
                :style="{ width: data.percentage + '%' }"
              ></div>
            </div>
            <div class="text-xs text-gray-400 mt-1 text-right">
              {{ data.correct }} / {{ data.total }} correct
            </div>
          </div>
          
          <div v-if="Object.keys(domainMastery).length === 0" class="text-center py-8 text-gray-500">
            No domain tracking data yet. Complete an assessment!
          </div>
        </div>
      </div>
      
    </div>

    <!-- Exam History Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-6 border-b border-gray-100">
         <h3 class="text-md font-bold text-gray-800">Recent Exam History</h3>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-600">
          <thead class="text-xs text-gray-500 uppercase bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3">Assessment Title</th>
              <th scope="col" class="px-6 py-3">Date Completed</th>
              <th scope="col" class="px-6 py-3">Time Spent</th>
              <th scope="col" class="px-6 py-3">Score</th>
              <th scope="col" class="px-6 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="exam in examHistory" :key="exam.id" class="border-b last:border-0 hover:bg-gray-50">
              <td class="px-6 py-4 font-medium text-gray-900">{{ exam.title }}</td>
              <td class="px-6 py-4">{{ exam.date }}</td>
              <td class="px-6 py-4">{{ exam.time_spent }}</td>
              <td class="px-6 py-4">
                <span class="px-2 py-1 rounded text-xs font-bold" 
                      :class="exam.score >= 70 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                  {{ exam.score }}%
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button class="text-indigo-600 hover:text-indigo-900 font-medium">Review</button>
              </td>
            </tr>
            <tr v-if="examHistory.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                You haven't completed any tracked exams yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  readinessScore: Number,
  domainMastery: Object,
  examHistory: Array
});

// Dynamic UI Logic
const readinessColor = computed(() => {
  if (props.readinessScore >= 80) return '#10B981'; // Green
  if (props.readinessScore >= 60) return '#F59E0B'; // Yellow/Orange
  return '#EF4444'; // Red
});

const readinessMessage = computed(() => {
  if (props.readinessScore >= 80) return "You are highly likely to pass the certification. Continue light reviews!";
  if (props.readinessScore >= 60) return "You are on track but need to focus on your weak domains to ensure a passing score.";
  if (props.readinessScore === 0) return "Not enough data. Take a mock exam to establish your baseline.";
  return "Significant study is recommended before attempting the actual certification exam.";
});

const getBarColor = (percentage) => {
  if (percentage >= 80) return 'bg-green-500';
  if (percentage >= 60) return 'bg-yellow-400';
  return 'bg-red-500';
};

const getBandColor = (status) => {
  if (status === 'Proficient') return 'text-green-600';
  if (status === 'Intermediate') return 'text-yellow-600';
  return 'text-red-600';
};
</script>

<style scoped>
/* Custom mini scrollbar for the domains */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
</style>

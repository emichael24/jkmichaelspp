<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Star, User, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    courseId: { type: Number, required: true },
    average: { type: Number, default: 0 },
    distribution: { type: Object, default: () => ({}) },
    reviews: { type: Array, default: () => [] },
    userRating: { type: Object, default: null },
    totalRatings: { type: Number, default: 0 },
    canRate: { type: Boolean, default: false },
});

const emit = defineEmits(['rated']);
const showForm = ref(false);
const hoverStar = ref(0);

const form = useForm({
    course_id: props.courseId,
    rating: props.userRating?.rating || 0,
    review: props.userRating?.review || '',
});

function submitRating() {
    form.post(route('course-ratings.store'), {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; emit('rated'); },
    });
}

function removeRating() {
    router.delete(route('course-ratings.destroy', props.courseId), {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.rating = 0;
            form.review = '';
            form.course_id = props.courseId;
        },
    });
}

const auth = usePage().props.auth;

const maxDistribution = computed(() => Math.max(...Object.values(props.distribution), 1));

function timeAgo(date) {
    const s = Math.floor((Date.now() - new Date(date)) / 1000);
    if (s < 60) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
}
</script>

<template>
    <div class="space-y-6">
        <!-- Summary -->
        <div class="flex items-start gap-6">
            <div class="text-center">
                <p class="text-4xl font-bold text-surface-900">{{ average.toFixed(1) }}</p>
                <div class="flex items-center gap-0.5 mt-1">
                    <Star v-for="i in 5" :key="i" :class="['w-4 h-4', i <= Math.round(average) ? 'text-warning-500 fill-warning-500' : 'text-surface-300']" />
                </div>
                <p class="text-sm text-surface-500 mt-1">{{ totalRatings }} ratings</p>
            </div>
            <div class="flex-1 space-y-1.5">
                <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2">
                    <span class="text-xs text-surface-500 w-3">{{ star }}</span>
                    <Star class="w-3 h-3 text-warning-500 fill-warning-500" />
                    <div class="flex-1 bg-surface-100 rounded-full h-2 overflow-hidden">
                        <div class="bg-warning-400 h-full rounded-full transition-all" :style="{ width: ((distribution[star] || 0) / maxDistribution * 100) + '%' }"></div>
                    </div>
                    <span class="text-xs text-surface-400 w-6 text-right">{{ distribution[star] || 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Rate Button -->
        <div v-if="auth?.user && props.canRate" class="flex items-center gap-2">
            <button v-if="!showForm && !userRating" @click="showForm = true" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
                Rate This Course
            </button>
            <button v-else-if="!showForm && userRating" @click="showForm = true" class="px-4 py-2 bg-surface-100 text-surface-700 rounded-lg text-sm font-medium hover:bg-surface-200 transition">
                Update Your Rating
            </button>
            <button
                v-if="userRating"
                @click="removeRating"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-danger-600 bg-danger-50 rounded-lg hover:bg-danger-100 transition"
            >
                <Trash2 class="w-4 h-4" />
                Remove
            </button>
        </div>
        <p v-else-if="auth?.user" class="text-sm text-surface-500">Enroll in the course to leave a rating.</p>

        <!-- Rating Form -->
        <div v-if="showForm" class="bg-surface-50 rounded-xl p-4">
            <p class="text-sm font-medium text-surface-700 mb-3">Your rating</p>
            <div class="flex items-center gap-1 mb-3">
                <button v-for="i in 5" :key="i" @click="form.rating = i" @mouseenter="hoverStar = i" @mouseleave="hoverStar = 0">
                    <Star :class="['w-7 h-7 transition-colors cursor-pointer', (hoverStar || form.rating) >= i ? 'text-warning-500 fill-warning-500' : 'text-surface-300']" />
                </button>
            </div>
            <textarea v-model="form.review" rows="3" placeholder="Share your experience (optional)" class="w-full px-3 py-2 border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500 text-sm"></textarea>
            <div class="flex gap-2 mt-3">
                <button @click="showForm = false" class="px-3 py-1.5 text-sm text-surface-600 hover:bg-surface-200 rounded-lg">Cancel</button>
                <button @click="submitRating" :disabled="!form.rating || form.processing" class="px-4 py-1.5 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition">
                    Submit Rating
                </button>
            </div>
        </div>

        <!-- Reviews -->
        <div v-if="reviews.length" class="space-y-4">
            <h3 class="text-sm font-semibold text-surface-700">Reviews</h3>
            <div v-for="review in reviews" :key="review.id" class="border-b border-surface-100 pb-4 last:border-0">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
                        <User class="w-3.5 h-3.5 text-primary-600" />
                    </div>
                    <span class="text-sm font-medium text-surface-800">{{ review.user?.first_name }}</span>
                    <div class="flex items-center gap-0.5">
                        <Star v-for="i in 5" :key="i" :class="['w-3 h-3', i <= review.rating ? 'text-warning-500 fill-warning-500' : 'text-surface-300']" />
                    </div>
                    <span class="text-xs text-surface-400">{{ timeAgo(review.created_at) }}</span>
                </div>
                <p v-if="review.review" class="text-sm text-surface-600 ml-9">{{ review.review }}</p>
            </div>
        </div>
    </div>
</template>

<template>
    <Link :href="`/courses/${course.slug}`" class="group block">
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-lg hover:shadow-primary-500/5 hover:border-primary-200 hover:-translate-y-0.5 h-full flex flex-col">
            <!-- Thumbnail -->
            <div class="relative aspect-[16/10] bg-surface-100 overflow-hidden">
                <img
                    v-if="course.thumbnail"
                    :src="course.thumbnail"
                    :alt="course.title"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                />
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50">
                    <BookOpen class="w-10 h-10 text-primary-300" />
                </div>

                <!-- Category badge -->
                <div v-if="course.category" class="absolute top-3 left-3">
                    <span class="bg-white/90 backdrop-blur-sm text-surface-700 text-[11px] font-semibold px-2.5 py-1 rounded-lg shadow-sm">
                        {{ course.category?.name || course.category }}
                    </span>
                </div>

                <!-- Progress overlay -->
                <div v-if="course.progress > 0" class="absolute bottom-0 left-0 right-0">
                    <div class="h-1 bg-surface-900/20">
                        <div
                            class="h-full bg-gradient-to-r from-primary-400 to-accent-400 transition-all duration-500"
                            :style="{ width: `${course.progress}%` }"
                        />
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 flex flex-col flex-1">
                <h3 class="font-semibold text-surface-900 group-hover:text-primary-600 transition-colors leading-snug line-clamp-2 mb-2">
                    {{ course.title }}
                </h3>

                <div class="flex items-center gap-2 text-xs text-surface-400 mb-3">
                    <span class="flex items-center gap-1">
                        <Layers class="w-3.5 h-3.5" />
                        {{ course.lessons_count || 0 }} lessons
                    </span>
                    <span class="text-surface-300">-</span>
                    <span class="flex items-center gap-1">
                        <Clock class="w-3.5 h-3.5" />
                        {{ course.duration_hours ? course.duration_hours + 'h' : 'Self-paced' }}
                    </span>
                </div>

                <div class="mt-auto flex items-center justify-between">
                    <!-- Difficulty -->
                    <div class="flex items-center gap-1">
                        <div
                            v-for="i in 3"
                            :key="i"
                            :class="[
                                'w-1.5 h-1.5 rounded-full',
                                i <= difficultyLevel ? difficultyColor : 'bg-surface-200',
                            ]"
                        />
                        <span class="text-[11px] text-surface-400 ml-1 capitalize">{{ course.level || 'beginner' }}</span>
                    </div>

                    <!-- Progress or price -->
                    <span v-if="course.progress > 0" class="text-xs font-semibold text-primary-600">
                        {{ course.progress }}%
                    </span>
                    <span v-else-if="course.price > 0" class="text-sm font-bold text-surface-900">
                        {{ displayCurrency }} {{ displayPrice }}
                    </span>
                    <span v-else class="text-xs font-semibold text-success-600 bg-success-50 px-2 py-0.5 rounded-full">
                        Free
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Clock, Layers } from 'lucide-vue-next';

const props = defineProps({
    course: { type: Object, required: true },
});

const difficultyLevel = computed(() => {
    const map = { 'beginner': 1, 'intermediate': 2, 'advanced': 3, 'expert': 3 };
    return map[props.course.level] || 1;
});

const difficultyColor = computed(() => {
    const map = { 1: 'bg-success-500', 2: 'bg-warning-500', 3: 'bg-danger-500' };
    return map[difficultyLevel.value] || 'bg-success-500';
});

const displayCurrency = computed(() => (props.course.currency || 'NGN').toUpperCase());

const displayPrice = computed(() => {
    const price = Number(props.course.discount_price || props.course.price || 0);
    return price.toLocaleString();
});
</script>

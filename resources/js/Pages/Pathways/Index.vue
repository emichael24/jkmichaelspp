<script setup>
import { router } from '@inertiajs/vue3';
import { Map, ChevronRight, BookOpen, TrendingUp } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ pathways: Array });
</script>

<template>
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-display font-bold text-surface-900">Career Pathways</h1>
            <p class="text-surface-500 mt-1">Follow structured learning paths toward your career goals</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card v-for="pathway in pathways" :key="pathway.id" class="overflow-hidden hover:shadow-lg transition cursor-pointer group" @click="router.visit(route('pathways.show', pathway.slug))">
                <div class="h-3 bg-gradient-to-r from-primary-500 to-accent-500"></div>
                <div class="p-5">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 flex items-center justify-center flex-shrink-0">
                            <Map class="w-5 h-5 text-primary-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-surface-900 group-hover:text-primary-600 transition">{{ pathway.title }}</h3>
                            <p class="text-sm text-surface-500 mt-0.5 line-clamp-2">{{ pathway.description }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-sm text-surface-500 mb-4">
                        <span class="flex items-center gap-1"><BookOpen class="w-4 h-4" />{{ pathway.courses_count }} courses</span>
                        <Badge v-if="pathway.level" variant="secondary" size="sm">{{ pathway.level }}</Badge>
                    </div>

                    <!-- Progress -->
                    <div>
                        <div class="flex justify-between text-sm mb-1.5">
                            <span class="text-surface-500">Progress</span>
                            <span class="font-semibold text-primary-600">{{ pathway.progress || 0 }}%</span>
                        </div>
                        <div class="bg-surface-100 rounded-full h-2.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-primary-500 to-accent-500 h-full rounded-full transition-all duration-500" :style="{ width: (pathway.progress || 0) + '%' }"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4 text-primary-600 text-sm font-medium">
                        View Pathway <ChevronRight class="w-4 h-4 ml-1" />
                    </div>
                </div>
            </Card>
        </div>

        <EmptyState v-if="!pathways?.length" title="No career pathways available" description="Pathways will be added by administrators." :icon="Map" />
    </div>
</template>

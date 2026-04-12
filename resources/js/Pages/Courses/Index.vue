<template>
    <Head title="Courses" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Course Catalog</h1>
                <p class="text-surface-500 mt-1 text-sm">Explore {{ totalCourses }} courses across {{ categories.length }} categories</p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-for="view in ['grid', 'list']"
                    :key="view"
                    :variant="currentView === view ? 'primary' : 'ghost'"
                    size="sm"
                    rounded
                    @click="currentView = view"
                >
                    <LayoutGrid v-if="view === 'grid'" class="w-4 h-4" />
                    <List v-else class="w-4 h-4" />
                </Button>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row gap-3">
                <!-- Search -->
                <div class="flex-1">
                    <Input
                        v-model="filterForm.search"
                        placeholder="Search courses, topics, skills..."
                        :icon="SearchIcon"
                        clearable
                        size="md"
                        @update:modelValue="debouncedFilter"
                    />
                </div>
                <!-- Filter pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                    <button
                        :class="[
                            'shrink-0 px-4 py-2 rounded-xl text-sm font-medium transition-all',
                            !filterForm.category ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-100 text-surface-600 hover:bg-surface-200',
                        ]"
                        @click="filterForm.category = ''; applyFilters()"
                    >
                        All
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        :class="[
                            'shrink-0 px-4 py-2 rounded-xl text-sm font-medium transition-all whitespace-nowrap',
                            filterForm.category == cat.id ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-100 text-surface-600 hover:bg-surface-200',
                        ]"
                        @click="filterForm.category = cat.id; applyFilters()"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <!-- Sort & Level filters -->
            <div class="flex flex-wrap items-center gap-3 mt-3 pt-3 border-t border-surface-100">
                <select v-model="filterForm.level" class="rounded-xl border-surface-200 text-sm px-3 py-1.5" @change="applyFilters">
                    <option value="">All Levels</option>
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="expert">Expert</option>
                </select>
                <select v-model="filterForm.type" class="rounded-xl border-surface-200 text-sm px-3 py-1.5" @change="applyFilters">
                    <option value="">All Types</option>
                    <option value="free">Free</option>
                    <option value="premium">Premium</option>
                    <option value="certified">Certified</option>
                </select>
                <select v-model="filterForm.sort" class="rounded-xl border-surface-200 text-sm px-3 py-1.5" @change="applyFilters">
                    <option value="">Newest</option>
                    <option value="popular">Most Popular</option>
                    <option value="rating">Highest Rated</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                </select>
                <button
                    v-if="hasActiveFilters"
                    class="text-xs text-surface-400 hover:text-danger-500 transition-colors ml-auto"
                    @click="clearFilters"
                >
                    Clear all filters
                </button>
            </div>
        </div>

        <!-- Course grid -->
        <div v-if="courses.data?.length" :class="currentView === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5' : 'space-y-4'">
            <CourseCard
                v-for="course in courses.data"
                :key="course.id"
                :course="course"
            />
        </div>

        <!-- Empty -->
        <EmptyState
            v-else
            title="No courses found"
            message="Try adjusting your search or filters to find what you're looking for."
            :icon="SearchIcon"
        >
            <template #action>
                <Button variant="secondary" size="sm" @click="clearFilters">Clear filters</Button>
            </template>
        </EmptyState>

        <!-- Pagination -->
        <div v-if="courses.last_page > 1" class="flex justify-center gap-1">
            <Link
                v-for="link in courses.links"
                :key="link.label"
                :href="link.url"
                :class="[
                    'px-3 py-2 rounded-xl text-sm transition-colors',
                    link.active ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 hover:bg-surface-100 border border-surface-200',
                    !link.url ? 'opacity-40 pointer-events-none' : '',
                ]"
                v-html="link.label"
                preserve-state
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import CourseCard from '@/Components/Course/CourseCard.vue';
import { Search as SearchIcon, LayoutGrid, List } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    courses: { type: Object, default: () => ({ data: [] }) },
    categories: { type: Array, default: () => [] },
    totalCourses: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
});

const currentView = ref('grid');

const filterForm = reactive({
    search: props.filters.search || '',
    category: props.filters.category || '',
    level: props.filters.level || '',
    type: props.filters.type || '',
    sort: props.filters.sort || '',
});

let filterTimeout = null;
const debouncedFilter = () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 400);
};

const applyFilters = () => {
    router.get('/courses', {
        search: filterForm.search || undefined,
        category: filterForm.category || undefined,
        level: filterForm.level || undefined,
        type: filterForm.type || undefined,
        sort: filterForm.sort || undefined,
    }, { preserveState: true, replace: true });
};

const hasActiveFilters = computed(() => {
    return filterForm.search || filterForm.category || filterForm.level || filterForm.type || filterForm.sort;
});

const clearFilters = () => {
    filterForm.search = '';
    filterForm.category = '';
    filterForm.level = '';
    filterForm.type = '';
    filterForm.sort = '';
    applyFilters();
};
</script>

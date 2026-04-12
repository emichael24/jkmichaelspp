<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { MessageSquare, Users, BookOpen, Search, Plus, ChevronRight } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ forums: Object, searchResults: { type: Object, default: null }, searchQuery: { type: String, default: '' } });

const search = ref(props.searchQuery);

function doSearch() {
    if (search.value.trim()) {
        router.get(route('forums.search'), { q: search.value }, { preserveState: true });
    }
}
</script>

<template>
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-display font-bold text-surface-900">Discussion Forums</h1>
                <p class="text-surface-500 mt-1">Ask questions, share insights, and connect with peers</p>
            </div>
        </div>

        <!-- Search -->
        <div class="mb-8 flex gap-3">
            <div class="flex-1 relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-surface-400" />
                <input v-model="search" @keyup.enter="doSearch" type="text" placeholder="Search threads..." class="w-full pl-10 pr-4 py-2.5 border border-surface-200 rounded-xl bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
            </div>
            <Button @click="doSearch" variant="primary">Search</Button>
        </div>

        <!-- Search Results -->
        <div v-if="searchResults && searchResults.length" class="mb-8">
            <h2 class="text-lg font-semibold text-surface-800 mb-4">Search Results for "{{ searchQuery }}"</h2>
            <div class="space-y-3">
                <Card v-for="thread in searchResults" :key="thread.id" class="p-4 hover:shadow-md transition cursor-pointer" @click="router.visit(route('forums.threads.show', thread.id))">
                    <h3 class="font-semibold text-surface-900">{{ thread.title }}</h3>
                    <p class="text-sm text-surface-500 mt-1 line-clamp-2">{{ thread.body }}</p>
                    <div class="flex items-center gap-3 mt-2 text-xs text-surface-400">
                        <span>by {{ thread.user?.first_name }} {{ thread.user?.last_name }}</span>
                        <span>in {{ thread.forum?.title }}</span>
                    </div>
                </Card>
            </div>
        </div>

        <!-- General Forums -->
        <section v-if="forums.general?.length" class="mb-8">
            <h2 class="text-lg font-semibold text-surface-800 mb-4 flex items-center gap-2">
                <MessageSquare class="w-5 h-5 text-primary-600" /> General Forums
            </h2>
            <div class="space-y-3">
                <Card v-for="forum in forums.general" :key="forum.id" class="p-4 hover:shadow-md transition cursor-pointer" @click="router.visit(route('forums.show', forum.id))">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ forum.title }}</h3>
                            <p class="text-sm text-surface-500 mt-0.5">{{ forum.description }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-surface-400">{{ forum.threads_count }} threads</span>
                            <ChevronRight class="w-5 h-5 text-surface-300" />
                        </div>
                    </div>
                </Card>
            </div>
        </section>

        <!-- Course Forums -->
        <section v-if="forums.courses?.length" class="mb-8">
            <h2 class="text-lg font-semibold text-surface-800 mb-4 flex items-center gap-2">
                <BookOpen class="w-5 h-5 text-accent-600" /> Course Forums
            </h2>
            <div class="space-y-3">
                <Card v-for="forum in forums.courses" :key="forum.id" class="p-4 hover:shadow-md transition cursor-pointer" @click="router.visit(route('forums.show', forum.id))">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ forum.title }}</h3>
                            <p class="text-sm text-surface-500 mt-0.5">{{ forum.course?.title }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-surface-400">{{ forum.threads_count }} threads</span>
                            <ChevronRight class="w-5 h-5 text-surface-300" />
                        </div>
                    </div>
                </Card>
            </div>
        </section>

        <!-- Cohort Forums -->
        <section v-if="forums.cohorts?.length" class="mb-8">
            <h2 class="text-lg font-semibold text-surface-800 mb-4 flex items-center gap-2">
                <Users class="w-5 h-5 text-success-600" /> Cohort Forums
            </h2>
            <div class="space-y-3">
                <Card v-for="forum in forums.cohorts" :key="forum.id" class="p-4 hover:shadow-md transition cursor-pointer" @click="router.visit(route('forums.show', forum.id))">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ forum.title }}</h3>
                            <p class="text-sm text-surface-500 mt-0.5">{{ forum.cohort?.name }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-surface-400">{{ forum.threads_count }} threads</span>
                            <ChevronRight class="w-5 h-5 text-surface-300" />
                        </div>
                    </div>
                </Card>
            </div>
        </section>

        <EmptyState v-if="!forums.general?.length && !forums.courses?.length && !forums.cohorts?.length" title="No forums available" description="Forums will appear here once you enroll in courses or join cohorts." :icon="MessageSquare" />
    </div>
</template>

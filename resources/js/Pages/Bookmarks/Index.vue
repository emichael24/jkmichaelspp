<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-display font-bold text-surface-900">Bookmarks</h1>
                    <p class="text-sm text-surface-500 mt-1">Your saved courses and lessons</p>
                </div>
            </div>

            <!-- Bookmarks List -->
            <div v-if="bookmarks.data.length" class="space-y-3">
                <div
                    v-for="bookmark in bookmarks.data"
                    :key="bookmark.id"
                    class="bg-white rounded-xl border border-surface-200/60 p-4 hover:shadow-md transition-shadow"
                >
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div :class="[
                            'w-10 h-10 rounded-xl flex items-center justify-center shrink-0',
                            bookmark.bookmarkable_type.includes('Course') ? 'bg-primary-100' : 'bg-accent-100',
                        ]">
                            <BookOpen v-if="bookmark.bookmarkable_type.includes('Course')" class="w-5 h-5 text-primary-600" />
                            <FileText v-else class="w-5 h-5 text-accent-600" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <Link
                                :href="getBookmarkUrl(bookmark)"
                                class="text-base font-semibold text-surface-900 hover:text-primary-600 transition-colors line-clamp-1"
                            >
                                {{ bookmark.bookmarkable?.title || 'Untitled' }}
                            </Link>
                            <p class="text-sm text-surface-500 mt-0.5">
                                {{ bookmark.bookmarkable_type.includes('Course') ? 'Course' : 'Lesson' }}
                                <span class="mx-1.5 text-surface-300">&middot;</span>
                                Saved {{ formatDate(bookmark.created_at) }}
                            </p>
                            <p v-if="bookmark.note" class="text-sm text-surface-600 mt-1.5 bg-surface-50 rounded-lg px-3 py-2">
                                {{ bookmark.note }}
                            </p>
                        </div>

                        <!-- Remove -->
                        <button
                            @click="removeBookmark(bookmark.id)"
                            class="p-2 rounded-lg text-surface-400 hover:text-danger-500 hover:bg-danger-50 transition-colors shrink-0"
                        >
                            <BookmarkX class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="bookmarks.last_page > 1" class="flex justify-center pt-4">
                    <div class="flex gap-1">
                        <Link
                            v-for="link in bookmarks.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                                link.active
                                    ? 'bg-primary-600 text-white'
                                    : link.url
                                        ? 'text-surface-600 hover:bg-surface-100'
                                        : 'text-surface-300 cursor-default',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white rounded-xl border border-surface-200/60 p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-surface-100 flex items-center justify-center mx-auto mb-4">
                    <Bookmark class="w-8 h-8 text-surface-400" />
                </div>
                <h3 class="text-lg font-semibold text-surface-900 mb-1">No bookmarks yet</h3>
                <p class="text-sm text-surface-500 mb-4">Save courses and lessons to quickly find them later.</p>
                <Link
                    href="/courses"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors"
                >
                    <BookOpen class="w-4 h-4" />
                    Browse Courses
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BookOpen, FileText, Bookmark, BookmarkX } from 'lucide-vue-next';

defineProps({
    bookmarks: Object,
});

const getBookmarkUrl = (bookmark) => {
    if (bookmark.bookmarkable_type.includes('Course')) {
        return `/courses/${bookmark.bookmarkable?.slug || bookmark.bookmarkable_id}`;
    }
    return `/lessons/${bookmark.bookmarkable_id}`;
};

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const removeBookmark = (id) => {
    if (confirm('Remove this bookmark?')) {
        router.delete(`/bookmarks/${id}`);
    }
};
</script>

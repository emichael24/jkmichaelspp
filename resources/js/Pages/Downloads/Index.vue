<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Download, Trash2, BookOpen, Wifi, WifiOff, RefreshCw, HardDrive, CheckCircle } from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { getDownloadedLessons, removeOfflineLesson, requestBackgroundSync, setupNetworkStatus } from '@/pwa';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const downloadedLessons = ref([]);
const isOnline = ref(navigator.onLine);
const syncing = ref(false);

onMounted(async () => {
    loadDownloads();
    setupNetworkStatus(
        () => { isOnline.value = true; },
        () => { isOnline.value = false; }
    );
});

async function loadDownloads() {
    try {
        downloadedLessons.value = await getDownloadedLessons();
    } catch { downloadedLessons.value = []; }
}

async function removeDownload(lessonId) {
    await removeOfflineLesson(lessonId);
    await loadDownloads();
}

async function syncNow() {
    syncing.value = true;
    try {
        await requestBackgroundSync('sync-progress');
        await requestBackgroundSync('sync-quiz-attempts');
    } catch (err) {
        console.warn('Sync not available:', err);
    }
    setTimeout(() => { syncing.value = false; }, 2000);
}

function formatSize(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function formatDate(ts) {
    return new Date(ts).toLocaleDateString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-display font-bold text-surface-900">Downloads & Offline</h1>
                <p class="text-surface-500 mt-1">Manage offline content and sync progress</p>
            </div>
            <div class="flex items-center gap-3">
                <Badge :variant="isOnline ? 'success' : 'warning'" class="gap-1.5 py-1.5 px-3">
                    <component :is="isOnline ? Wifi : WifiOff" class="w-4 h-4" />
                    {{ isOnline ? 'Online' : 'Offline' }}
                </Badge>
                <Button @click="syncNow" variant="secondary" :disabled="syncing || !isOnline" class="gap-2">
                    <RefreshCw :class="['w-4 h-4', syncing && 'animate-spin']" />
                    Sync Now
                </Button>
            </div>
        </div>

        <!-- Offline Banner -->
        <Card v-if="!isOnline" class="p-5 mb-6 bg-warning-50 border border-warning-200">
            <div class="flex items-center gap-3">
                <WifiOff class="w-6 h-6 text-warning-600" />
                <div>
                    <h3 class="font-semibold text-warning-800">You're currently offline</h3>
                    <p class="text-sm text-warning-600">Your progress will be synced automatically when you reconnect.</p>
                </div>
            </div>
        </Card>

        <!-- Storage Info -->
        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <Card class="p-4 text-center">
                <HardDrive class="w-6 h-6 text-primary-500 mx-auto mb-2" />
                <p class="text-2xl font-bold text-surface-900">{{ downloadedLessons.length }}</p>
                <p class="text-xs text-surface-500">Downloaded Lessons</p>
            </Card>
            <Card class="p-4 text-center">
                <Download class="w-6 h-6 text-accent-500 mx-auto mb-2" />
                <p class="text-2xl font-bold text-surface-900">{{ formatSize(downloadedLessons.reduce((s, l) => s + (l.size || 0), 0)) }}</p>
                <p class="text-xs text-surface-500">Storage Used</p>
            </Card>
            <Card class="p-4 text-center">
                <CheckCircle class="w-6 h-6 text-success-500 mx-auto mb-2" />
                <p class="text-2xl font-bold text-surface-900">{{ isOnline ? 'Synced' : 'Pending' }}</p>
                <p class="text-xs text-surface-500">Sync Status</p>
            </Card>
        </div>

        <!-- Downloaded Lessons -->
        <Card class="p-0" v-if="downloadedLessons.length">
            <div class="p-4 border-b border-surface-100">
                <h2 class="font-semibold text-surface-800">Downloaded Lessons</h2>
            </div>
            <div class="divide-y divide-surface-100">
                <div v-for="lesson in downloadedLessons" :key="lesson.lessonId" class="flex items-center justify-between p-4 hover:bg-surface-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                            <BookOpen class="w-5 h-5 text-primary-600" />
                        </div>
                        <div>
                            <p class="font-medium text-surface-900">{{ lesson.title || `Lesson #${lesson.lessonId}` }}</p>
                            <p class="text-xs text-surface-500">Downloaded {{ formatDate(lesson.downloadedAt) }} - {{ formatSize(lesson.size) }}</p>
                        </div>
                    </div>
                    <button @click="removeDownload(lesson.lessonId)" class="p-2 rounded-lg hover:bg-error-50">
                        <Trash2 class="w-4 h-4 text-error-500" />
                    </button>
                </div>
            </div>
        </Card>

        <EmptyState v-else title="No downloads yet" description="Download lessons to access them offline. Look for the download button on lesson pages." :icon="Download" />
    </div>
</template>

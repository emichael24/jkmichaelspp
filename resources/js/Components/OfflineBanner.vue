<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { WifiOff, Wifi } from 'lucide-vue-next';

const isOnline = ref(true);
const showBanner = ref(false);

function goOnline() {
    isOnline.value = true;
    showBanner.value = true;
    setTimeout(() => { showBanner.value = false; }, 3000);
}

function goOffline() {
    isOnline.value = false;
    showBanner.value = true;
}

onMounted(() => {
    isOnline.value = navigator.onLine;
    window.addEventListener('online', goOnline);
    window.addEventListener('offline', goOffline);
});

onUnmounted(() => {
    window.removeEventListener('online', goOnline);
    window.removeEventListener('offline', goOffline);
});
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-200 ease-in"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div v-if="showBanner" :class="['fixed top-0 left-0 right-0 z-[100] flex items-center justify-center gap-2 py-2 px-4 text-sm font-medium', isOnline ? 'bg-success-500 text-white' : 'bg-warning-500 text-white']">
            <component :is="isOnline ? Wifi : WifiOff" class="w-4 h-4" />
            <span>{{ isOnline ? 'You\'re back online! Syncing your data...' : 'You\'re offline. Changes will be saved locally.' }}</span>
            <button @click="showBanner = false" class="ml-2 opacity-70 hover:opacity-100">x</button>
        </div>
    </Transition>
</template>

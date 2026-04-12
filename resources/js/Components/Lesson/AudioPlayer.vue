<template>
    <div class="space-y-4">
        <!-- Player card -->
        <div class="rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 p-6 sm:p-8 text-white">
            <!-- Decorative -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-accent-400/10 rounded-full blur-2xl" />

            <div class="relative z-10 flex flex-col items-center text-center">
                <!-- Artwork / icon -->
                <div class="w-20 h-20 rounded-2xl bg-white/10 backdrop-blur-sm flex items-center justify-center mb-4 border border-white/10">
                    <Headphones class="w-10 h-10 text-white/80" />
                </div>

                <h3 class="font-semibold text-lg mb-1">{{ lessonTitle }}</h3>
                <p class="text-primary-200 text-sm mb-6">Audio Lesson</p>

                <!-- Progress bar -->
                <div class="w-full max-w-md mb-2">
                    <input
                        type="range"
                        :value="currentTime"
                        :max="duration"
                        step="1"
                        class="w-full h-1.5 accent-white cursor-pointer"
                        @input="seekTo($event.target.value)"
                    />
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs font-mono text-primary-200">{{ formatTime(currentTime) }}</span>
                        <span class="text-xs font-mono text-primary-200">{{ formatTime(duration) }}</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center gap-6">
                    <!-- Rewind 15s -->
                    <button @click="skip(-15)" class="text-white/70 hover:text-white transition-colors">
                        <RotateCcw class="w-5 h-5" />
                    </button>

                    <!-- Play / Pause -->
                    <button
                        @click="togglePlay"
                        class="w-14 h-14 rounded-full bg-white flex items-center justify-center hover:bg-white/90 transition-colors shadow-lg"
                    >
                        <Pause v-if="isPlaying" class="w-6 h-6 text-primary-700" />
                        <Play v-else class="w-6 h-6 text-primary-700 ml-0.5" />
                    </button>

                    <!-- Forward 15s -->
                    <button @click="skip(15)" class="text-white/70 hover:text-white transition-colors">
                        <RotateCw class="w-5 h-5" />
                    </button>
                </div>

                <!-- Speed control -->
                <div class="flex items-center gap-2 mt-4">
                    <span class="text-xs text-primary-200">Speed:</span>
                    <div class="flex gap-1">
                        <button
                            v-for="speed in speeds"
                            :key="speed"
                            @click="setSpeed(speed)"
                            :class="[
                                'px-2 py-1 rounded-md text-xs font-medium transition-colors',
                                playbackRate === speed
                                    ? 'bg-white text-primary-700'
                                    : 'bg-white/10 text-primary-200 hover:bg-white/20',
                            ]"
                        >
                            {{ speed }}x
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden audio element -->
        <audio
            ref="audioEl"
            :src="audioUrl"
            @timeupdate="onTimeUpdate"
            @loadedmetadata="onMetaLoaded"
            @ended="onEnded"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            preload="metadata"
        />

        <!-- Empty state -->
        <div v-if="!audioUrl" class="text-center py-12">
            <Headphones class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500 text-sm">No audio available for this lesson.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Headphones, Play, Pause, RotateCcw, RotateCw } from 'lucide-vue-next';

const props = defineProps({
    audioUrl: { type: String, default: null },
    durationSeconds: { type: Number, default: 0 },
    lessonTitle: { type: String, default: 'Audio Lesson' },
});

const audioEl = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(props.durationSeconds || 0);
const playbackRate = ref(1);
const speeds = [0.75, 1, 1.25, 1.5, 2];

function togglePlay() {
    if (!audioEl.value) return;
    if (isPlaying.value) {
        audioEl.value.pause();
    } else {
        audioEl.value.play();
    }
}

function onTimeUpdate() {
    if (audioEl.value) {
        currentTime.value = Math.floor(audioEl.value.currentTime);
    }
}

function onMetaLoaded() {
    if (audioEl.value) {
        duration.value = Math.floor(audioEl.value.duration);
    }
}

function onEnded() {
    isPlaying.value = false;
}

function seekTo(time) {
    if (audioEl.value) {
        audioEl.value.currentTime = Number(time);
        currentTime.value = Number(time);
    }
}

function skip(seconds) {
    if (audioEl.value) {
        audioEl.value.currentTime = Math.max(0, Math.min(duration.value, audioEl.value.currentTime + seconds));
    }
}

function setSpeed(speed) {
    if (audioEl.value) {
        playbackRate.value = speed;
        audioEl.value.playbackRate = speed;
    }
}

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div class="space-y-4">
        <!-- Video container -->
        <HostedVideo v-if="hostedVideo(videoUrl)" ref="hostedPlayer" :key="videoUrl" :url="videoUrl" :initial-position="initialPosition" @time-update="currentTime = $event" @position-update="$emit('position-update', $event)" />
        <div v-else class="relative aspect-video rounded-xl overflow-hidden bg-surface-900">
            <video
                ref="videoEl"
                :src="videoUrl"
                class="w-full h-full object-contain"
                @timeupdate="onTimeUpdate"
                @loadedmetadata="onMetaLoaded"
                @ended="onEnded"
                @play="isPlaying = true"
                @pause="isPlaying = false"
                controlsList="nodownload"
                playsinline
            />

            <!-- Custom overlay controls -->
            <div
                :class="[
                    'absolute inset-0 flex items-center justify-center transition-opacity',
                    showControls ? 'opacity-100' : 'opacity-0',
                ]"
                @click="togglePlay"
                @mousemove="showControlsTemporarily"
            >
                <button
                    v-if="!isPlaying"
                    class="w-16 h-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center hover:bg-white/30 transition-colors"
                >
                    <Play class="w-8 h-8 text-white ml-1" />
                </button>
            </div>

            <!-- Bottom gradient -->
            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4 pt-8">
                <!-- Progress scrubber -->
                <div class="flex items-center gap-3 mb-2">
                    <input
                        type="range"
                        :value="currentTime"
                        :max="duration"
                        step="1"
                        class="flex-1 h-1 accent-primary-500 cursor-pointer"
                        @input="seekTo($event.target.value)"
                    />
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Play/Pause -->
                        <button @click.stop="togglePlay" class="text-white hover:text-primary-300 transition-colors">
                            <Pause v-if="isPlaying" class="w-5 h-5" />
                            <Play v-else class="w-5 h-5" />
                        </button>

                        <!-- Volume -->
                        <div class="flex items-center gap-1.5">
                            <button @click.stop="toggleMute" class="text-white hover:text-primary-300 transition-colors">
                                <VolumeX v-if="isMuted" class="w-4 h-4" />
                                <Volume2 v-else class="w-4 h-4" />
                            </button>
                            <input
                                type="range"
                                :value="volume"
                                max="1"
                                step="0.1"
                                class="w-16 h-1 accent-white cursor-pointer"
                                @input="setVolume($event.target.value)"
                            />
                        </div>

                        <!-- Time -->
                        <span class="text-xs text-white/80 font-mono">
                            {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Speed -->
                        <div class="relative">
                            <button
                                @click.stop="showSpeedMenu = !showSpeedMenu"
                                class="text-xs text-white/80 hover:text-white font-medium px-2 py-1 rounded bg-white/10 hover:bg-white/20 transition-colors"
                            >
                                {{ playbackRate }}x
                            </button>
                            <div
                                v-if="showSpeedMenu"
                                class="absolute bottom-full right-0 mb-2 bg-surface-800 rounded-lg shadow-lg overflow-hidden"
                            >
                                <button
                                    v-for="speed in speeds"
                                    :key="speed"
                                    @click.stop="setSpeed(speed)"
                                    :class="[
                                        'block w-full px-4 py-2 text-xs text-left transition-colors',
                                        playbackRate === speed
                                            ? 'bg-primary-600 text-white'
                                            : 'text-white/80 hover:bg-surface-700',
                                    ]"
                                >
                                    {{ speed }}x
                                </button>
                            </div>
                        </div>

                        <!-- Captions toggle -->
                        <button
                            @click.stop="captionsOn = !captionsOn"
                            :class="['text-white/80 hover:text-white transition-colors', captionsOn ? 'text-primary-400' : '']"
                            title="Toggle captions"
                        >
                            <Subtitles class="w-4 h-4" />
                        </button>

                        <!-- Fullscreen -->
                        <button @click.stop="toggleFullscreen" class="text-white/80 hover:text-white transition-colors">
                            <Maximize2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chapters -->
        <div v-if="chapters && chapters.length" class="rounded-xl border border-surface-200 p-4">
            <h3 class="font-semibold text-surface-900 text-sm mb-3 flex items-center gap-2">
                <List class="w-4 h-4 text-primary-500" />
                Chapters
            </h3>
            <div class="space-y-1">
                <button
                    v-for="(chapter, idx) in chapters"
                    :key="idx"
                    @click="seekTo(chapter.time)"
                    :class="[
                        'flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm text-left transition-colors',
                        isCurrentChapter(chapter, idx)
                            ? 'bg-primary-50 text-primary-700'
                            : 'text-surface-600 hover:bg-surface-50',
                    ]"
                >
                    <span class="text-xs font-mono text-surface-400 w-12 shrink-0">{{ formatTime(chapter.time) }}</span>
                    <span class="flex-1 truncate">{{ chapter.title }}</span>
                </button>
            </div>
        </div>

        <!-- Transcript -->
        <div v-if="transcript">
            <button
                @click="showTranscript = !showTranscript"
                class="flex items-center gap-2 text-sm font-medium text-surface-600 hover:text-primary-600 transition-colors"
            >
                <FileText class="w-4 h-4" />
                {{ showTranscript ? 'Hide' : 'Show' }} Transcript
            </button>
            <div v-if="showTranscript" class="mt-3 p-4 rounded-xl bg-surface-50 text-sm text-surface-700 leading-relaxed max-h-80 overflow-y-auto">
                <SafeHtml :content="transcript" />
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!videoUrl" class="text-center py-12">
            <VideoIcon class="w-12 h-12 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500 text-sm">No video available for this lesson.</p>
        </div>
    </div>
</template>

<script setup>
import HostedVideo from './HostedVideo.vue';
import { hostedVideo } from '@/lib/hostedVideo';
import SafeHtml from '@/Components/SafeHtml.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import {
    Play, Pause, Volume2, VolumeX, Maximize2, Subtitles,
    List, FileText, Video as VideoIcon,
} from 'lucide-vue-next';

const props = defineProps({
    videoUrl: { type: String, default: null },
    chapters: { type: Array, default: () => [] },
    transcript: { type: String, default: null },
    durationSeconds: { type: Number, default: 0 },
    initialPosition: { type: Number, default: 0 },
});

const emit = defineEmits(['position-update']);

const videoEl = ref(null);
const hostedPlayer = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(props.durationSeconds || 0);
const volume = ref(1);
const isMuted = ref(false);
const playbackRate = ref(1);
const showSpeedMenu = ref(false);
const showControls = ref(true);
const captionsOn = ref(false);
const showTranscript = ref(false);
const speeds = [0.75, 1, 1.25, 1.5, 2];

let controlsTimer = null;

onMounted(() => {
    if (videoEl.value && props.initialPosition > 0) {
        videoEl.value.currentTime = props.initialPosition;
    }
});

onBeforeUnmount(() => {
    // Emit final position on leave
    if (videoEl.value) {
        emit('position-update', Math.floor(videoEl.value.currentTime));
    }
});

function togglePlay() {
    if (!videoEl.value) return;
    if (isPlaying.value) {
        videoEl.value.pause();
    } else {
        videoEl.value.play();
    }
}

function onTimeUpdate() {
    if (!videoEl.value) return;
    currentTime.value = Math.floor(videoEl.value.currentTime);

    // Periodically emit for progress saving
    if (currentTime.value % 30 === 0 && currentTime.value > 0) {
        emit('position-update', currentTime.value);
    }
}

function onMetaLoaded() {
    if (videoEl.value) {
        duration.value = Math.floor(videoEl.value.duration);
    }
}

function onEnded() {
    isPlaying.value = false;
    emit('position-update', Math.floor(duration.value));
}

function seekTo(time) {
    if (hostedPlayer.value) {
        hostedPlayer.value.seekTo(time);
        currentTime.value = Number(time);
        return;
    }
    if (videoEl.value) {
        videoEl.value.currentTime = Number(time);
        currentTime.value = Number(time);
    }
}

function toggleMute() {
    if (!videoEl.value) return;
    isMuted.value = !isMuted.value;
    videoEl.value.muted = isMuted.value;
}

function setVolume(val) {
    if (!videoEl.value) return;
    volume.value = Number(val);
    videoEl.value.volume = volume.value;
    isMuted.value = volume.value === 0;
}

function setSpeed(speed) {
    if (!videoEl.value) return;
    playbackRate.value = speed;
    videoEl.value.playbackRate = speed;
    showSpeedMenu.value = false;
}

function toggleFullscreen() {
    const container = videoEl.value?.parentElement;
    if (!container) return;
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        container.requestFullscreen();
    }
}

function showControlsTemporarily() {
    showControls.value = true;
    clearTimeout(controlsTimer);
    controlsTimer = setTimeout(() => {
        if (isPlaying.value) showControls.value = false;
    }, 3000);
}

function isCurrentChapter(chapter, index) {
    const nextChapter = props.chapters[index + 1];
    return currentTime.value >= chapter.time && (!nextChapter || currentTime.value < nextChapter.time);
}

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

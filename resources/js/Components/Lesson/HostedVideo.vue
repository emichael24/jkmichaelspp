<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import { RotateCcw, CheckCircle2 } from 'lucide-vue-next';
import { hostedVideo } from '@/lib/hostedVideo';

const props = defineProps({ url: String, initialPosition: { type: Number, default: 0 } });
const emit = defineEmits(['position-update', 'time-update', 'ended']);
const source = computed(() => hostedVideo(props.url));
const element = ref(null), error = ref(false), finished = ref(false);
let player, lastPosition = -1;

function seekTo(time) {
    const position = Number(time);
    if (player && Number.isFinite(position) && position >= 0) player.currentTime = position;
}
defineExpose({ seekTo });

function reportPosition(force = false) {
    const position = Math.floor(player?.currentTime || 0);
    if (Number.isFinite(position) && (force || position >= lastPosition + 15)) { lastPosition = position; emit('position-update', position); }
}

function watchAgain() {
    finished.value = false;
    player?.restart();
    player?.play();
}

onMounted(() => {
    if (!source.value || !element.value) return;
    try {
        player = new Plyr(element.value, {
            autoplay: false,
            ratio: '16:9',
            hideControls: true,
            // Return to the start when the video ends so YouTube's end screen of suggested videos never appears.
            resetOnEnd: true,
            controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'captions', 'settings', 'pip', 'fullscreen'],
            settings: ['captions', 'quality', 'speed'],
            speed: { selected: 1, options: [0.75, 1, 1.25, 1.5, 1.75, 2] },
            // Privacy-enhanced domain, academy controls instead of YouTube's, related videos limited to the same channel,
            // no annotations. YouTube no longer allows removing its logo, and adverts are controlled by the video owner.
            youtube: { noCookie: true, rel: 0, showinfo: 0, iv_load_policy: 3, modestbranding: 1, playsinline: 1, cc_load_policy: 0, fs: 0 },
            vimeo: { byline: false, portrait: false, title: false, dnt: true, transparent: false, playsinline: true },
        });
        player.on('ready', () => { if (props.initialPosition > 0) player.currentTime = props.initialPosition; });
        player.on('play', () => { finished.value = false; });
        player.on('timeupdate', () => { emit('time-update', Math.floor(player.currentTime || 0)); reportPosition(); });
        player.on('pause', () => reportPosition(true));
        player.on('ended', () => { reportPosition(true); finished.value = true; emit('ended'); });
        player.on('error', () => error.value = true);
    } catch { error.value = true; }
});
onBeforeUnmount(() => { if (player) { reportPosition(true); player.destroy(); } });
</script>

<template>
    <div class="overflow-hidden rounded-xl bg-surface-900">
        <div v-show="!finished" class="hosted-video">
            <div v-if="source" ref="element" :data-plyr-provider="source.provider" :data-plyr-embed-id="source.id" />
        </div>
        <div v-if="finished" class="grid aspect-video place-content-center justify-items-center gap-4 bg-surface-900 p-6 text-center text-white">
            <CheckCircle2 class="h-10 w-10 text-emerald-400" />
            <p class="text-lg font-semibold">Video complete</p>
            <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-5 py-2.5 text-sm font-semibold hover:bg-white/20" @click="watchAgain"><RotateCcw class="h-4 w-4" />Watch again</button>
        </div>
        <p v-if="error" role="alert" class="p-5 text-sm text-white">This video could not be loaded. Check that it is public or unlisted and allows embedding.</p>
    </div>
</template>

<style scoped>
/* Brand the player controls with the academy colour. */
.hosted-video :deep(.plyr) { --plyr-color-main: #c2410c; }
</style>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import { hostedVideo } from '@/lib/hostedVideo';
const props = defineProps({ url: String, initialPosition: { type: Number, default: 0 } });
const emit = defineEmits(['position-update', 'time-update']);
const source = computed(() => hostedVideo(props.url));
const element = ref(null), error = ref(false);
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
onMounted(() => {
    if (!source.value || !element.value) return;
    try {
        player = new Plyr(element.value, { autoplay: false, youtube: { noCookie: true, rel: 0 }, controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'settings', 'fullscreen'] });
        player.on('ready', () => { if (props.initialPosition > 0) player.currentTime = props.initialPosition; });
        player.on('timeupdate', () => { emit('time-update', Math.floor(player.currentTime || 0)); reportPosition(); });
        player.on('pause', () => reportPosition(true));
        player.on('ended', () => reportPosition(true));
        player.on('error', () => error.value = true);
    } catch { error.value = true; }
});
onBeforeUnmount(() => { if (player) { reportPosition(true); player.destroy(); } });
</script>
<template><div class="overflow-hidden rounded-xl bg-surface-900"><div v-if="source" ref="element" :data-plyr-provider="source.provider" :data-plyr-embed-id="source.id" /><p v-if="error" role="alert" class="p-5 text-sm text-white">This video could not be loaded. Check that it is available and allows embedding.</p></div></template>

<template>
    <div
        v-if="totalSeconds > 0"
        :class="[
            'flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold tabular-nums',
            isUrgent
                ? 'bg-danger-100 text-danger-700 animate-pulse'
                : isWarning
                    ? 'bg-warning-100 text-warning-700'
                    : 'bg-surface-100 text-surface-600',
        ]"
    >
        <Clock class="w-4 h-4" />
        <span>{{ formattedTime }}</span>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Clock } from 'lucide-vue-next';

const props = defineProps({
    totalSeconds: { type: Number, required: true },
});

const emit = defineEmits(['tick', 'expired']);

const remaining = ref(props.totalSeconds);
let interval = null;

const formattedTime = computed(() => {
    const hrs = Math.floor(remaining.value / 3600);
    const mins = Math.floor((remaining.value % 3600) / 60);
    const secs = remaining.value % 60;

    if (hrs > 0) {
        return `${hrs}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    return `${mins}:${String(secs).padStart(2, '0')}`;
});

const isWarning = computed(() => {
    return remaining.value <= 300 && remaining.value > 60;
});

const isUrgent = computed(() => {
    return remaining.value <= 60;
});

function startTimer() {
    interval = setInterval(() => {
        remaining.value--;
        emit('tick', remaining.value);

        if (remaining.value <= 0) {
            clearInterval(interval);
            emit('expired');
        }
    }, 1000);
}

onMounted(() => {
    if (props.totalSeconds > 0) {
        startTimer();
    }
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});

watch(() => props.totalSeconds, (newVal) => {
    if (interval) clearInterval(interval);
    remaining.value = newVal;
    if (newVal > 0) startTimer();
});
</script>

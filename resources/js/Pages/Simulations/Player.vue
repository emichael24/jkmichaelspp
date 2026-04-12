<template>
    <Head :title="`${simulation.title} — Playing`" />

    <div class="fixed inset-0 bg-surface-900 flex flex-col">
        <!-- Top bar -->
        <div class="h-14 bg-surface-800/80 backdrop-blur-sm border-b border-white/10 flex items-center justify-between px-6 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-white font-display font-bold text-sm">{{ simulation.title }}</h1>
            </div>
            <div class="flex items-center gap-4">
                <!-- Running scores (subtle) -->
                <div class="hidden md:flex items-center gap-3 text-xs text-white/50">
                    <span v-for="(val, key) in currentScores" :key="key" class="flex items-center gap-1">
                        {{ formatDimension(key) }}: <span class="text-white/80 font-semibold">{{ val }}</span>
                    </span>
                </div>
                <!-- Timer -->
                <div class="text-white/60 text-sm font-mono">{{ formattedTime }}</div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="flex-1 overflow-y-auto">
            <div class="max-w-3xl mx-auto px-6 py-10">
                <transition name="fade" mode="out-in">
                    <!-- Story/Consequence/Feedback Node -->
                    <div v-if="currentNode && !currentNode.is_end && currentNode.type !== 'decision'"
                         :key="`story-${currentNode.id}`"
                         class="space-y-6"
                    >
                        <!-- Node type indicator -->
                        <span :class="['px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider', nodeTypeBadge(currentNode.type)]">
                            {{ currentNode.type }}
                        </span>

                        <!-- Media -->
                        <img v-if="currentNode.media_url" :src="currentNode.media_url"
                             class="w-full rounded-xl max-h-80 object-cover" alt="" />

                        <!-- Content -->
                        <p class="text-white text-lg leading-relaxed whitespace-pre-line">{{ currentNode.content }}</p>

                        <!-- Continue button (auto-advance to next node via choices if only one) -->
                        <button v-if="currentNode.choices?.length === 1"
                                @click="makeChoice(currentNode.id, currentNode.choices[0].id)"
                                class="px-8 py-3 rounded-xl bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-colors">
                            Continue →
                        </button>
                        <button v-else-if="!currentNode.choices?.length"
                                @click="handleNoChoices"
                                class="px-8 py-3 rounded-xl bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-colors">
                            Continue →
                        </button>
                    </div>

                    <!-- Decision Node -->
                    <div v-else-if="currentNode && currentNode.type === 'decision'"
                         :key="`decision-${currentNode.id}`"
                         class="space-y-6"
                    >
                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-warning-500/20 text-warning-300">
                            Decision Point
                        </span>

                        <img v-if="currentNode.media_url" :src="currentNode.media_url"
                             class="w-full rounded-xl max-h-80 object-cover" alt="" />

                        <p class="text-white text-lg leading-relaxed whitespace-pre-line">{{ currentNode.content }}</p>

                        <!-- Decision cards -->
                        <div class="space-y-3">
                            <button
                                v-for="choice in currentNode.choices"
                                :key="choice.id"
                                @click="makeChoice(currentNode.id, choice.id)"
                                :disabled="choosing"
                                class="w-full text-left p-5 rounded-xl border-2 border-white/10 bg-white/5 hover:bg-white/10 hover:border-primary-500/50 transition-all group disabled:opacity-50"
                            >
                                <p class="text-white font-medium group-hover:text-primary-300 transition-colors">
                                    {{ choice.label }}
                                </p>
                            </button>
                        </div>
                    </div>

                    <!-- End Node -->
                    <div v-else-if="currentNode && currentNode.is_end"
                         :key="`end-${currentNode.id}`"
                         class="space-y-6 text-center"
                    >
                        <div class="w-20 h-20 mx-auto rounded-full bg-success-500/20 flex items-center justify-center">
                            <CheckCircle class="w-10 h-10 text-success-400" />
                        </div>

                        <p class="text-white text-lg leading-relaxed">{{ currentNode.content }}</p>

                        <button
                            @click="completeSimulation"
                            :disabled="completing"
                            class="px-10 py-4 rounded-xl bg-success-600 text-white font-bold text-lg hover:bg-success-700 transition-colors disabled:opacity-50"
                        >
                            {{ completing ? 'Completing...' : 'View Your Scorecard' }}
                        </button>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { CheckCircle } from 'lucide-vue-next';

const props = defineProps({
    simulation: { type: Object, required: true },
    attempt: { type: Object, required: true },
    node: { type: Object, required: true },
});

const currentNode = ref(props.node);
const currentScores = ref(props.attempt.scores || {
    judgment: 50, ethics: 50, stakeholder_handling: 50, risk_thinking: 50,
});
const choosing = ref(false);
const completing = ref(false);

// Timer
const startTime = Date.now();
const elapsed = ref(0);
let timerInterval = null;

const formattedTime = ref('00:00');

onMounted(() => {
    timerInterval = setInterval(() => {
        elapsed.value = Math.floor((Date.now() - startTime) / 1000);
        const m = Math.floor(elapsed.value / 60).toString().padStart(2, '0');
        const s = (elapsed.value % 60).toString().padStart(2, '0');
        formattedTime.value = `${m}:${s}`;
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(timerInterval);
});

async function makeChoice(nodeId, choiceId) {
    choosing.value = true;
    try {
        const response = await fetch(`/simulation-attempts/${props.attempt.id}/choose`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ node_id: nodeId, choice_id: choiceId }),
        });

        const data = await response.json();
        currentNode.value = data.node;
        currentScores.value = data.scores || currentScores.value;
    } catch (err) {
        console.error('Choice error:', err);
    } finally {
        choosing.value = false;
    }
}

function handleNoChoices() {
    // End node or broken path — complete
    completeSimulation();
}

async function completeSimulation() {
    completing.value = true;
    try {
        const response = await fetch(`/simulation-attempts/${props.attempt.id}/complete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ time_spent_seconds: elapsed.value }),
        });

        await response.json();
        // Navigate to report
        window.location.href = `/simulation-attempts/${props.attempt.id}/report`;
    } catch (err) {
        console.error('Complete error:', err);
        completing.value = false;
    }
}

function nodeTypeBadge(type) {
    const map = {
        story: 'bg-primary-500/20 text-primary-300',
        consequence: 'bg-warning-500/20 text-warning-300',
        feedback: 'bg-success-500/20 text-success-300',
    };
    return map[type] || 'bg-white/10 text-white/60';
}

function formatDimension(key) {
    const map = {
        judgment: 'JDG',
        ethics: 'ETH',
        stakeholder_handling: 'STK',
        risk_thinking: 'RSK',
    };
    return map[key] || key;
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>

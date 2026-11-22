<template>
    <Head title="AI Tutor" />

    <div v-if="error" role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</div>
    <div class="h-[calc(100dvh-11rem)] min-h-[560px] lg:min-h-0 lg:h-[calc(100vh-8rem)] flex flex-col lg:flex-row gap-4 overflow-hidden">
        <!-- Sessions Sidebar -->
        <div class="w-full lg:w-64 flex-shrink-0 bg-white rounded-2xl border border-surface-200/60 shadow-sm flex flex-col overflow-hidden">
            <div class="p-3 lg:p-4 border-b border-surface-100 grid grid-cols-2 lg:block gap-2">
                <button
                    @click="startNewSession"
                    :disabled="isThinking || loadingSession"
                    class="w-full py-2 px-3 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition-colors flex items-center justify-center gap-2"
                >
                    <Plus class="w-4 h-4" /> New Chat
                </button>
                <label for="mobile-chat-session" class="sr-only">Recent conversations</label>
                <select id="mobile-chat-session" class="lg:hidden w-full min-w-0 rounded-xl border border-surface-200 px-2 text-sm" :value="activeSession?.id || ''" :disabled="isThinking || loadingSession" @change="loadSelectedSession">
                    <option value="">Recent conversations</option>
                    <option v-for="session in sessions" :key="session.id" :value="session.id">{{ session.title }}</option>
                </select>
            </div>

            <div class="hidden lg:block lg:flex-1 overflow-y-auto p-2 space-y-1">
                <button
                    v-for="session in sessions"
                    :key="session.id"
                    @click="loadSession(session)"
                    :disabled="isThinking || loadingSession"
                    :class="[
                        'w-full text-left px-3 py-2.5 rounded-xl text-sm transition-colors group',
                        activeSession?.id === session.id
                            ? 'bg-primary-50 text-primary-900'
                            : 'text-surface-700 hover:bg-surface-100',
                    ]"
                >
                    <p class="font-medium truncate">{{ session.title || 'New conversation' }}</p>
                    <p class="text-xs text-surface-400 mt-0.5">{{ formatDate(session.updated_at) }}</p>
                </button>
            </div>

            <!-- Tools -->
            <div class="p-2 lg:p-3 border-t border-surface-100 flex lg:block gap-1 lg:space-y-1">
                <button @click="mode = 'quiz'" :class="toolBtnClass('quiz')">
                    <HelpCircle class="w-4 h-4" /> Generate Quiz
                </button>
                <button @click="mode = 'summary'" :class="toolBtnClass('summary')">
                    <FileText class="w-4 h-4" /> Lesson Summary
                </button>
                <button @click="mode = 'chat'" :class="toolBtnClass('chat')">
                    <MessageSquare class="w-4 h-4" /> AI Chat
                </button>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="flex-1 min-w-0 min-h-0 bg-white rounded-2xl border border-surface-200/60 shadow-sm flex flex-col overflow-hidden">

            <!-- ─── CHAT MODE ─── -->
            <template v-if="mode === 'chat'">
                <!-- Chat header -->
                <div class="px-5 py-3 border-b border-surface-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center flex-shrink-0">
                        <Bot class="w-4 h-4 text-white" />
                    </div>
                    <div>
                        <p class="font-semibold text-surface-900 text-sm">{{ activeSession?.title || 'AI Study Tutor' }}</p>
                        <p class="text-xs text-surface-500">AI study support · Check important answers against your course material.</p>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="chatLog" class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div v-if="!activeSession && !messages.length" class="h-full flex flex-col items-center justify-center text-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-primary-100 flex items-center justify-center">
                            <Bot class="w-7 h-7 text-primary-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-surface-800">Your AI Study Companion</p>
                            <p class="text-sm text-surface-400 mt-1">Ask me anything about your courses, get explanations, or request practice questions.</p>
                        </div>
                    </div>

                    <div
                        v-for="(msg, index) in messages"
                        :key="index"
                        :class="['flex gap-3', msg.role === 'user' ? 'justify-end' : 'justify-start']"
                    >
                        <div v-if="msg.role === 'assistant'" class="w-7 h-7 rounded-lg bg-primary-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <Bot class="w-3.5 h-3.5 text-white" />
                        </div>
                        <div :class="[
                            'max-w-full lg:max-w-lg break-words px-4 py-3 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap',
                            msg.role === 'user'
                                ? 'bg-primary-600 text-white rounded-br-sm'
                                : 'bg-surface-100 text-surface-800 rounded-bl-sm',
                        ]">
                            {{ msg.content }}
                        </div>
                    </div>

                    <!-- Streaming indicator -->
                    <div v-if="isThinking" class="flex gap-3">
                        <div class="w-7 h-7 rounded-lg bg-primary-600 flex items-center justify-center flex-shrink-0">
                            <Bot class="w-3.5 h-3.5 text-white" />
                        </div>
                        <div class="bg-surface-100 px-4 py-3 rounded-2xl flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-surface-400 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="w-1.5 h-1.5 bg-surface-400 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="w-1.5 h-1.5 bg-surface-400 rounded-full animate-bounce"></span>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="p-4 border-t border-surface-100">
                    <div class="flex gap-3">
                        <textarea
                            v-model="chatInput"
                            maxlength="7000"
                            aria-label="Message your AI tutor"
                            :disabled="isThinking || loadingSession"
                            placeholder="Ask anything… (Shift+Enter for new line)"
                            rows="2"
                            class="flex-1 rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                            @keydown.enter.exact.prevent="sendMessage"
                        ></textarea>
                        <button
                            @click="sendMessage"
                            :disabled="!chatInput.trim() || isThinking || loadingSession"
                            aria-label="Send message"
                            class="px-4 py-2 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0"
                        >
                            <Send class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </template>

            <!-- ─── QUIZ MODE ─── -->
            <template v-else-if="mode === 'quiz'">
                <div class="px-5 py-3 border-b border-surface-100">
                    <h2 class="font-semibold text-surface-900">Generate Practice Quiz</h2>
                    <p class="text-xs text-surface-400 mt-0.5">AI-generated questions from any lesson</p>
                </div>
                <div class="flex-1 overflow-y-auto p-5 space-y-5">
                    <!-- Config -->
                    <div v-if="!activeQuiz" class="max-w-md space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5" for="quiz-content">Lesson Content</label>
                            <textarea
                                id="quiz-content" v-model="quizInput.content"
                                maxlength="7000"
                                rows="5"
                                placeholder="Paste the lesson text or describe the topic…"
                                class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                            ></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5" for="quiz-count">Questions</label>
                                <input type="number" id="quiz-count" v-model.number="quizInput.num_questions" min="1" max="10" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5" for="quiz-difficulty">Difficulty</label>
                                <select id="quiz-difficulty" v-model="quizInput.difficulty" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>
                        <button @click="generateQuiz" :disabled="!quizInput.content || isGenerating" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50">
                            {{ isGenerating ? 'Generating…' : 'Generate Quiz' }}
                        </button>
                    </div>

                    <!-- Quiz result -->
                    <div v-if="activeQuiz" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-surface-900">Practice Quiz ({{ activeQuiz.questions?.length || 0 }} questions)</h3>
                            <button @click="activeQuiz = null; quizAnswers = {}; quizSubmitted = false" class="text-xs text-primary-600 hover:underline">Generate New</button>
                        </div>

                        <div v-for="(q, qi) in activeQuiz.questions" :key="qi" class="bg-surface-50 rounded-xl p-4 space-y-3">
                            <p class="font-medium text-surface-800 text-sm">{{ qi + 1 }}. {{ q.question }}</p>
                            <div class="space-y-2">
                                <label v-for="(opt, oi) in q.options" :key="oi" :class="[
                                    'flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer text-sm transition-colors',
                                    !quizSubmitted ? 'hover:bg-surface-100' : '',
                                    quizSubmitted && oi === q.correct_index ? 'bg-success-50 text-success-800 font-medium' : '',
                                    quizSubmitted && quizAnswers[qi] === oi && oi !== q.correct_index ? 'bg-danger-50 text-danger-800' : '',
                                ]">
                                    <input type="radio" :name="`q${qi}`" :value="oi" v-model="quizAnswers[qi]" :disabled="quizSubmitted" class="text-primary-600" />
                                    <span>{{ opt }}</span>
                                </label>
                            </div>
                            <p v-if="quizSubmitted && q.explanation" class="text-xs text-surface-500 mt-2 italic">{{ q.explanation }}</p>
                        </div>

                        <button v-if="!quizSubmitted" @click="submitQuiz" class="px-5 py-2.5 bg-success-600 text-white rounded-xl text-sm font-semibold hover:bg-success-700">
                            Submit Answers
                        </button>
                        <div v-if="quizSubmitted" class="p-4 bg-primary-50 rounded-xl text-center">
                            <p class="text-lg font-bold text-primary-700">{{ quizScore }} / {{ activeQuiz.questions.length }}</p>
                            <p class="text-sm text-primary-600 mt-1">{{ quizScore === activeQuiz.questions.length ? 'Perfect score! 🎉' : 'Keep practising!' }}</p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── SUMMARY MODE ─── -->
            <template v-else-if="mode === 'summary'">
                <div class="px-5 py-3 border-b border-surface-100">
                    <h2 class="font-semibold text-surface-900">Lesson Summary</h2>
                    <p class="text-xs text-surface-400 mt-0.5">Generate an AI-powered summary of any lesson</p>
                </div>
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    <div class="max-w-md space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5" for="summary-content">Lesson Text or Topic</label>
                            <textarea
                                id="summary-content" v-model="summaryInput.content"
                                maxlength="7000"
                                rows="5"
                                placeholder="Paste the lesson content or describe the topic…"
                                class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5" for="summary-type">Summary Type</label>
                            <select id="summary-type" v-model="summaryInput.type" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="brief">Brief (3-4 sentences)</option>
                                <option value="detailed">Detailed</option>
                                <option value="key_points">Key Points (bullet list)</option>
                                <option value="study_guide">Study Guide</option>
                            </select>
                        </div>
                        <button @click="generateSummary" :disabled="!summaryInput.content || isGenerating" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50">
                            {{ isGenerating ? 'Generating…' : 'Generate Summary' }}
                        </button>
                    </div>

                    <div v-if="summaryResult" class="bg-surface-50 rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-surface-800">Summary</h3>
                            <button @click="copySummary" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                                <Copy class="w-3.5 h-3.5" /> Copy
                            </button>
                        </div>
                        <p class="text-sm text-surface-700 whitespace-pre-wrap leading-relaxed">{{ summaryResult }}</p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { integrationError } from '@/lib/integration';
import {
    Bot, Plus, Send, FileText, HelpCircle, MessageSquare, Copy,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    sessions: { type: Array, default: () => [] },
});

const mode = ref('chat');
const isThinking = ref(false);
const isGenerating = ref(false);
const error = ref('');
const loadingSession = ref(false);

// ─── Chat ───
const chatInput = ref('');
const messages = ref([]);
const activeSession = ref(null);
const chatLog = ref(null);

function startNewSession() {
    if (isThinking.value || loadingSession.value) return;
    error.value = '';
    activeSession.value = null;
    messages.value = [];
    mode.value = 'chat';
}

async function loadSession(session) {
    if (isThinking.value || loadingSession.value) return;
    loadingSession.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/ai/tutor/sessions/${session.id}`);
        activeSession.value = data.session;
        mode.value = 'chat';
        messages.value = data.session.messages || [];
        scrollToBottom();
    } catch (e) { error.value = integrationError(e); }
    finally { loadingSession.value = false; }
}

function loadSelectedSession(event) {
    const session = props.sessions.find(item => String(item.id) === event.target.value);
    if (session) loadSession(session);
}

async function sendMessage() {
    const content = chatInput.value.trim();
    if (!content || isThinking.value || loadingSession.value) return;
    error.value = '';
    isThinking.value = true;
    scrollToBottom();

    try {
        if (!activeSession.value) {
            const { data } = await axios.post('/ai/tutor/sessions', { context_type: 'general' });
            activeSession.value = data.session;
        }
        const { data } = await axios.post(`/ai/tutor/sessions/${activeSession.value.id}/chat`, { message: content });
        messages.value.push({ role: 'user', content }, { role: 'assistant', content: data.response });
        chatInput.value = '';
        activeSession.value.title = data.title || activeSession.value.title;
        router.reload({ only: ['sessions'] });
    } catch (e) {
        error.value = integrationError(e);
    } finally {
        isThinking.value = false;
        scrollToBottom();
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (chatLog.value) chatLog.value.scrollTop = chatLog.value.scrollHeight;
    });
}

// ─── Quiz ───
const quizInput = ref({ content: '', num_questions: 5, difficulty: 'medium' });
const activeQuiz = ref(null);
const quizAnswers = ref({});
const quizSubmitted = ref(false);

const quizScore = computed(() => {
    if (!activeQuiz.value) return 0;
    return activeQuiz.value.questions.filter((q, i) => quizAnswers.value[i] === q.correct_index).length;
});

async function generateQuiz() {
    if (isGenerating.value) return;
    error.value = '';
    isGenerating.value = true;
    try {
        const { data } = await axios.post('/ai/quiz', quizInput.value);
        activeQuiz.value = data;
        quizAnswers.value = {};
        quizSubmitted.value = false;
    } catch (e) { error.value = integrationError(e); } finally { isGenerating.value = false; }
}

function submitQuiz() { quizSubmitted.value = true; }

// ─── Summary ───
const summaryInput = ref({ content: '', type: 'key_points' });
const summaryResult = ref('');

async function generateSummary() {
    if (isGenerating.value) return;
    error.value = '';
    isGenerating.value = true;
    summaryResult.value = '';
    try {
        const { data } = await axios.post('/ai/lesson-summary', summaryInput.value);
        summaryResult.value = data.summary;
    } catch (e) { error.value = integrationError(e); } finally { isGenerating.value = false; }
}

async function copySummary() { try { await navigator.clipboard.writeText(summaryResult.value); } catch { error.value = 'Copy is unavailable. Select and copy the summary text.'; } }

function toolBtnClass(toolMode) {
    return [
        'w-full flex items-center gap-1 lg:gap-2 px-2 lg:px-3 py-2 rounded-lg text-xs lg:text-sm transition-colors',
        mode.value === toolMode ? 'bg-primary-50 text-primary-700 font-medium' : 'text-surface-600 hover:bg-surface-100',
    ];
}

function formatDate(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}
</script>

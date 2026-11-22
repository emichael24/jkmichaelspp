<template>
    <Head title="AI Content Studio" />

    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-display font-bold text-surface-900">AI Content Studio</h1>
            <p class="text-surface-500 mt-1 text-sm">Create voiceovers, avatar videos, repurpose content, and more.</p>
        </div>

        <!-- Tabs -->
        <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
        <p class="text-sm text-surface-500">Review generated content before publishing. Keep this page open during generation. If a request times out, check recent creations before retrying.</p>
        <div class="flex flex-wrap gap-1 bg-surface-100 p-1 rounded-xl w-fit">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                    'px-4 py-1.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-2',
                    activeTab === tab.id ? 'bg-white shadow-sm text-surface-900' : 'text-surface-500 hover:text-surface-700',
                ]"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
        </div>

        <!-- ─── TTS TAB ─── -->
        <div v-if="activeTab === 'tts'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Text-to-Speech</h2>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5" for="tts-text">Text Content *</label>
                    <textarea
                        id="tts-text" v-model="ttsForm.text"
                        maxlength="10000"
                        rows="6"
                        placeholder="Enter the text you want to convert to speech…"
                        class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                    ></textarea>
                    <p class="text-xs text-surface-400 mt-1">{{ ttsForm.text.length }} characters</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5" for="tts-voice">Voice</label>
                        <select id="tts-voice" v-model="ttsForm.voice_id" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="aaliyah">Aaliyah</option>
                            <option value="angelo">Angelo</option>
                            <option value="helena">Helena</option>
                            <option value="orion">Orion</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5" for="tts-speed">Speed: {{ ttsForm.speed }}x</label>
                        <input type="range" id="tts-speed" v-model="ttsForm.speed" min="0.5" max="2" step="0.25" class="w-full accent-primary-600 mt-2" />
                    </div>
                </div>

                <button @click="generateTTS" :disabled="!ttsForm.text || ttsLoading" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                    <Loader2 v-if="ttsLoading" class="w-4 h-4 animate-spin" />
                    <Mic v-else class="w-4 h-4" />
                    {{ ttsLoading ? 'Generating…' : 'Generate Audio' }}
                </button>
            </div>

            <!-- TTS Result -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h3 class="font-semibold text-surface-800 mb-4">Output</h3>
                <div v-if="ttsResult" class="space-y-3">
                    <audio :src="ttsResult.audio_url" controls class="w-full rounded-lg" />
                    <a :href="ttsResult.audio_url" download class="flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium">
                        <Download class="w-4 h-4" /> Download Audio
                    </a>
                </div>
                <div v-else class="flex flex-col items-center justify-center h-32 text-surface-300 gap-3">
                    <Volume2 class="w-10 h-10" />
                    <p class="text-sm text-surface-400">Audio will appear here</p>
                </div>
            </div>
        </div>

        <!-- ─── AVATAR VIDEO TAB ─── -->
        <div v-else-if="activeTab === 'avatar'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Avatar Video</h2>
                <p class="text-sm text-surface-500">Animate a photo with your voice or audio to create a speaking avatar.</p>

                <div>
                    <label for="avatar-photo" class="block text-sm font-medium text-surface-700 mb-1.5">Portrait photo · JPG, PNG or WebP, up to 5 MB</label>
                    <Input v-model="avatarForm.photo_url" aria-label="Generated portrait media URL" placeholder="Generated media URL, or upload below" />
                    <input id="avatar-photo" type="file" accept="image/jpeg,image/png,image/webp" @change="handleAvatarImageUpload" class="mt-2 block w-full text-sm text-surface-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-surface-100 file:text-surface-700 hover:file:bg-surface-200" />
                </div>

                <div>
                    <label for="avatar-audio" class="block text-sm font-medium text-surface-700 mb-1.5">Audio · MP3, WAV, M4A, OGG, FLAC or WebM, up to 10 MB</label>
                    <Input v-model="avatarForm.audio_url" aria-label="Generated avatar audio URL" placeholder="Generated audio URL, or upload below" />
                    <input id="avatar-audio" type="file" accept="audio/*" @change="handleAvatarAudioUpload" class="mt-2 block w-full text-sm text-surface-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-surface-100 file:text-surface-700 hover:file:bg-surface-200" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5" for="avatar-engine">Engine</label>
                    <select id="avatar-engine" v-model="avatarForm.engine" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="live-avatar">Live Avatar · Standard</option>
                        <option value="stable-avatar">Stable Avatar · Greater consistency</option>
                        <option value="ai-avatar">AI Avatar · Higher fidelity</option>
                    </select>
                </div>

                <p class="text-xs text-surface-500">Upload files from other sources. URL fields accept media previously generated by this AI service.</p>
                <div><label for="avatar-prompt" class="block text-sm font-medium text-surface-700 mb-1.5">Presenter direction</label><textarea id="avatar-prompt" v-model="avatarForm.prompt" maxlength="2000" rows="3" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm" placeholder="Describe the presenter's expression and movement."></textarea></div>
                <button @click="generateAvatar" :disabled="(!avatarForm.photo_url && !avatarImageFile) || (!avatarForm.audio_url && !avatarAudioFile) || avatarLoading" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                    <Loader2 v-if="avatarLoading" class="w-4 h-4 animate-spin" />
                    <Video v-else class="w-4 h-4" />
                    {{ avatarLoading ? 'Generating video…' : 'Generate Avatar Video' }}
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h3 class="font-semibold text-surface-800 mb-4">Video preview</h3>
                <video v-if="avatarResult" :src="avatarResult" controls class="w-full rounded-xl" />
                <p v-else class="text-sm text-surface-500">Your finished video will appear here.</p>
            </div>
        </div>

        <!-- ─── REPURPOSE TAB ─── -->
        <div v-else-if="activeTab === 'repurpose'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Repurpose Lesson Content</h2>
                <p class="text-sm text-surface-500">Convert your lesson into different content formats automatically.</p>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5" for="repurpose-content">Original Content *</label>
                    <textarea
                        id="repurpose-content" v-model="repurposeForm.content"
                        maxlength="7000"
                        rows="7"
                        placeholder="Paste your lesson text, transcript, or notes…"
                        class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                    ></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5" for="repurpose-format">Target Format</label>
                        <select id="repurpose-format" v-model="repurposeForm.target_format" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="video_script">Video Script</option>
                            <option value="blog_post">Blog Post</option>
                            <option value="social_linkedin">LinkedIn Post</option>
                            <option value="social_twitter">Twitter Thread</option>
                            <option value="email_lesson">Email Lesson</option>
                            <option value="infographic_outline">Infographic Outline</option>
                            <option value="podcast_script">Podcast Script</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1.5" for="repurpose-tone">Tone</label>
                        <select id="repurpose-tone" v-model="repurposeForm.tone" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="professional">Professional</option>
                            <option value="casual">Conversational</option>
                            <option value="academic">Academic</option>
                            <option value="encouraging">Encouraging</option>
                        </select>
                    </div>
                </div>

                <button @click="repurposeContent" :disabled="!repurposeForm.content || repurposeLoading" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                    <Loader2 v-if="repurposeLoading" class="w-4 h-4 animate-spin" />
                    <Wand2 v-else class="w-4 h-4" />
                    {{ repurposeLoading ? 'Generating…' : 'Repurpose Content' }}
                </button>
            </div>

            <!-- Output -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-surface-800">Output</h3>
                    <button v-if="repurposeResult" @click="copyRepurpose" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                        <Copy class="w-3.5 h-3.5" /> Copy
                    </button>
                </div>
                <div v-if="repurposeResult" class="text-sm text-surface-700 whitespace-pre-wrap leading-relaxed bg-surface-50 rounded-xl p-4 max-h-96 overflow-y-auto">{{ repurposeResult }}</div>
                <div v-else class="flex flex-col items-center justify-center h-40 text-surface-300 gap-2">
                    <Wand2 class="w-8 h-8" />
                    <p class="text-sm text-surface-400">Repurposed content will appear here</p>
                </div>
            </div>
        </div>

        <!-- ─── TRANSCRIBE TAB ─── -->
        <div v-else-if="activeTab === 'transcribe'" class="max-w-2xl space-y-5">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-5">
                <h2 class="font-semibold text-surface-800">Transcribe Audio</h2>
                <p class="text-sm text-surface-500">Upload audio up to 10 MB, or reuse an audio URL generated by this AI service.</p>

                <div>
                    <label for="transcribe-url" class="block text-sm font-medium text-surface-700 mb-1.5">Generated audio URL</label>
                    <Input id="transcribe-url" v-model="transcribeForm.audio_url" placeholder="https://…" />
                </div>
                <p class="text-xs text-surface-400 text-center">— or —</p>
                <div>
                    <label for="transcribe-audio" class="block text-sm font-medium text-surface-700 mb-1.5">Upload Audio File</label>
                    <input id="transcribe-audio" type="file" accept="audio/*" @change="handleTranscribeUpload" class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" />
                </div>

                <button @click="transcribeAudio" :disabled="(!transcribeForm.audio_url && !transcribeFile) || transcribeLoading" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                    <Loader2 v-if="transcribeLoading" class="w-4 h-4 animate-spin" />
                    <FileAudio v-else class="w-4 h-4" />
                    {{ transcribeLoading ? 'Transcribing…' : 'Transcribe' }}
                </button>
            </div>

            <div v-if="transcribeResult" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-surface-800">Transcript</h3>
                    <button @click="copyTranscript" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                        <Copy class="w-3.5 h-3.5" /> Copy
                    </button>
                </div>
                <p class="text-sm text-surface-700 whitespace-pre-wrap leading-relaxed">{{ transcribeResult }}</p>
            </div>
        </div>
        <!-- History is available from every tool. -->
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6">
                <h3 class="font-semibold text-surface-800 mb-4">Recent creations</h3>
                <div v-if="recentJobs.length" class="space-y-3">
                    <div v-for="job in recentJobs" :key="job.id" :class="['p-3 rounded-xl border', jobBorderClass(job.status)]">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-surface-800">{{ { tts: 'Voiceover', avatar_video: 'Avatar video', content_repurpose: 'Repurposed content', transcription: 'Transcript' }[job.job_type] || job.job_type }}</span>
                            <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', jobBadgeClass(job.status)]">{{ job.status }}</span>
                        </div>
                        <p class="text-xs text-surface-400 mt-1">{{ formatDate(job.created_at) }}</p>
                        <div v-if="job.status === 'completed' && job.output_file_path" class="mt-2">
                            <a :href="job.output_file_path" target="_blank" rel="noopener noreferrer" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                                <ExternalLink class="w-3 h-3" /> View Result
                            </a>
                        </div>
                        <details v-if="job.status === 'completed' && job.output && !job.output_file_path" class="mt-2 text-sm"><summary class="cursor-pointer text-primary-700">Read result</summary><p class="mt-2 whitespace-pre-wrap">{{ job.output }}</p></details>
                        <div v-if="job.status === 'failed' && job.error_message" class="mt-1 text-xs text-danger-600">{{ job.error_message }}</div>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center h-32 text-surface-300 gap-2">
                    <Film class="w-8 h-8" />
                    <p class="text-sm text-surface-400">No jobs yet</p>
                </div>
            </div>

    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import FacilitatorLayout from '@/Layouts/FacilitatorLayout.vue';
import Input from '@/Components/UI/Input.vue';
import axios from 'axios';
import { integrationError } from '@/lib/integration';
import {
    Mic, Video, Wand2, FileAudio, Loader2, Download, Volume2,
    Film, ExternalLink, Copy,
} from 'lucide-vue-next';

defineOptions({ layout: FacilitatorLayout });

const props = defineProps({
    recentJobs: { type: Array, default: () => [] },
});

const tabs = [
    { id: 'tts', label: 'Text-to-Speech', icon: Mic },
    { id: 'avatar', label: 'Avatar Video', icon: Video },
    { id: 'repurpose', label: 'Repurpose', icon: Wand2 },
    { id: 'transcribe', label: 'Transcribe', icon: FileAudio },
];
const activeTab = ref('tts');
const error = ref('');
function refreshJobs() { router.reload({ only: ['recentJobs'] }); }

// ─── TTS ───
const ttsForm = ref({ text: '', voice_id: 'aaliyah', speed: 1 });
const ttsLoading = ref(false);
const ttsResult = ref(null);

async function generateTTS() {
    if (ttsLoading.value) return;
    error.value = '';
    ttsLoading.value = true;
    try {
        const { data } = await axios.post('/facilitator/ai-studio/tts', ttsForm.value);
        ttsResult.value = data;
    } catch (e) { error.value = integrationError(e); } finally { ttsLoading.value = false; refreshJobs(); }
}

// ─── Avatar ───
const avatarForm = ref({ photo_url: '', audio_url: '', engine: 'live-avatar', render_tier: 'standard', prompt: 'A professional instructor presenting naturally to camera, with subtle movement and a clear, welcoming expression.' });
const avatarImageFile = ref(null);
const avatarAudioFile = ref(null);
const avatarResult = ref('');
const avatarLoading = ref(false);

function handleAvatarImageUpload(e) {
    avatarImageFile.value = e.target.files[0];
    if (avatarImageFile.value) avatarForm.value.photo_url = '';
}
function handleAvatarAudioUpload(e) {
    avatarForm.value.audio_url = '';
    avatarAudioFile.value = e.target.files[0];
}

async function generateAvatar() {
    if (avatarLoading.value) return;
    error.value = '';
    avatarLoading.value = true;
    const fd = new FormData();
    Object.entries(avatarForm.value).forEach(([k, v]) => { if (v) fd.append(k, v); });
    if (avatarImageFile.value) { fd.delete('photo_url'); fd.append('photo', avatarImageFile.value); }
    if (avatarAudioFile.value) { fd.delete('audio_url'); fd.append('audio', avatarAudioFile.value); }
    try {
        const { data } = await axios.post('/facilitator/ai-studio/avatar', fd);
        avatarResult.value = data.result.video_url;
    } catch (e) { error.value = integrationError(e); } finally { avatarLoading.value = false; refreshJobs(); }
}

// ─── Repurpose ───
const repurposeForm = ref({ content: '', target_format: 'video_script', tone: 'professional' });
const repurposeLoading = ref(false);
const repurposeResult = ref('');

async function repurposeContent() {
    if (repurposeLoading.value) return;
    error.value = '';
    repurposeLoading.value = true;
    repurposeResult.value = '';
    try {
        const { data } = await axios.post('/facilitator/ai-studio/repurpose', repurposeForm.value);
        repurposeResult.value = data.result;
    } catch (e) { error.value = integrationError(e); } finally { repurposeLoading.value = false; refreshJobs(); }
}

async function copyRepurpose() { await copyText(repurposeResult.value); }

// ─── Transcribe ───
const transcribeForm = ref({ audio_url: '' });
const transcribeFile = ref(null);
const transcribeLoading = ref(false);
const transcribeResult = ref('');

function handleTranscribeUpload(e) { transcribeFile.value = e.target.files[0]; if (transcribeFile.value) transcribeForm.value.audio_url = ''; }

async function transcribeAudio() {
    if (transcribeLoading.value) return;
    error.value = '';
    transcribeLoading.value = true;
    const fd = new FormData();
    if (transcribeFile.value) fd.append('audio', transcribeFile.value);
    else if (transcribeForm.value.audio_url) fd.append('audio_url', transcribeForm.value.audio_url);
    try {
        const { data } = await axios.post('/facilitator/ai-studio/transcribe', fd);
        transcribeResult.value = data.text;
    } catch (e) { error.value = integrationError(e); } finally { transcribeLoading.value = false; refreshJobs(); }
}

async function copyTranscript() { await copyText(transcribeResult.value); }
async function copyText(text) { try { await navigator.clipboard.writeText(text); } catch { error.value = 'Copy is unavailable. Select and copy the result text.'; } }

function jobBorderClass(status) {
    return { completed: 'border-success-200', failed: 'border-danger-200', processing: 'border-amber-200' }[status] || 'border-surface-200';
}
function jobBadgeClass(status) {
    return { completed: 'bg-success-100 text-success-700', failed: 'bg-danger-100 text-danger-700', processing: 'bg-amber-100 text-amber-700', pending: 'bg-surface-100 text-surface-600' }[status] || 'bg-surface-100 text-surface-600';
}
function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="AI Platform Insights" />

    <div class="space-y-6">
        <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">AI Platform Insights</h1>
                <p class="text-surface-500 mt-1 text-sm">Generate AI-powered analytics and strategic insights for your platform.</p>
            </div>
            <button @click="generateInsights" :disabled="isGenerating" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-colors disabled:opacity-60 flex items-center gap-2">
                <Loader2 v-if="isGenerating" class="w-4 h-4 animate-spin" />
                <Sparkles v-else class="w-4 h-4" />
                {{ isGenerating ? 'Analysing…' : 'Generate Insights' }}
            </button>
        </div>

        <!-- Insight Type Selector -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="type in insightTypes"
                :key="type.value"
                @click="selectedType = type.value"
                :class="[
                    'px-3 py-1.5 rounded-xl text-sm font-medium border transition-colors',
                    selectedType === type.value
                        ? 'bg-primary-600 text-white border-primary-600'
                        : 'bg-white text-surface-600 border-surface-200 hover:border-primary-400 hover:text-primary-600',
                ]"
            >{{ type.label }}</button>
        </div>

        <!-- Content Generation Section -->
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-surface-800 flex items-center gap-2">
                <PenSquare class="w-5 h-5 text-primary-600" /> AI Content Generator
            </h2>
            <p class="text-sm text-surface-500">Generate platform content instantly: course descriptions, announcements, welcome messages, and emails.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5" for="admin-content-type">Content Type</label>
                    <select id="admin-content-type" v-model="contentForm.type" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="course_description">Course Description</option>
                        <option value="announcement">Announcement</option>
                        <option value="email_subject">Email Subject Lines</option>
                        <option value="welcome_message">Welcome Message</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5" for="admin-content-tone">Tone</label>
                    <select id="admin-content-tone" v-model="contentForm.tone" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="professional">Professional</option>
                        <option value="friendly">Friendly</option>
                        <option value="motivational">Motivational</option>
                        <option value="formal">Formal</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1.5" for="admin-content-context">Context / Topic</label>
                <textarea
                    id="admin-content-context" v-model="contentForm.context"
                    maxlength="2000"
                    rows="3"
                    placeholder="Describe what you need content for…"
                    class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                ></textarea>
            </div>
            <button @click="generateContent" :disabled="!contentForm.context || contentLoading" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                <Loader2 v-if="contentLoading" class="w-4 h-4 animate-spin" />
                <PenSquare v-else class="w-4 h-4" />
                {{ contentLoading ? 'Generating…' : 'Generate Content' }}
            </button>

            <div v-if="contentResult" class="bg-surface-50 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-medium text-surface-500 uppercase tracking-wide">Generated Content</p>
                    <button @click="copyContent" class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                        <Copy class="w-3 h-3" /> Copy
                    </button>
                </div>
                <p class="text-sm text-surface-800 whitespace-pre-wrap leading-relaxed">{{ contentResult }}</p>
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="isGenerating" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
            <div class="flex items-center gap-3">
                <Sparkles class="w-5 h-5 text-primary-600 animate-pulse" />
                <p class="font-semibold text-surface-800">Analysing platform data…</p>
            </div>
            <div class="space-y-3">
                <div class="h-4 bg-surface-100 rounded animate-pulse w-3/4"></div>
                <div class="h-4 bg-surface-100 rounded animate-pulse"></div>
                <div class="h-4 bg-surface-100 rounded animate-pulse w-5/6"></div>
                <div class="h-4 bg-surface-100 rounded animate-pulse w-2/3"></div>
            </div>
        </div>

        <!-- Latest Insight -->
        <div v-if="latestInsight && !isGenerating" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-surface-900 flex items-center gap-2">
                        <Sparkles class="w-5 h-5 text-primary-600" /> Platform Insights
                    </h2>
                    <p class="text-xs text-surface-400 mt-0.5">Generated {{ formatDate(latestInsight.created_at) }}</p>
                </div>
                <button @click="copyInsight" class="text-xs text-primary-600 hover:underline flex items-center gap-1 flex-shrink-0">
                    <Copy class="w-3.5 h-3.5" /> Copy
                </button>
            </div>

            <div class="bg-gradient-to-br from-primary-50 to-surface-50 rounded-xl p-5">
                <p class="text-sm text-surface-800 whitespace-pre-wrap leading-relaxed">{{ latestInsight.insights }}</p>
            </div>
        </div>

        <!-- Insight History -->
        <div v-if="pastInsights.length" class="bg-white rounded-2xl border border-surface-200/60 shadow-sm p-5">
            <h3 class="font-semibold text-surface-800 mb-4">Insight History</h3>
            <div class="space-y-2">
                <button
                    v-for="(insight, i) in pastInsights"
                    :key="i"
                    @click="latestInsight = { ...insight, insights: insight.output }"
                    class="w-full text-left flex items-center justify-between px-4 py-3 rounded-xl hover:bg-surface-50 cursor-pointer border border-surface-100 transition-colors"
                >
                    <div>
                        <p class="text-sm font-medium text-surface-800">{{ insight.type_label || 'Platform Overview' }}</p>
                        <p class="text-xs text-surface-400 mt-0.5">{{ formatDate(insight.created_at) }}</p>
                    </div>
                    <ChevronRight class="w-4 h-4 text-surface-400" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';
import { integrationError } from '@/lib/integration';
import { Sparkles, PenSquare, Loader2, Copy, ChevronRight } from 'lucide-vue-next';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    recentInsights: { type: Array, default: () => [] },
});

const isGenerating = ref(false);
const pastInsights = computed(() => props.recentInsights.filter(item => item.status === 'completed'));
const latestInsight = ref(pastInsights.value[0] ? { ...pastInsights.value[0], insights: pastInsights.value[0].output } : null);
const error = ref('');
const selectedType = ref('overview');

const insightTypes = [
    { value: 'overview', label: 'Platform Overview' },
    { value: 'engagement', label: 'Engagement' },
    { value: 'learning_outcomes', label: 'Learning Outcomes' },
    { value: 'revenue', label: 'Revenue' },
    { value: 'retention', label: 'Retention' },
];

async function generateInsights() {
    if (isGenerating.value) return;
    error.value = '';
    isGenerating.value = true;
    try {
        const { data } = await axios.post('/admin/erp/ai/platform-insights', { type: selectedType.value });
        latestInsight.value = { insights: data.insights, created_at: new Date().toISOString(), type_label: insightTypes.find(t => t.value === selectedType.value)?.label };
        router.reload({ only: ['recentInsights'] });
    } catch (e) { error.value = integrationError(e); } finally { isGenerating.value = false; }
}

async function copyInsight() {
    if (latestInsight.value) await copyText(latestInsight.value.insights);
}

// Content Generation
const contentForm = ref({ type: 'course_description', tone: 'professional', context: '' });
const contentLoading = ref(false);
const contentResult = ref('');

async function generateContent() {
    if (contentLoading.value) return;
    error.value = '';
    contentLoading.value = true;
    contentResult.value = '';
    try {
        const { data } = await axios.post('/admin/erp/ai/generate-content', contentForm.value);
        contentResult.value = data.content;
    } catch (e) { error.value = integrationError(e); } finally { contentLoading.value = false; }
}

async function copyContent() { await copyText(contentResult.value); }
async function copyText(text) { try { await navigator.clipboard.writeText(text); } catch { error.value = 'Copy is unavailable. Select and copy the generated text.'; } }

function formatDate(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

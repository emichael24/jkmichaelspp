<template>
    <Head title="SME Dashboard" />

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-violet-600 via-purple-700 to-indigo-800 p-6 sm:p-8">
            <div class="absolute top-0 right-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -translate-y-1/3 translate-x-1/3" />
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-display font-bold text-white mb-1">
                        SME Governance Console
                    </h1>
                    <p class="text-purple-200 text-sm sm:text-base">
                        Review content quality, manage the question bank, and ensure assessment integrity.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="stats.pendingReview > 0" class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center">
                        <p class="text-2xl font-bold text-white leading-none">{{ stats.pendingReview }}</p>
                        <p class="text-white/60 text-[10px] font-medium">Reviews Pending</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10 text-center">
                        <p class="text-2xl font-bold text-white leading-none">{{ stats.reviewedThisMonth }}</p>
                        <p class="text-white/60 text-[10px] font-medium">Reviewed This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
                label="Total Questions"
                :value="stats.totalQuestions"
                :icon="Database"
                color="primary"
            />
            <StatCard
                label="Published"
                :value="stats.publishedQuestions"
                :icon="CheckCircle"
                color="success"
            />
            <StatCard
                label="In Review"
                :value="stats.inReviewQuestions"
                :icon="Clock"
                :color="stats.inReviewQuestions > 0 ? 'warning' : 'default'"
            />
            <StatCard
                label="Avg Accuracy"
                :value="`${stats.avgAccuracy}%`"
                :icon="Target"
                :color="stats.avgAccuracy >= 70 ? 'success' : stats.avgAccuracy >= 50 ? 'warning' : 'danger'"
            />
        </div>

        <!-- Main grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left 2/3 -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Content Pending Review -->
                <Card title="Content Pending Review" noPadding>
                    <template #header-actions>
                        <Link href="/sme/pending" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            View all
                        </Link>
                    </template>

                    <div v-if="pendingContent.length" class="divide-y divide-surface-100">
                        <Link
                            v-for="item in pendingContent"
                            :key="item.id"
                            :href="`/sme/review/${item.id}`"
                            class="flex items-center gap-4 px-6 py-4 hover:bg-surface-50 transition-colors group"
                        >
                            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0', typeClass(item.type)]">
                                <component :is="typeIcon(item.type)" class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-surface-900 group-hover:text-primary-600 truncate">
                                    {{ item.title }}
                                </p>
                                <p class="text-xs text-surface-500 mt-0.5">
                                    {{ item.type }} · by {{ item.submitter }} · {{ item.submitted }}
                                </p>
                            </div>
                            <Badge variant="warning" size="sm">Review</Badge>
                        </Link>
                    </div>
                    <div v-else class="p-6">
                        <EmptyState title="All caught up!" message="No content pending review." :icon="CheckCircle" />
                    </div>
                </Card>

                <!-- Problem Items (low quality questions) -->
                <Card title="Items Needing Attention">
                    <template #header-actions>
                        <Link href="/sme/questions/analytics-dashboard" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            Full analytics
                        </Link>
                    </template>

                    <div v-if="problemItems.length" class="space-y-3">
                        <Link
                            v-for="item in problemItems"
                            :key="item.id"
                            :href="`/sme/questions/${item.id}/analytics`"
                            class="block p-4 rounded-xl bg-surface-50 hover:bg-surface-100 transition-all group"
                        >
                            <p class="text-sm font-medium text-surface-900 group-hover:text-primary-600 line-clamp-2">
                                {{ item.stem }}
                            </p>
                            <div class="flex items-center gap-4 mt-2">
                                <Badge :variant="item.difficulty === 'hard' ? 'danger' : item.difficulty === 'medium' ? 'warning' : 'success'" size="sm">
                                    {{ item.difficulty }}
                                </Badge>
                                <span class="text-xs text-surface-500">
                                    Disc: <span :class="item.discrimination_index < 0.15 ? 'text-danger-600 font-semibold' : 'text-surface-700'">
                                        {{ item.discrimination_index?.toFixed(2) ?? '—' }}
                                    </span>
                                </span>
                                <span class="text-xs text-surface-500">
                                    Correct: <span :class="(item.correct_rate < 20 || item.correct_rate > 95) ? 'text-danger-600 font-semibold' : 'text-surface-700'">
                                        {{ item.correct_rate ?? 0 }}%
                                    </span>
                                </span>
                            </div>
                        </Link>
                    </div>
                    <EmptyState v-else title="All items healthy" message="No questions flagged for quality issues." :icon="ThumbsUp" />
                </Card>

                <!-- Difficulty Distribution -->
                <Card title="Question Difficulty Distribution">
                    <div class="space-y-3">
                        <div v-for="(count, diff) in difficultyDist" :key="diff" class="flex items-center gap-4">
                            <Badge :variant="diff === 'hard' ? 'danger' : diff === 'medium' ? 'warning' : 'success'" size="sm" class="w-20 justify-center capitalize">
                                {{ diff }}
                            </Badge>
                            <div class="flex-1 bg-surface-100 rounded-full h-3">
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all',
                                        diff === 'hard' ? 'bg-danger-400' : diff === 'medium' ? 'bg-warning-400' : 'bg-success-400'
                                    ]"
                                    :style="{ width: `${(count / totalQuestionCount) * 100}%` }"
                                />
                            </div>
                            <span class="text-sm font-medium text-surface-700 w-12 text-right">{{ count }}</span>
                        </div>
                    </div>
                    <p v-if="!Object.keys(difficultyDist).length" class="text-sm text-surface-400 text-center py-4">
                        No published questions yet.
                    </p>
                </Card>
            </div>

            <!-- Right sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <Card title="Quick Actions">
                    <div class="space-y-3">
                        <Link href="/sme/pending"
                              class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-purple-50 hover:text-purple-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                <Eye class="w-4 h-4 text-purple-600" />
                            </div>
                            <span class="text-sm font-medium flex-1">Review Queue</span>
                            <Badge v-if="stats.pendingReview > 0" variant="warning" size="sm">{{ stats.pendingReview }}</Badge>
                        </Link>
                        <Link href="/sme/questions"
                              class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-blue-50 hover:text-blue-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <Database class="w-4 h-4 text-blue-600" />
                            </div>
                            <span class="text-sm font-medium flex-1">Question Bank</span>
                            <span class="text-xs text-surface-400">{{ stats.totalQuestions }}</span>
                        </Link>
                        <Link href="/sme/flags"
                              class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-amber-50 hover:text-amber-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                <Flag class="w-4 h-4 text-amber-600" />
                            </div>
                            <span class="text-sm font-medium flex-1">Flag Queue</span>
                            <Badge v-if="stats.openFlags > 0" variant="danger" size="sm">{{ stats.openFlags }}</Badge>
                        </Link>
                        <Link href="/sme/questions/analytics-dashboard"
                              class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-emerald-50 hover:text-emerald-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <BarChart3 class="w-4 h-4 text-emerald-600" />
                            </div>
                            <span class="text-sm font-medium">Item Analytics</span>
                        </Link>
                        <Link href="/sme/questions/blueprint-coverage"
                              class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-indigo-50 hover:text-indigo-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                <LayoutGrid class="w-4 h-4 text-indigo-600" />
                            </div>
                            <span class="text-sm font-medium">Blueprint Coverage</span>
                        </Link>
                    </div>
                </Card>

                <!-- Recent Flags -->
                <Card title="Recent Flags">
                    <template #header-actions>
                        <Link href="/sme/flags" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all</Link>
                    </template>

                    <div v-if="recentFlags.length" class="space-y-3">
                        <div v-for="flag in recentFlags" :key="flag.id" class="p-3 rounded-xl bg-surface-50">
                            <p class="text-sm font-medium text-surface-900 line-clamp-2">
                                {{ flag.question?.stem || 'Unnamed question' }}
                            </p>
                            <div class="flex items-center gap-2 mt-2">
                                <Badge variant="danger" size="sm">{{ flag.reason }}</Badge>
                                <span class="text-xs text-surface-400">
                                    {{ flag.reporter?.first_name }} {{ flag.reporter?.last_name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else title="No open flags" message="No flagged questions." :icon="Flag" />
                </Card>

                <!-- Weekly Activity -->
                <Card title="My Review Activity">
                    <p class="text-xs text-surface-500 mb-3">This week</p>
                    <div class="grid grid-cols-7 gap-1">
                        <div
                            v-for="day in weekDays"
                            :key="day.label"
                            class="flex flex-col items-center"
                        >
                            <div
                                :class="[
                                    'w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold',
                                    day.count > 0 ? 'bg-purple-100 text-purple-700' : 'bg-surface-100 text-surface-400',
                                ]"
                            >
                                {{ day.count }}
                            </div>
                            <span class="text-[10px] text-surface-400 mt-1">{{ day.label }}</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-surface-100 text-center">
                        <p class="text-xs text-surface-500">
                            {{ totalWeeklyReviews }} reviews this week
                        </p>
                    </div>
                </Card>

                <!-- Question Bank Summary -->
                <Card title="Question Bank Status">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-600">Published</span>
                            <span class="text-sm font-semibold text-success-600">{{ stats.publishedQuestions }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-600">Draft</span>
                            <span class="text-sm font-semibold text-surface-700">{{ stats.draftQuestions }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-600">In Review</span>
                            <span class="text-sm font-semibold text-warning-600">{{ stats.inReviewQuestions }}</span>
                        </div>
                        <div class="pt-2 border-t border-surface-100 flex items-center justify-between">
                            <span class="text-sm font-medium text-surface-700">Total</span>
                            <span class="text-sm font-bold text-surface-900">{{ stats.totalQuestions }}</span>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SmeLayout from '@/Layouts/SmeLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import {
    Database, CheckCircle, Clock, Target, Eye, Flag, BarChart3, LayoutGrid,
    BookOpen, FileText, HelpCircle, ThumbsUp,
} from 'lucide-vue-next';

defineOptions({ layout: SmeLayout });

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    difficultyDist: { type: Object, default: () => ({}) },
    recentFlags: { type: Array, default: () => [] },
    problemItems: { type: Array, default: () => [] },
    pendingContent: { type: Array, default: () => [] },
    weeklyActivity: { type: Object, default: () => ({}) },
});

const totalQuestionCount = computed(() => {
    return Object.values(props.difficultyDist).reduce((a, b) => a + b, 0) || 1;
});

const typeIcon = (type) => {
    const map = { Course: BookOpen, Lesson: FileText, Question: HelpCircle };
    return map[type] || FileText;
};
const typeClass = (type) => {
    const map = {
        Course: 'bg-blue-100 text-blue-600',
        Lesson: 'bg-emerald-100 text-emerald-600',
        Question: 'bg-purple-100 text-purple-600',
    };
    return map[type] || 'bg-surface-100 text-surface-600';
};

// Build weekly activity grid
const weekDays = computed(() => {
    const labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const startOfWeek = new Date();
    startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1);

    return labels.map((label, i) => {
        const date = new Date(startOfWeek);
        date.setDate(date.getDate() + i);
        const key = date.toISOString().split('T')[0];
        return { label, count: props.weeklyActivity[key] || 0 };
    });
});

const totalWeeklyReviews = computed(() => {
    return weekDays.value.reduce((sum, d) => sum + d.count, 0);
});
</script>

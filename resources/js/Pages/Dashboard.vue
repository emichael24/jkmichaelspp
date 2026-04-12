<template>
    <Head title="Dashboard" />

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-700 to-primary-800 p-6 sm:p-8">
            <!-- Decorative -->
            <div class="absolute top-0 right-0 w-72 h-72 bg-accent-400/10 rounded-full blur-3xl -translate-y-1/3 translate-x-1/3" />
            <div class="absolute bottom-0 left-1/2 w-48 h-48 bg-xp-500/10 rounded-full blur-3xl" />

            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-display font-bold text-white mb-1">
                        Good {{ greeting }}, {{ user.first_name || 'Learner' }} 👋
                    </h1>
                    <p class="text-primary-200 text-sm sm:text-base">
                        {{ motivationalMessage }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Streak -->
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10">
                        <Flame class="w-5 h-5 text-warning-400" />
                        <div>
                            <p class="text-white font-bold text-lg leading-none">{{ user.streak || 0 }}</p>
                            <p class="text-white/60 text-[10px] font-medium">day streak</p>
                        </div>
                    </div>
                    <!-- Readiness score -->
                    <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10">
                        <CircularProgress :percentage="readinessScore" :size="42" :stroke-width="4" color="accent">
                            <span class="text-xs font-bold text-white">{{ readinessScore }}%</span>
                        </CircularProgress>
                        <div>
                            <p class="text-white/60 text-[10px] font-medium">Readiness</p>
                            <p class="text-white font-semibold text-sm">{{ readinessLabel }}</p>
                        </div>
                    </div>
                    <!-- Exam countdown -->
                    <div v-if="examCountdown && !examCountdown.is_past" :class="[
                        'flex items-center gap-2 backdrop-blur-sm rounded-xl px-4 py-2.5 border border-white/10',
                        examCountdown.urgency === 'critical' ? 'bg-danger-500/20' : examCountdown.urgency === 'high' ? 'bg-warning-500/20' : 'bg-white/10'
                    ]">
                        <Calendar :class="['w-5 h-5', examCountdown.urgency === 'critical' ? 'text-danger-300' : 'text-white/80']" />
                        <div>
                            <p :class="['font-bold text-lg leading-none', examCountdown.urgency === 'critical' ? 'text-danger-200' : 'text-white']">{{ examCountdown.days_left }}</p>
                            <p class="text-white/60 text-[10px] font-medium">days to exam</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pass likelihood -->
            <div v-if="stats.passLikelihood" class="relative z-10 mt-3 flex items-center gap-2">
                <span :class="['inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full', passLikelihoodStyle.bg]">
                    <component :is="passLikelihoodStyle.icon" class="w-3.5 h-3.5" />
                    {{ stats.passLikelihood }}% pass likelihood
                </span>
                <span v-if="stats.confidenceBand" class="text-white/50 text-xs">({{ stats.confidenceBand }})</span>
            </div>

            <!-- XP bar -->
            <div class="relative z-10 mt-4 bg-white/10 rounded-full p-1">
                <div class="flex items-center gap-3">
                    <span class="text-white/80 text-xs font-medium pl-2 whitespace-nowrap">
                        Lv {{ user.level }} {{ user.level_title }}
                    </span>
                    <div class="flex-1 bg-white/10 rounded-full h-2">
                        <div
                            class="bg-gradient-to-r from-xp-400 to-xp-500 h-full rounded-full transition-all duration-500"
                            :style="{ width: `${user.xp_progress || 0}%` }"
                        />
                    </div>
                    <span class="text-white/60 text-xs pr-2 whitespace-nowrap">
                        {{ user.xp?.toLocaleString() }} / {{ user.next_level_xp?.toLocaleString() }} XP
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
                label="Streak"
                :value="`${stats.streak || 0} days`"
                :icon="Flame"
                color="warning"
            />
            <StatCard
                label="XP This Week"
                :value="stats.weeklyXp || 0"
                :icon="Zap"
                color="xp"
            />
            <StatCard
                label="Courses Active"
                :value="stats.activeCourses || 0"
                :icon="BookOpen"
                color="primary"
            />
            <StatCard
                label="Days Active"
                :value="`${stats.daysActive || 0}/7`"
                :icon="CalendarCheck"
                color="success"
            />
        </div>

        <!-- Main grid: Continue learning + Sidebar -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Continue learning (left 2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Coach insight -->
                <div v-if="coachInsight" class="flex items-start gap-3 p-4 rounded-xl bg-accent-50 border border-accent-200">
                    <div class="w-8 h-8 rounded-lg bg-accent-100 flex items-center justify-center shrink-0 mt-0.5">
                        <Lightbulb class="w-4 h-4 text-accent-600" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-accent-800">Coach Insight</p>
                        <p class="text-sm text-accent-700 mt-0.5">{{ coachInsight }}</p>
                    </div>
                </div>

                <!-- Continue card -->
                <Card v-if="continueCourse" title="Continue Learning" hoverable>
                    <template #header-actions>
                        <Badge variant="primary" size="sm">{{ continueCourse.progress }}% complete</Badge>
                    </template>
                    <Link :href="`/courses/${continueCourse.slug}`" class="block group">
                        <div class="flex gap-4">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-100 overflow-hidden shrink-0">
                                <img
                                    v-if="continueCourse.thumbnail"
                                    :src="continueCourse.thumbnail"
                                    :alt="continueCourse.title"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <BookOpen class="w-8 h-8 text-surface-300" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-surface-900 group-hover:text-primary-600 transition-colors truncate">
                                    {{ continueCourse.title }}
                                </h4>
                                <p class="text-sm text-surface-500 mt-0.5">
                                    {{ continueCourse.currentLesson }}
                                </p>
                                <ProgressBar
                                    :percentage="continueCourse.progress"
                                    color="primary"
                                    size="sm"
                                    class="mt-3"
                                />
                                <div class="flex items-center gap-4 mt-2 text-xs text-surface-400">
                                    <span class="flex items-center gap-1">
                                        <Clock class="w-3.5 h-3.5" />
                                        {{ continueCourse.timeLeft }} left
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Layers class="w-3.5 h-3.5" />
                                        {{ continueCourse.lessonsLeft }} lessons remaining
                                    </span>
                                </div>
                            </div>
                            <div class="hidden sm:flex items-center">
                                <div class="w-10 h-10 rounded-full bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition-colors">
                                    <Play class="w-5 h-5 text-primary-600 ml-0.5" />
                                </div>
                            </div>
                        </div>
                    </Link>
                </Card>

                <!-- Active courses -->
                <Card title="My Courses">
                    <template #header-actions>
                        <Link href="/courses" class="text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
                            View all
                        </Link>
                    </template>

                    <div v-if="courses.length" class="grid sm:grid-cols-2 gap-4">
                        <CourseCard
                            v-for="course in courses"
                            :key="course.id"
                            :course="course"
                        />
                    </div>
                    <EmptyState
                        v-else
                        title="No courses yet"
                        message="Start your learning journey by exploring our course catalog."
                        :icon="BookOpen"
                    >
                        <template #action>
                            <Button variant="primary" size="sm" :icon="Search">
                                <Link href="/courses">Browse courses</Link>
                            </Button>
                        </template>
                    </EmptyState>
                </Card>
            </div>

            <!-- Right sidebar -->
            <div class="space-y-6">
                <!-- Today's plan -->
                <Card title="Today's Plan" variant="elevated">
                    <template #header-actions>
                        <span class="text-xs font-medium text-surface-400">{{ todayCompleted }}/{{ todayPlan.length }} done</span>
                    </template>

                    <div class="space-y-3">
                        <Link
                            v-for="(item, i) in todayPlan"
                            :key="i"
                            :href="item.url || '#'"
                            :class="[
                                'flex items-center gap-3 p-3 rounded-xl transition-all',
                                'bg-surface-50 hover:bg-surface-100',
                            ]"
                        >
                            <div class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center shrink-0">
                                <component :is="planIcon(item.icon)" class="w-4 h-4 text-primary-600" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-surface-700 truncate">{{ item.title }}</p>
                                <p class="text-xs text-surface-400">{{ item.subtitle }} · {{ item.time }}</p>
                            </div>
                            <ChevronRight class="w-4 h-4 text-surface-300 shrink-0" />
                        </Link>
                    </div>
                </Card>

                <!-- Daily missions -->
                <Card title="Daily Missions" variant="elevated">
                    <template #header-actions>
                        <Link href="/missions" class="text-xs font-medium text-primary-600 hover:text-primary-700">
                            View all
                        </Link>
                    </template>

                    <div class="space-y-3">
                        <div
                            v-for="mission in dailyMissions"
                            :key="mission.id"
                            class="flex items-center gap-3"
                        >
                            <div :class="[
                                'w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0 transition-all',
                                mission.status === 'completed'
                                    ? 'bg-success-500 border-success-500'
                                    : 'border-surface-300',
                            ]">
                                <svg v-if="mission.status === 'completed'" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p :class="['text-sm font-medium truncate', mission.status === 'completed' ? 'line-through text-surface-400' : 'text-surface-700']">
                                    {{ mission.title }}
                                </p>
                                <div class="mt-1 bg-surface-100 rounded-full h-1.5">
                                    <div
                                        :class="['h-full rounded-full transition-all', mission.status === 'completed' ? 'bg-success-400' : 'bg-primary-400']"
                                        :style="{ width: `${mission.progress_pct}%` }"
                                    />
                                </div>
                            </div>
                            <span class="text-xs text-surface-400 shrink-0">+{{ mission.xp_reward }} XP</span>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-surface-100 text-center">
                        <p class="text-xs text-surface-500">
                            {{ dailyMissionsCompleted }}/{{ dailyMissionsTotal }} missions completed today
                        </p>
                    </div>
                </Card>

                <!-- Practice due -->
                <Card variant="elevated">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-xp-50 flex items-center justify-center">
                            <Brain class="w-5 h-5 text-xp-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-surface-900">Spaced Review</p>
                            <p class="text-xs text-surface-500">{{ dueCards }} cards due today</p>
                        </div>
                    </div>
                    <Link href="/practice/review" class="block">
                        <Button variant="outline" size="sm" class="w-full" :icon="Play">
                            Start review
                        </Button>
                    </Link>
                </Card>

                <!-- Focus areas -->
                <Card v-if="focusAreas.length" title="Focus Areas" variant="elevated">
                    <template #header-actions>
                        <Link href="/practice" class="text-xs font-medium text-primary-600 hover:text-primary-700">Practice</Link>
                    </template>
                    <p class="text-xs text-surface-500 mb-3">Your weakest domains — focus here for fastest improvement</p>
                    <div class="space-y-3">
                        <div v-for="area in focusAreas" :key="area.domain" class="group">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-surface-700">{{ area.domain }}</span>
                                <span :class="['text-xs font-bold', area.accuracy >= 70 ? 'text-success-600' : area.accuracy >= 40 ? 'text-warning-600' : 'text-danger-600']">
                                    {{ area.accuracy }}%
                                </span>
                            </div>
                            <div class="bg-surface-100 rounded-full h-2">
                                <div :class="[
                                    'h-full rounded-full transition-all duration-500',
                                    area.accuracy >= 70 ? 'bg-success-400' : area.accuracy >= 40 ? 'bg-warning-400' : 'bg-danger-400'
                                ]" :style="{ width: `${area.accuracy}%` }" />
                            </div>
                            <p class="text-[11px] text-surface-400 mt-0.5">{{ area.total }} questions attempted</p>
                        </div>
                    </div>
                </Card>

                <!-- Recent badges -->
                <Card v-if="recentBadges.length" title="Recent Achievements" variant="elevated">
                    <template #header-actions>
                        <Link href="/badges" class="text-xs font-medium text-primary-600 hover:text-primary-700">
                            All badges
                        </Link>
                    </template>
                    <div class="space-y-3">
                        <div
                            v-for="badge in recentBadges"
                            :key="badge.name"
                            class="flex items-center gap-3"
                        >
                            <div class="w-10 h-10 rounded-xl bg-xp-50 flex items-center justify-center text-lg">
                                {{ badge.icon || '🏆' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-surface-800 truncate">{{ badge.name }}</p>
                                <p class="text-xs text-surface-400">{{ badge.category }}</p>
                            </div>
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
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import CircularProgress from '@/Components/UI/CircularProgress.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import CourseCard from '@/Components/Course/CourseCard.vue';
import {
    Flame, Zap, BookOpen, Award, Clock, Layers, Play, Search, Brain,
    CalendarCheck, Lightbulb, ChevronRight, ClipboardCheck, Target,
    Calendar, TrendingUp, TrendingDown, Minus, ShieldCheck, AlertTriangle,
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    continueCourse: { type: Object, default: null },
    courses: { type: Array, default: () => [] },
    todayPlan: { type: Array, default: () => [] },
    dueCards: { type: Number, default: 0 },
    coachInsight: { type: String, default: null },
    recentBadges: { type: Array, default: () => [] },
    dailyMissions: { type: Array, default: () => [] },
    dailyMissionsCompleted: { type: Number, default: 0 },
    dailyMissionsTotal: { type: Number, default: 0 },
    focusAreas: { type: Array, default: () => [] },
    examCountdown: { type: Object, default: null },
});

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'morning';
    if (h < 17) return 'afternoon';
    return 'evening';
});

const motivationalMessages = [
    "Every expert was once a beginner. Keep pushing forward!",
    "Consistency beats intensity. You're building something great.",
    "Small daily improvements lead to stunning results.",
    "Your future self will thank you for the effort today.",
];

const motivationalMessage = motivationalMessages[Math.floor(Math.random() * motivationalMessages.length)];

const readinessScore = computed(() => props.stats.readiness || 0);
const readinessLabel = computed(() => {
    if (readinessScore.value >= 80) return 'Exam Ready';
    if (readinessScore.value >= 60) return 'On Track';
    if (readinessScore.value >= 40) return 'Building Up';
    return 'Just Started';
});

const todayCompleted = computed(() => props.todayPlan.filter(i => i.completed).length);

const passLikelihoodStyle = computed(() => {
    const pct = props.stats.passLikelihood || 0;
    if (pct >= 75) return { bg: 'bg-success-500/20 text-success-200', icon: ShieldCheck };
    if (pct >= 50) return { bg: 'bg-accent-500/20 text-accent-200', icon: TrendingUp };
    return { bg: 'bg-warning-500/20 text-warning-200', icon: AlertTriangle };
});

const iconMap = { 'brain': Brain, 'book-open': BookOpen, 'clipboard-check': ClipboardCheck, 'target': Target, 'play': Play, 'zap': Zap };
const planIcon = (name) => iconMap[name] || BookOpen;
</script>

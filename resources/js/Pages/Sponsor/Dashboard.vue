<template>
    <Head title="Sponsor Dashboard" />

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 p-6 sm:p-8">
            <div class="absolute top-0 right-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -translate-y-1/3 translate-x-1/3" />
            <div class="relative z-10">
                <h1 class="text-2xl sm:text-3xl font-display font-bold text-white mb-1">
                    Sponsor Dashboard
                </h1>
                <p class="text-emerald-200 text-sm sm:text-base">
                    Track your interns' progress, review submissions, and provide feedback.
                </p>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
                label="Active Interns"
                :value="stats.activeInterns"
                :icon="Users"
                color="primary"
            />
            <StatCard
                label="Pending Reviews"
                :value="stats.pendingReviews"
                :icon="ClipboardCheck"
                :color="stats.pendingReviews > 0 ? 'warning' : 'success'"
            />
            <StatCard
                label="Tasks Completed"
                :value="`${stats.completedTasks}/${stats.totalTasks}`"
                :icon="CheckSquare"
                color="success"
            />
            <StatCard
                label="Avg Score"
                :value="stats.avgScore ? `${stats.avgScore}/5` : '—'"
                :icon="Star"
                color="xp"
            />
        </div>

        <!-- Main content grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left: Intern Progress + Pending Reviews (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Intern Progress Cards -->
                <Card title="Intern Progress">
                    <template #header-actions>
                        <span class="text-xs text-surface-400">{{ interns.length }} active</span>
                    </template>

                    <div v-if="interns.length" class="space-y-4">
                        <Link
                            v-for="intern in interns"
                            :key="intern.id"
                            :href="`/sponsor/interns/${intern.id}/progress`"
                            class="block p-4 rounded-xl bg-surface-50 hover:bg-surface-100 transition-all group"
                        >
                            <div class="flex items-center gap-4">
                                <Avatar :name="intern.intern?.name" size="md" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <h4 class="font-semibold text-surface-900 group-hover:text-primary-600 truncate">
                                            {{ intern.intern?.name }}
                                        </h4>
                                        <Badge :variant="intern.overall_score >= 4 ? 'success' : intern.overall_score >= 3 ? 'warning' : 'default'" size="sm">
                                            {{ intern.overall_score ? `${intern.overall_score}/5` : 'No score' }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-surface-500 mb-2">
                                        {{ intern.track }} - Week {{ intern.current_week }} of {{ intern.duration_weeks }}
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <ProgressBar :percentage="intern.progress_pct" color="primary" size="sm" class="flex-1" />
                                        <span class="text-xs font-medium text-surface-600 whitespace-nowrap">{{ intern.progress_pct }}%</span>
                                    </div>
                                </div>
                                <ChevronRight class="w-4 h-4 text-surface-300 shrink-0" />
                            </div>
                        </Link>
                    </div>
                    <EmptyState
                        v-else
                        title="No active interns"
                        message="You don't have any active intern assignments yet."
                        :icon="Users"
                    />
                </Card>

                <!-- Pending Reviews -->
                <Card title="Pending Reviews" noPadding>
                    <template #header-actions>
                        <Link href="/sponsor/review-queue" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            View all
                        </Link>
                    </template>

                    <div v-if="pendingReviews.length" class="divide-y divide-surface-100">
                        <Link
                            v-for="sub in pendingReviews"
                            :key="sub.id"
                            :href="`/sponsor/submissions/${sub.id}`"
                            class="flex items-center gap-4 px-6 py-4 hover:bg-surface-50 transition-colors group"
                        >
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                 :class="taskTypeClass(sub.task?.task_type)">
                                <component :is="taskTypeIcon(sub.task?.task_type)" class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-surface-900 group-hover:text-primary-600 truncate">
                                    {{ sub.task?.title }}
                                </p>
                                <p class="text-xs text-surface-500 mt-0.5">
                                    {{ sub.task?.enrollment?.user?.first_name }} {{ sub.task?.enrollment?.user?.last_name }}
                                    - Submitted {{ formatDate(sub.submitted_at) }}
                                </p>
                            </div>
                            <Badge :variant="sub.status === 'submitted' ? 'warning' : 'primary'" size="sm">
                                {{ sub.status }}
                            </Badge>
                        </Link>
                    </div>
                    <div v-else class="p-6">
                        <EmptyState
                            title="All caught up!"
                            message="No submissions awaiting your review."
                            :icon="CheckSquare"
                        />
                    </div>
                </Card>
            </div>

            <!-- Right sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <Card title="Quick Actions">
                    <div class="space-y-3">
                        <Link href="/sponsor/review-queue"
                              class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-primary-50 hover:text-primary-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
                                <ClipboardCheck class="w-4 h-4 text-primary-600" />
                            </div>
                            <span class="text-sm font-medium">Review Queue</span>
                            <Badge v-if="stats.pendingReviews > 0" variant="warning" size="sm" class="ml-auto">
                                {{ stats.pendingReviews }}
                            </Badge>
                        </Link>
                        <button v-for="intern in interns.slice(0, 2)" :key="`sc-${intern.id}`"
                              @click="$inertia.visit(`/sponsor/interns/${intern.id}/scorecard`)"
                              class="w-full flex items-center gap-3 p-3 rounded-xl bg-surface-50 hover:bg-xp-50 hover:text-xp-700 transition-all">
                            <div class="w-8 h-8 rounded-lg bg-xp-100 flex items-center justify-center">
                                <Star class="w-4 h-4 text-xp-600" />
                            </div>
                            <div class="text-left flex-1 min-w-0">
                                <span class="text-sm font-medium block truncate">Scorecard</span>
                                <span class="text-xs text-surface-400 truncate block">{{ intern.intern?.name }}</span>
                            </div>
                        </button>
                    </div>
                </Card>

                <!-- Upcoming Meetings -->
                <Card title="Upcoming Meetings">
                    <template #header-actions>
                        <span class="text-xs text-surface-400">{{ upcomingMeetings.length }} scheduled</span>
                    </template>

                    <div v-if="upcomingMeetings.length" class="space-y-3">
                        <div
                            v-for="meeting in upcomingMeetings"
                            :key="meeting.id"
                            class="p-3 rounded-xl bg-surface-50"
                        >
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-12 rounded-lg bg-primary-100 flex flex-col items-center justify-center shrink-0">
                                    <span class="text-[10px] font-bold text-primary-600 uppercase">{{ monthAbbr(meeting.scheduled_at) }}</span>
                                    <span class="text-lg font-bold text-primary-700 leading-none">{{ dayNum(meeting.scheduled_at) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-surface-900 text-sm truncate">{{ meeting.title }}</p>
                                    <p class="text-xs text-surface-500 mt-0.5">
                                        {{ formatTime(meeting.scheduled_at) }} - {{ meeting.duration_minutes }}min
                                    </p>
                                    <a v-if="meeting.meeting_url" :href="meeting.meeting_url" target="_blank"
                                       class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 mt-1 font-medium">
                                        <Video class="w-3 h-3" /> Join
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else title="No upcoming meetings" message="No meetings scheduled." :icon="Calendar" />
                </Card>

                <!-- Recent Activity -->
                <Card title="Recent Reviews">
                    <div v-if="recentReviews.length" class="space-y-3">
                        <div
                            v-for="review in recentReviews"
                            :key="review.id"
                            class="flex items-center gap-3"
                        >
                            <div :class="[
                                'w-8 h-8 rounded-full flex items-center justify-center shrink-0',
                                review.decision === 'approved' ? 'bg-success-100' : 'bg-warning-100'
                            ]">
                                <CheckCircle v-if="review.decision === 'approved'" class="w-4 h-4 text-success-600" />
                                <RotateCcw v-else class="w-4 h-4 text-warning-600" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-surface-800 truncate">
                                    {{ review.submission?.task?.title }}
                                </p>
                                <p class="text-xs text-surface-400">
                                    {{ review.submission?.task?.enrollment?.user?.first_name }}
                                    - {{ formatDate(review.reviewed_at) }}
                                </p>
                            </div>
                            <Badge :variant="review.decision === 'approved' ? 'success' : 'warning'" size="sm">
                                {{ review.decision === 'approved' ? 'Approved' : 'Revision' }}
                            </Badge>
                        </div>
                    </div>
                    <EmptyState v-else title="No reviews yet" message="Your review history will appear here." :icon="ClipboardCheck" />
                </Card>

                <!-- Overdue Alert -->
                <div v-if="stats.overdueTasks > 0" class="p-4 rounded-xl bg-danger-50 border border-danger-200">
                    <div class="flex items-center gap-3">
                        <AlertTriangle class="w-5 h-5 text-danger-600 shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-danger-800">{{ stats.overdueTasks }} Overdue Tasks</p>
                            <p class="text-xs text-danger-600 mt-0.5">Some interns have overdue tasks that need attention.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SponsorLayout from '@/Layouts/SponsorLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Avatar from '@/Components/UI/Avatar.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import {
    Users, ClipboardCheck, CheckSquare, Star, ChevronRight, Calendar, Video,
    CheckCircle, RotateCcw, AlertTriangle, FileText, Repeat, Briefcase,
} from 'lucide-vue-next';

defineOptions({ layout: SponsorLayout });

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    interns: { type: Array, default: () => [] },
    pendingReviews: { type: Array, default: () => [] },
    recentReviews: { type: Array, default: () => [] },
    upcomingMeetings: { type: Array, default: () => [] },
});

const taskTypeIcon = (type) => {
    const map = { daily: Repeat, weekly: Briefcase, monthly: FileText };
    return map[type] || FileText;
};
const taskTypeClass = (type) => {
    const map = {
        daily: 'bg-blue-100 text-blue-600',
        weekly: 'bg-purple-100 text-purple-600',
        monthly: 'bg-amber-100 text-amber-600',
    };
    return map[type] || 'bg-surface-100 text-surface-600';
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
const formatTime = (d) => d ? new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
const monthAbbr = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short' }) : '';
const dayNum = (d) => d ? new Date(d).getDate() : '';
</script>

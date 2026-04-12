<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    MessageSquare,
    Users,
    Calendar,
    Mail,
    ArrowRight,
    Globe,
} from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            forums: 0,
            studyGroups: 0,
            upcomingEvents: 0,
            unreadMessages: 0,
        }),
    },
});

const sections = [
    {
        title: 'Discussion Forums',
        description: 'Join conversations, ask questions, and share knowledge with fellow learners.',
        icon: MessageSquare,
        href: '/forums',
        stat: props.stats.forums,
        statLabel: 'forums',
        color: 'bg-blue-500',
    },
    {
        title: 'Study Groups',
        description: 'Collaborate with peers in focused study groups to learn together.',
        icon: Users,
        href: '/study-groups',
        stat: props.stats.studyGroups,
        statLabel: 'active groups',
        color: 'bg-emerald-500',
    },
    {
        title: 'Events',
        description: 'Discover upcoming webinars, workshops, and networking events.',
        icon: Calendar,
        href: '/events',
        stat: props.stats.upcomingEvents,
        statLabel: 'upcoming',
        color: 'bg-purple-500',
    },
    {
        title: 'Messages',
        description: 'Send direct messages to facilitators, mentors, and fellow learners.',
        icon: Mail,
        href: '/messages',
        stat: props.stats.unreadMessages,
        statLabel: 'unread',
        color: 'bg-amber-500',
    },
];
</script>

<template>
    <Head title="Community" />
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <Globe class="w-5 h-5 text-indigo-600" />
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Community</h1>
                </div>
                <p class="text-gray-500 ml-13">
                    Connect, collaborate, and grow together with your learning community.
                </p>
            </div>

            <!-- Section Cards Grid -->
            <div class="grid gap-6 sm:grid-cols-2">
                <Link
                    v-for="section in sections"
                    :key="section.title"
                    :href="section.href"
                    class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-gray-300"
                >
                    <!-- Icon + Stat Badge -->
                    <div class="flex items-start justify-between mb-4">
                        <div :class="[section.color, 'w-12 h-12 rounded-xl flex items-center justify-center']">
                            <component :is="section.icon" class="w-6 h-6 text-white" />
                        </div>
                        <span
                            v-if="section.stat > 0"
                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700"
                        >
                            {{ section.stat }} {{ section.statLabel }}
                        </span>
                    </div>

                    <!-- Title + Description -->
                    <h2 class="text-lg font-semibold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors">
                        {{ section.title }}
                    </h2>
                    <p class="text-sm text-gray-500 leading-relaxed">
                        {{ section.description }}
                    </p>

                    <!-- Arrow -->
                    <div class="absolute bottom-6 right-6 opacity-0 translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition">
                        <ArrowRight class="w-5 h-5 text-indigo-500" />
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

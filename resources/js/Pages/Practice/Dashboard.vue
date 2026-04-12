<template>
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-display font-bold text-surface-900">Practice Hub</h1>
                <p class="text-surface-500 mt-1">Review flashcards, strengthen weak areas, and build exam confidence</p>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-surface-200 p-5 text-center">
                <Brain class="w-6 h-6 text-primary-500 mx-auto mb-2" />
                <p class="text-3xl font-bold text-surface-900">{{ stats.due_today }}</p>
                <p class="text-xs text-surface-500">Due Today</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-5 text-center">
                <Layers class="w-6 h-6 text-accent-500 mx-auto mb-2" />
                <p class="text-3xl font-bold text-surface-900">{{ stats.total_active }}</p>
                <p class="text-xs text-surface-500">Active Cards</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-5 text-center">
                <BookOpen class="w-6 h-6 text-xp-500 mx-auto mb-2" />
                <p class="text-3xl font-bold text-surface-900">{{ stats.learning }}</p>
                <p class="text-xs text-surface-500">Learning</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-5 text-center">
                <GraduationCap class="w-6 h-6 text-success-500 mx-auto mb-2" />
                <p class="text-3xl font-bold text-surface-900">{{ stats.graduated }}</p>
                <p class="text-xs text-surface-500">Graduated</p>
            </div>
        </div>

        <!-- Action cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Start Review -->
            <div class="bg-gradient-to-br from-primary-50 to-primary-100 rounded-2xl border border-primary-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-primary-600 flex items-center justify-center">
                        <RotateCcw class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-900">Spaced Repetition Review</h3>
                        <p class="text-sm text-surface-600">{{ stats.due_today }} cards due now</p>
                    </div>
                </div>
                <p class="text-sm text-surface-600 mb-4">
                    Review flashcards on an optimized schedule. The SM-2 algorithm ensures you review just before you'd forget.
                </p>
                <Link
                    :href="route('practice.review')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition"
                >
                    <Play class="w-4 h-4" />
                    Start Review ({{ stats.due_today }})
                </Link>
            </div>

            <!-- Flashcards -->
            <div class="bg-gradient-to-br from-accent-50 to-accent-100 rounded-2xl border border-accent-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-accent-600 flex items-center justify-center">
                        <CreditCard class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-900">My Flashcards</h3>
                        <p class="text-sm text-surface-600">{{ flashcardCount }} total cards</p>
                    </div>
                </div>
                <p class="text-sm text-surface-600 mb-4">
                    Browse, create, and manage your flashcards. Cards are auto-created from quiz mistakes.
                </p>
                <Link
                    :href="route('practice.flashcards')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent-600 text-white text-sm font-semibold rounded-xl hover:bg-accent-700 transition"
                >
                    <CreditCard class="w-4 h-4" />
                    Manage Flashcards
                </Link>
            </div>
        </div>

        <!-- Progress breakdown -->
        <div class="bg-white rounded-2xl border border-surface-200 p-6">
            <h3 class="font-semibold text-surface-900 mb-4">Card Status Breakdown</h3>
            <div class="flex items-center gap-3 mb-3">
                <div class="flex-1 h-4 rounded-full bg-surface-100 overflow-hidden flex">
                    <div class="h-full bg-primary-400" :style="{ width: statusPct('new') }" title="New"></div>
                    <div class="h-full bg-warning-400" :style="{ width: statusPct('learning') }" title="Learning"></div>
                    <div class="h-full bg-accent-400" :style="{ width: statusPct('review') }" title="Review"></div>
                    <div class="h-full bg-success-400" :style="{ width: statusPct('graduated') }" title="Graduated"></div>
                </div>
            </div>
            <div class="flex items-center gap-6 text-xs text-surface-600">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-primary-400"></span> New ({{ stats.new }})</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-warning-400"></span> Learning ({{ stats.learning }})</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-accent-400"></span> Review ({{ stats.review }})</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-success-400"></span> Graduated ({{ stats.graduated }})</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { Brain, Layers, BookOpen, GraduationCap, RotateCcw, Play, CreditCard } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    stats: Object,
    flashcardCount: Number,
})

function statusPct(status) {
    const total = props.stats.total_active + props.stats.graduated
    if (total === 0) return '0%'
    return Math.round((props.stats[status] / total) * 100) + '%'
}
</script>

<template>
    <Head title="...a forward thinking Institute" />

    <div class="min-h-screen bg-gradient-to-b from-primary-950 via-primary-800 to-primary-950 relative overflow-hidden">
        <!-- Decorative background shapes -->
        <div class="absolute top-0 right-1/4 w-[700px] h-[700px] bg-primary-400/8 rounded-full blur-3xl" />
        <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-primary-600/10 rounded-full blur-3xl" />
        <div class="absolute top-1/2 right-0 w-96 h-96 bg-primary-300/5 rounded-full blur-3xl" />

        <!-- Grid overlay -->
        <div class="absolute inset-0 opacity-[0.03]">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col min-h-screen">
            <!-- Header with centered logo -->
            <header class="flex items-center justify-center px-6 sm:px-10 lg:px-16 py-8">
                <div class="flex flex-col items-center gap-3">
                    <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-3 shadow-lg shadow-black/10">
                        <img src="/images/logo.png" alt="JK Michaels Institute" class="h-16 sm:h-20 object-contain" />
                    </div>
                </div>
            </header>

            <!-- Hero Section -->
            <main class="flex-1 flex flex-col items-center justify-center px-6 sm:px-10 lg:px-16 py-12">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 border border-white/10 mb-6">
                        <span class="w-2 h-2 rounded-full bg-accent-400 animate-pulse" />
                        <span class="text-white/70 text-sm font-medium">Nigeria's Leading Professional Certification Platform</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-display font-bold text-white leading-tight mb-6">
                        ...a forward thinking
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-300 to-primary-400">
                            Institute
                        </span>
                    </h1>
                </div>

                <!-- Role-based Login Cards -->
                <div class="w-full max-w-5xl mx-auto">
                    <p class="text-center text-white/50 text-sm font-medium uppercase tracking-widest mb-6">
                        Select your portal to sign in
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-3xl mx-auto">
                        <button
                            v-for="portal in portals"
                            :key="portal.role"
                            @click="selectPortal(portal)"
                            class="group relative p-6 rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm hover:bg-white/10 hover:border-white/20 transition-all duration-300 text-left"
                        >
                            <div class="flex items-start gap-4">
                                <div :class="[portal.iconBg, 'w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0']">
                                    <component :is="portal.icon" class="w-6 h-6 text-white" />
                                </div>
                                <div>
                                    <h3 class="text-white font-semibold text-lg mb-1">{{ portal.title }}</h3>
                                    <p class="text-white/50 text-sm leading-relaxed">{{ portal.description }}</p>
                                </div>
                            </div>
                            <div class="absolute top-6 right-6 opacity-0 group-hover:opacity-100 transition-opacity">
                                <ArrowRight class="w-5 h-5 text-white/60" />
                            </div>
                        </button>
                    </div>

                    <!-- General Login -->
                    <div class="text-center mt-8">
                        <p class="text-white/40 text-sm mb-3">Or sign in with your credentials — we'll redirect you automatically</p>
                        <a
                            href="/login"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white/10 border border-white/10 text-white font-semibold hover:bg-white/15 hover:border-white/20 transition-all"
                        >
                            <LogIn class="w-4 h-4" />
                            General Sign In
                        </a>
                    </div>
                </div>

                <!-- Stats ribbon -->
                <div class="flex flex-wrap justify-center gap-8 sm:gap-12 mt-16 pt-12 border-t border-white/10 max-w-3xl mx-auto w-full">
                    <div class="text-center">
                        <p class="text-3xl font-display font-bold text-white">Learn</p>
                        <p class="text-white/40 text-sm mt-0.5">Build your knowledge</p>
                    </div>
                    <div class="text-center">
                        <p class="text-3xl font-display font-bold text-white">Practice</p>
                        <p class="text-white/40 text-sm mt-0.5">Develop confidence</p>
                    </div>
                    <div class="text-center">
                        <p class="text-3xl font-display font-bold text-white">Apply</p>
                        <p class="text-white/40 text-sm mt-0.5">Work on real challenges</p>
                    </div>
                    <div class="text-center">
                        <p class="text-3xl font-display font-bold text-white">Advance</p>
                        <p class="text-white/40 text-sm mt-0.5">Grow professionally</p>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="px-6 sm:px-10 lg:px-16 py-6 text-center">
                <p class="text-white/30 text-xs">
                    &copy; {{ new Date().getFullYear() }} JK Michaels Institute. All rights reserved.
                </p>
            </footer>
        </div>

        <!-- Demo Login Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showModal = false">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false" />
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 z-10">
                        <button @click="showModal = false" class="absolute top-4 right-4 text-surface-400 hover:text-surface-600 transition-colors">
                            <X class="w-5 h-5" />
                        </button>

                        <div class="flex items-center gap-3 mb-6">
                            <div :class="[selectedPortal?.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center']">
                                <component :is="selectedPortal?.icon" class="w-5 h-5 text-white" />
                            </div>
                            <div>
                                <h3 class="font-display font-bold text-surface-900 text-lg">{{ selectedPortal?.title }} Portal</h3>
                                <p class="text-surface-500 text-sm">Sign in to access your dashboard</p>
                            </div>
                        </div>

                        <form @submit.prevent="submitLogin" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Email address</label>
                                <div class="relative">
                                    <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" />
                                    <input
                                        v-model="loginForm.email"
                                        type="email"
                                        required
                                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 text-sm transition"
                                        placeholder="you@company.com"
                                    />
                                </div>
                                <p v-if="loginForm.errors.email" class="text-danger-500 text-xs mt-1">{{ loginForm.errors.email }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Password</label>
                                <div class="relative">
                                    <Lock class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" />
                                    <input
                                        v-model="loginForm.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-surface-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 text-sm transition"
                                        placeholder="Enter your password"
                                    />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600">
                                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                                        <Eye v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <p v-if="loginForm.errors.password" class="text-danger-500 text-xs mt-1">{{ loginForm.errors.password }}</p>
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="loginForm.remember"
                                    type="checkbox"
                                    class="w-4 h-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500/20"
                                />
                                <span class="text-sm text-surface-600">Remember me</span>
                            </label>

                            <button
                                type="submit"
                                :disabled="loginForm.processing"
                                class="w-full py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-semibold text-sm transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <Loader2 v-if="loginForm.processing" class="w-4 h-4 animate-spin" />
                                <span>{{ loginForm.processing ? 'Signing in...' : 'Sign In' }}</span>
                            </button>
                        </form>

                        <div v-if="selectedPortal?.demoEmail" class="mt-4 p-3 rounded-xl bg-surface-50 border border-surface-100">
                            <p class="text-xs text-surface-500 font-medium mb-2">Demo credentials:</p>
                            <button
                                @click="fillDemo"
                                class="text-xs text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1 transition-colors"
                            >
                                <Zap class="w-3 h-3" />
                                Quick fill: {{ selectedPortal.demoEmail }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- PWA Install Prompt -->
        <PwaInstallPrompt />
    </div>
</template>

<script setup>
import { ref, markRaw } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    GraduationCap,
    ShieldCheck,
    Users,
    Microscope,
    Briefcase,
    ArrowRight,
    LogIn,
    Mail,
    Lock,
    Eye,
    EyeOff,
    X,
    Loader2,
    Zap,
} from 'lucide-vue-next';

const portals = [
    {
        role: 'learner',
        title: 'Learner',
        description: 'Access courses, mock exams, certifications, and track your learning progress.',
        icon: markRaw(GraduationCap),
        iconBg: 'bg-gradient-to-br from-primary-500 to-primary-700',
        demoEmail: 'tunde.adeleke@example.com',
    },
    {
        role: 'facilitator',
        title: 'Facilitator',
        description: 'Manage cohorts, weekly plans, assignments, and learner interventions.',
        icon: markRaw(Users),
        iconBg: 'bg-gradient-to-br from-accent-500 to-accent-700',
        demoEmail: 'adaeze@jkmichaels.com',
    },
    {
        role: 'sme',
        title: 'SME Reviewer',
        description: 'Review content quality, manage question banks, and resolve flags.',
        icon: markRaw(Microscope),
        iconBg: 'bg-gradient-to-br from-purple-500 to-purple-700',
        demoEmail: 'emeka.obi@jkmichaels.com',
    },
    {
        role: 'sponsor',
        title: 'Sponsor',
        description: 'Monitor intern progress, review submissions, and schedule meetings.',
        icon: markRaw(Briefcase),
        iconBg: 'bg-gradient-to-br from-emerald-500 to-emerald-700',
        demoEmail: 'sponsor@vertex.com',
    },
];

const showModal = ref(false);
const selectedPortal = ref(null);
const showPassword = ref(false);

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

function selectPortal(portal) {
    selectedPortal.value = portal;
    loginForm.email = '';
    loginForm.password = '';
    loginForm.remember = false;
    loginForm.clearErrors();
    showModal.value = true;
}

function fillDemo() {
    if (selectedPortal.value?.demoEmail) {
        loginForm.email = selectedPortal.value.demoEmail;
        loginForm.password = 'password';
    }
}

function submitLogin() {
    loginForm.post('/login', {
        onFinish: () => loginForm.reset('password'),
    });
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>

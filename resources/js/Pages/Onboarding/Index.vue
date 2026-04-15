<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import {
    Target, User, BookOpen, CheckCircle, ChevronRight, ChevronLeft,
    Sparkles, GraduationCap, Briefcase, Trophy, Eye, Ear, BookOpenText,
    Clock, Accessibility, Zap, Brain, ArrowRight,
} from 'lucide-vue-next';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineOptions({ layout: AuthLayout });

const props = defineProps({
    user: Object,
    courses: Array,
    diagnosticQuestions: { type: Array, default: () => [] },
});

const currentStep = ref(1);
const totalSteps = 5;

// ── Step 2: Goal Selection ───────────────────
const goals = [
    { value: 'certification', label: 'Pass My Exam', desc: 'Prepare for professional certification exams', icon: GraduationCap, color: 'primary' },
    { value: 'skill', label: 'Build Management Skills', desc: 'Develop leadership & project management skills', icon: Briefcase, color: 'accent' },
    { value: 'internship', label: 'Start an Internship', desc: 'Launch your career with hands-on experience', icon: Trophy, color: 'xp' },
];
const selectedGoal = ref(props.user?.goal || '');

// ── Step 3: Profile Setup ────────────────────
const examDate = ref(props.user?.exam_date || '');
const dailyMinutes = ref(props.user?.daily_minutes || 15);
const learningPreference = ref(props.user?.learning_preference || 'read');
const experienceLevel = ref(props.user?.experience_level || 'beginner');
const accessibilityPrefs = ref(props.user?.accessibility_prefs || {
    dyslexia_font: false,
    high_contrast: false,
    color_blind: false,
    extended_time: false,
});
const showAccessibility = ref(false);

const dailyMinuteOptions = [10, 15, 20, 30, 45, 60];
const formatOptions = [
    { value: 'read', label: 'Read', desc: 'Articles & text lessons', icon: BookOpenText },
    { value: 'watch', label: 'Watch', desc: 'Video tutorials', icon: Eye },
    { value: 'listen', label: 'Listen', desc: 'Audio & podcasts', icon: Ear },
];
const expOptions = [
    { value: 'beginner', label: 'Beginner', desc: '0–2 years' },
    { value: 'intermediate', label: 'Intermediate', desc: '3–5 years' },
    { value: 'advanced', label: 'Advanced', desc: '5+ years' },
];

// ── Step 4: Diagnostic ──────────────────────
const skipDiagnostic = ref(false);
const diagnosticIdx = ref(0);
const diagnosticAnswers = ref({});
const diagnosticResult = ref(null);
const currentDQ = computed(() => props.diagnosticQuestions[diagnosticIdx.value] || null);
const diagnosticProgress = computed(() =>
    props.diagnosticQuestions.length > 0
        ? Math.round((diagnosticIdx.value / props.diagnosticQuestions.length) * 100)
        : 0
);

function selectDiagnosticAnswer(optionIndex) {
    if (!currentDQ.value) return;
    diagnosticAnswers.value[currentDQ.value.id] = optionIndex;
}

function nextDiagnosticQuestion() {
    if (diagnosticIdx.value < props.diagnosticQuestions.length - 1) {
        diagnosticIdx.value++;
    }
}

function prevDiagnosticQuestion() {
    if (diagnosticIdx.value > 0) diagnosticIdx.value--;
}

// ── Step 5: Quick Win ────────────────────────
const quickWinDone = ref(false);
const xpAwarded = ref(false);

// ── Save logic ───────────────────────────────
const saving = ref(false);

async function saveGoal() {
    saving.value = true;
    try {
        await fetch(route('onboarding.saveStep'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ step: 'goal', goal: selectedGoal.value }),
        });
        currentStep.value = 3;
    } finally { saving.value = false; }
}

async function saveProfile() {
    saving.value = true;
    try {
        await fetch(route('onboarding.saveStep'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({
                step: 'profile',
                exam_date: examDate.value || null,
                daily_minutes: dailyMinutes.value,
                learning_preference: learningPreference.value,
                experience_level: experienceLevel.value,
                accessibility_prefs: accessibilityPrefs.value,
            }),
        });
        currentStep.value = 4;
    } finally { saving.value = false; }
}

async function submitDiagnostic() {
    saving.value = true;
    try {
        const responses = Object.entries(diagnosticAnswers.value).map(([qid, sel]) => ({
            question_id: parseInt(qid),
            selected: sel,
        }));
        const res = await fetch(route('onboarding.saveStep'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ step: 'diagnostic', responses }),
        });
        const data = await res.json();
        diagnosticResult.value = data;
        currentStep.value = 5;
    } finally { saving.value = false; }
}

async function finishOnboarding() {
    saving.value = true;
    try {
        await fetch(route('onboarding.saveStep'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ step: 'complete' }),
        });
        xpAwarded.value = true;
        quickWinDone.value = true;

        // Redirect after celebration
        setTimeout(() => {
            router.post(route('onboarding.complete'));
        }, 2000);
    } finally { saving.value = false; }
}

const stepLabels = [
    { title: 'Welcome', icon: Sparkles },
    { title: 'Your Goal', icon: Target },
    { title: 'Profile', icon: User },
    { title: 'Assessment', icon: Brain },
    { title: 'Quick Win', icon: Trophy },
];
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-accent-50 flex items-center justify-center p-4">
        <div class="w-full max-w-xl">
            <!-- Progress indicators -->
            <div v-if="currentStep > 1" class="flex items-center justify-center gap-2 mb-8">
                <template v-for="(step, i) in stepLabels" :key="i">
                    <div :class="[
                        'w-9 h-9 rounded-full flex items-center justify-center transition-all text-xs font-bold',
                        currentStep > i + 1 ? 'bg-success-500 text-white' :
                        currentStep === i + 1 ? 'bg-primary-600 text-white shadow-lg shadow-primary-200' :
                        'bg-surface-200 text-surface-400'
                    ]">
                        <component :is="step.icon" class="w-4 h-4" />
                    </div>
                    <div v-if="i < stepLabels.length - 1" :class="['w-8 h-1 rounded-full transition-all', currentStep > i + 1 ? 'bg-success-400' : 'bg-surface-200']" />
                </template>
            </div>

            <!-- ═══════════ STEP 1: WELCOME ═══════════ -->
            <div v-if="currentStep === 1" class="text-center">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 p-10 sm:p-14">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-accent-400/10 rounded-full blur-3xl -translate-y-1/3 translate-x-1/3" />
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-xp-500/10 rounded-full blur-3xl translate-y-1/3 -translate-x-1/3" />

                    <div class="relative z-10">
                        <div class="w-20 h-20 rounded-2xl bg-white/10 backdrop-blur-sm flex items-center justify-center mx-auto mb-6 border border-white/20">
                            <GraduationCap class="w-10 h-10 text-white" />
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-display font-bold text-white mb-3">
                            Welcome to JK Michaels
                        </h1>
                        <p class="text-primary-200 text-lg mb-2">Professional Mastery Platform</p>
                        <p class="text-primary-300/80 text-sm max-w-sm mx-auto">
                            Let's personalize your learning experience in under 2 minutes.
                        </p>
                        <p class="text-primary-300/60 text-xs mt-4">Join 10,000+ professionals already learning</p>

                        <Button @click="currentStep = 2" variant="primary" class="mt-8 !bg-white !text-primary-700 hover:!bg-primary-50 !px-8 !py-3 !text-base font-semibold">
                            Get Started <ArrowRight class="w-5 h-5 ml-2" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- ═══════════ STEP 2: GOAL SELECTION ═══════════ -->
            <Card v-if="currentStep === 2" class="p-6 sm:p-8">
                <h2 class="text-2xl font-display font-bold text-surface-900 text-center mb-2">What's your main goal?</h2>
                <p class="text-surface-500 text-center mb-6">This helps us tailor your experience</p>

                <div class="space-y-3">
                    <button
                        v-for="goal in goals"
                        :key="goal.value"
                        @click="selectedGoal = goal.value"
                        :class="[
                            'w-full p-5 rounded-xl border-2 text-left transition-all flex items-center gap-4',
                            selectedGoal === goal.value
                                ? 'border-primary-500 bg-primary-50 shadow-md shadow-primary-100'
                                : 'border-surface-200 hover:border-primary-200 hover:bg-surface-50'
                        ]"
                    >
                        <div :class="['w-12 h-12 rounded-xl flex items-center justify-center shrink-0', selectedGoal === goal.value ? 'bg-primary-100' : 'bg-surface-100']">
                            <component :is="goal.icon" :class="['w-6 h-6', selectedGoal === goal.value ? 'text-primary-600' : 'text-surface-400']" />
                        </div>
                        <div>
                            <p class="font-semibold text-surface-800 text-lg">{{ goal.label }}</p>
                            <p class="text-sm text-surface-500 mt-0.5">{{ goal.desc }}</p>
                        </div>
                    </button>
                </div>

                <div class="flex justify-between items-center mt-8">
                    <button @click="currentStep = 1" class="text-sm text-surface-400 hover:text-surface-600">← Back</button>
                    <Button @click="saveGoal" variant="primary" :disabled="!selectedGoal || saving" class="!px-6">
                        Continue <ChevronRight class="w-4 h-4 ml-1" />
                    </Button>
                </div>
            </Card>

            <!-- ═══════════ STEP 3: PROFILE SETUP ═══════════ -->
            <Card v-if="currentStep === 3" class="p-6 sm:p-8">
                <h2 class="text-2xl font-display font-bold text-surface-900 text-center mb-2">Set up your profile</h2>
                <p class="text-surface-500 text-center mb-6">We'll build your personalized learning plan</p>

                <div class="space-y-6">
                    <!-- Exam date (show if goal is certification) -->
                    <div v-if="selectedGoal === 'certification'">
                        <label class="block text-sm font-semibold text-surface-700 mb-2">📅 When is your exam?</label>
                        <input v-model="examDate" type="date" class="w-full px-4 py-2.5 border border-surface-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                        <p class="text-xs text-surface-400 mt-1">We'll create a countdown and paced study plan</p>
                    </div>

                    <!-- Daily study time slider -->
                    <div>
                        <label class="block text-sm font-semibold text-surface-700 mb-2">
                            <Clock class="w-4 h-4 inline -mt-0.5 mr-1" /> Daily study time
                        </label>
                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-for="mins in dailyMinuteOptions"
                                :key="mins"
                                @click="dailyMinutes = mins"
                                :class="[
                                    'px-4 py-2 rounded-lg border-2 text-sm font-medium transition-all',
                                    dailyMinutes === mins
                                        ? 'border-primary-500 bg-primary-50 text-primary-700'
                                        : 'border-surface-200 text-surface-600 hover:border-primary-200'
                                ]"
                            >
                                {{ mins }} min
                            </button>
                        </div>
                    </div>

                    <!-- Learning preference -->
                    <div>
                        <label class="block text-sm font-semibold text-surface-700 mb-2">How do you learn best?</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button
                                v-for="fmt in formatOptions"
                                :key="fmt.value"
                                @click="learningPreference = fmt.value"
                                :class="[
                                    'p-4 rounded-xl border-2 text-center transition-all',
                                    learningPreference === fmt.value
                                        ? 'border-primary-500 bg-primary-50'
                                        : 'border-surface-200 hover:border-primary-200'
                                ]"
                            >
                                <component :is="fmt.icon" :class="['w-6 h-6 mx-auto mb-1', learningPreference === fmt.value ? 'text-primary-600' : 'text-surface-400']" />
                                <p :class="['text-sm font-semibold', learningPreference === fmt.value ? 'text-primary-700' : 'text-surface-700']">{{ fmt.label }}</p>
                                <p class="text-[11px] text-surface-400 mt-0.5">{{ fmt.desc }}</p>
                            </button>
                        </div>
                    </div>

                    <!-- Experience level -->
                    <div>
                        <label class="block text-sm font-semibold text-surface-700 mb-2">Experience level</label>
                        <div class="flex gap-3">
                            <button
                                v-for="exp in expOptions"
                                :key="exp.value"
                                @click="experienceLevel = exp.value"
                                :class="[
                                    'flex-1 p-3 rounded-xl border-2 text-center transition-all',
                                    experienceLevel === exp.value
                                        ? 'border-primary-500 bg-primary-50'
                                        : 'border-surface-200 hover:border-primary-200'
                                ]"
                            >
                                <p :class="['text-sm font-semibold', experienceLevel === exp.value ? 'text-primary-700' : 'text-surface-700']">{{ exp.label }}</p>
                                <p class="text-[11px] text-surface-400">{{ exp.desc }}</p>
                            </button>
                        </div>
                    </div>

                    <!-- Accessibility (expandable) -->
                    <div>
                        <button @click="showAccessibility = !showAccessibility" class="flex items-center gap-2 text-sm text-surface-500 hover:text-surface-700 transition-colors">
                            <Accessibility class="w-4 h-4" />
                            <span>Accessibility preferences</span>
                            <ChevronRight :class="['w-3 h-3 transition-transform', showAccessibility ? 'rotate-90' : '']" />
                        </button>
                        <div v-if="showAccessibility" class="mt-3 p-4 bg-surface-50 rounded-xl space-y-3">
                            <label v-for="(val, key) in { dyslexia_font: 'Dyslexia-friendly font', high_contrast: 'High contrast mode', color_blind: 'Color blind-friendly', extended_time: 'Extended time for quizzes' }" :key="key" class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="accessibilityPrefs[key]" class="rounded text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm text-surface-700">{{ val }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-8">
                    <button @click="currentStep = 2" class="text-sm text-surface-400 hover:text-surface-600">← Back</button>
                    <Button @click="saveProfile" variant="primary" :disabled="saving" class="!px-6">
                        Continue <ChevronRight class="w-4 h-4 ml-1" />
                    </Button>
                </div>
            </Card>

            <!-- ═══════════ STEP 4: DIAGNOSTIC ═══════════ -->
            <Card v-if="currentStep === 4" class="p-6 sm:p-8">
                <!-- Intro screen for diagnostic -->
                <div v-if="!skipDiagnostic && diagnosticIdx === 0 && Object.keys(diagnosticAnswers).length === 0">
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-2xl bg-accent-50 flex items-center justify-center mx-auto mb-4">
                            <Brain class="w-8 h-8 text-accent-600" />
                        </div>
                        <h2 class="text-2xl font-display font-bold text-surface-900 mb-2">Quick Assessment</h2>
                        <p class="text-surface-500 mb-1">Take a 5-minute diagnostic to personalize your learning plan.</p>
                        <p class="text-xs text-surface-400 mb-6">{{ diagnosticQuestions.length }} questions across key domains</p>

                        <div class="space-y-3">
                            <Button @click="diagnosticIdx = 0" variant="primary" class="w-full !py-3" :disabled="diagnosticQuestions.length === 0">
                                <Brain class="w-4 h-4 mr-2" /> Take Assessment
                            </Button>
                            <button @click="skipDiagnostic = true; currentStep = 5" class="w-full text-sm text-surface-400 hover:text-surface-600 py-2">
                                Skip — use my experience level instead
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Diagnostic questions -->
                <div v-else-if="!skipDiagnostic && currentDQ">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-xs font-medium text-surface-400">Question {{ diagnosticIdx + 1 }} of {{ diagnosticQuestions.length }}</span>
                        <span class="text-xs font-medium px-2 py-0.5 bg-accent-50 text-accent-600 rounded-full">{{ currentDQ.domain }}</span>
                    </div>
                    <div class="bg-surface-100 rounded-full h-1.5 mb-6">
                        <div class="bg-accent-500 h-full rounded-full transition-all duration-300" :style="{ width: `${diagnosticProgress}%` }" />
                    </div>

                    <p class="text-base font-medium text-surface-800 mb-5 leading-relaxed">{{ currentDQ.stem }}</p>

                    <div class="space-y-2">
                        <button
                            v-for="opt in currentDQ.options"
                            :key="opt.index"
                        @click="selectDiagnosticAnswer(opt.id)"
                        :class="[
                            'w-full p-4 rounded-xl border-2 text-left transition-all text-sm',
                            diagnosticAnswers[currentDQ.id] === opt.id
                                ? 'border-accent-500 bg-accent-50 text-accent-800'
                                : 'border-surface-200 text-surface-700 hover:border-accent-200 hover:bg-surface-50'
                        ]"
                    >
                        <span class="font-medium">{{ opt.id }}.</span> {{ opt.text }}
                    </button>
                    </div>

                    <div class="flex justify-between items-center mt-6">
                        <button v-if="diagnosticIdx > 0" @click="prevDiagnosticQuestion" class="text-sm text-surface-400 hover:text-surface-600">← Previous</button>
                        <span v-else />
                        <Button
                            v-if="diagnosticIdx < diagnosticQuestions.length - 1"
                            @click="nextDiagnosticQuestion"
                            variant="primary"
                            :disabled="diagnosticAnswers[currentDQ.id] === undefined"
                        >
                            Next <ChevronRight class="w-4 h-4 ml-1" />
                        </Button>
                        <Button
                            v-else
                            @click="submitDiagnostic"
                            variant="primary"
                            :disabled="diagnosticAnswers[currentDQ.id] === undefined || saving"
                        >
                            Submit <CheckCircle class="w-4 h-4 ml-1" />
                        </Button>
                    </div>
                </div>

                <!-- No questions available -->
                <div v-else-if="diagnosticQuestions.length === 0" class="text-center">
                    <p class="text-surface-500 mb-4">No diagnostic questions available at the moment.</p>
                    <Button @click="currentStep = 5" variant="primary">Continue</Button>
                </div>
            </Card>

            <!-- ═══════════ STEP 5: QUICK WIN / COMPLETION ═══════════ -->
            <div v-if="currentStep === 5">
                <!-- Diagnostic result summary (if they took it) -->
                <Card v-if="diagnosticResult" class="p-6 sm:p-8 mb-6">
                    <div class="text-center mb-4">
                        <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3', diagnosticResult.score >= 70 ? 'bg-success-100' : diagnosticResult.score >= 40 ? 'bg-warning-100' : 'bg-accent-100']">
                            <span class="text-2xl font-display font-bold" :class="diagnosticResult.score >= 70 ? 'text-success-600' : diagnosticResult.score >= 40 ? 'text-warning-600' : 'text-accent-600'">
                                {{ diagnosticResult.score }}%
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-surface-800">Assessment Complete!</h3>
                        <p class="text-sm text-surface-500">You got {{ diagnosticResult.correct }}/{{ diagnosticResult.total }} correct. We've used this to personalize your plan.</p>
                    </div>
                </Card>

                <!-- Celebration / completion -->
                <Card class="p-8 sm:p-10 text-center overflow-hidden relative">
                    <!-- Celebration animation -->
                    <div v-if="quickWinDone" class="absolute inset-0 pointer-events-none">
                        <div class="absolute top-4 left-8 text-3xl animate-bounce" style="animation-delay: 0.1s">🎉</div>
                        <div class="absolute top-6 right-12 text-2xl animate-bounce" style="animation-delay: 0.3s">⭐</div>
                        <div class="absolute bottom-8 left-1/4 text-2xl animate-bounce" style="animation-delay: 0.5s">🚀</div>
                        <div class="absolute bottom-12 right-1/4 text-3xl animate-bounce" style="animation-delay: 0.2s">🏆</div>
                    </div>

                    <div class="relative z-10">
                        <div :class="['w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-5 transition-all duration-500', quickWinDone ? 'bg-success-100 scale-110' : 'bg-primary-100']">
                            <Sparkles :class="['w-10 h-10 transition-all', quickWinDone ? 'text-success-600' : 'text-primary-600']" />
                        </div>

                        <h2 class="text-2xl sm:text-3xl font-display font-bold text-surface-900 mb-3">
                            {{ quickWinDone ? "You're on your way! 🎉" : "You're all set!" }}
                        </h2>
                        <p class="text-surface-500 mb-2 max-w-sm mx-auto">
                            {{ quickWinDone
                                ? 'Welcome to JK Michaels! Redirecting to your dashboard...'
                                : 'Your personalized learning plan is ready. Let\'s begin your journey!'
                            }}
                        </p>

                        <!-- XP award badge -->
                        <div v-if="xpAwarded" class="inline-flex items-center gap-2 bg-xp-50 text-xp-700 px-4 py-2 rounded-full text-sm font-bold mt-3 animate-pulse">
                            <Zap class="w-4 h-4" /> +25 XP Earned!
                        </div>

                        <div v-if="!quickWinDone" class="mt-8">
                            <Button @click="finishOnboarding" variant="primary" class="!px-8 !py-3 !text-base" :disabled="saving">
                                <Zap class="w-5 h-5 mr-2" /> Launch My Dashboard
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>

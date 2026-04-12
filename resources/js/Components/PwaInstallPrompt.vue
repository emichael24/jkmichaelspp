<template>
    <!-- Floating Install Banner -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div v-if="showBanner" class="fixed bottom-4 left-4 right-4 z-[60] sm:left-auto sm:right-6 sm:max-w-sm">
            <div class="bg-white rounded-2xl shadow-2xl border border-surface-200/60 overflow-hidden">
                <!-- Top accent bar -->
                <div class="h-1 bg-gradient-to-r from-primary-500 to-primary-600"></div>

                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <img src="/images/logo-icon.png" alt="JK Michaels" class="w-12 h-12 rounded-xl object-contain shrink-0" />
                        <div class="min-w-0 flex-1">
                            <h3 class="font-display font-bold text-surface-900 text-sm">Install JK Michaels</h3>
                            <p v-if="isIos" class="text-xs text-surface-500 mt-0.5 leading-relaxed">
                                Tap the <strong>Share</strong> button, then <strong>"Add to Home Screen"</strong> to install.
                            </p>
                            <p v-else class="text-xs text-surface-500 mt-0.5 leading-relaxed">
                                Get quick access to your courses, tasks, and progress. Works offline too!
                            </p>
                        </div>
                        <button @click="dismiss" class="p-1 text-surface-400 hover:text-surface-600 shrink-0" aria-label="Dismiss">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mt-3">
                        <button
                            @click="handleInstall"
                            class="flex-1 px-4 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 active:bg-primary-800 transition-colors flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {{ isIos ? 'Got it' : 'Install App' }}
                        </button>
                        <button
                            @click="dismiss"
                            class="px-4 py-2.5 text-surface-600 hover:bg-surface-100 rounded-xl text-sm font-medium transition-colors"
                        >
                            Not now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { setupInstallPrompt, installApp } from '@/pwa';

const showBanner = ref(false);
const canInstall = ref(false);
const isIos = ref(false);

// Don't show again for 7 days after dismissal
const DISMISS_KEY = 'jkm-pwa-dismiss';
const DISMISS_DAYS = 7;

function isDismissed() {
    const dismissed = localStorage.getItem(DISMISS_KEY);
    if (!dismissed) return false;
    const dismissDate = new Date(parseInt(dismissed));
    const now = new Date();
    const daysDiff = (now - dismissDate) / (1000 * 60 * 60 * 24);
    return daysDiff < DISMISS_DAYS;
}

function dismiss() {
    showBanner.value = false;
    localStorage.setItem(DISMISS_KEY, Date.now().toString());
}

async function handleInstall() {
    if (isIos.value) {
        // iOS doesn't support beforeinstallprompt, just dismiss
        dismiss();
        return;
    }
    const accepted = await installApp();
    if (accepted) {
        showBanner.value = false;
    }
}

function detectIos() {
    const ua = window.navigator.userAgent;
    return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

onMounted(() => {
    // Check if already installed (standalone mode)
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (isStandalone) return;
    if (isDismissed()) return;

    // Detect iOS for fallback prompt
    if (detectIos()) {
        isIos.value = true;
        setTimeout(() => {
            showBanner.value = true;
        }, 3000);
        return;
    }

    setupInstallPrompt((installable) => {
        canInstall.value = installable;
        if (installable) {
            // Show banner after a short delay so it doesn't feel intrusive
            setTimeout(() => {
                showBanner.value = true;
            }, 3000);
        }
    });
});
</script>

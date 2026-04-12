<template>
    <!-- More Menu Bottom Sheet -->
    <Teleport to="body">
        <!-- Backdrop -->
        <transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[60]"
                @click="$emit('close')"
            />
        </transition>

        <!-- Sheet -->
        <transition name="slide-up">
            <div
                v-if="open"
                class="fixed bottom-0 left-0 right-0 z-[61] bg-white rounded-t-2xl max-h-[75vh] flex flex-col safe-bottom"
            >
                <!-- Handle bar -->
                <div class="flex justify-center pt-3 pb-1">
                    <div class="w-10 h-1 rounded-full bg-surface-300" />
                </div>

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b border-surface-100">
                    <h3 class="text-base font-semibold text-surface-900">More</h3>
                    <button @click="$emit('close')" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Menu items -->
                <nav class="flex-1 overflow-y-auto px-4 py-3">
                    <div class="grid grid-cols-3 gap-2">
                        <Link
                            v-for="item in items"
                            :key="item.label"
                            :href="item.href"
                            @click="$emit('close')"
                            :class="[
                                'flex flex-col items-center gap-1.5 p-3 rounded-xl transition-colors',
                                item.active
                                    ? 'bg-primary-50 text-primary-700'
                                    : 'text-surface-600 hover:bg-surface-50 active:bg-surface-100',
                            ]"
                        >
                            <div :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center',
                                item.active
                                    ? 'bg-primary-100'
                                    : 'bg-surface-100',
                            ]">
                                <component
                                    :is="item.icon"
                                    :class="['w-5 h-5', item.active ? 'text-primary-600' : 'text-surface-500']"
                                />
                            </div>
                            <span :class="['text-[11px] font-medium text-center leading-tight', item.active ? 'text-primary-700' : 'text-surface-600']">
                                {{ item.label }}
                            </span>
                            <span
                                v-if="item.badge"
                                class="absolute -top-1 -right-1 w-4 h-4 bg-danger-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                            >
                                {{ item.badge > 9 ? '9+' : item.badge }}
                            </span>
                        </Link>
                    </div>
                </nav>

                <!-- Logout button -->
                <div class="px-4 py-3 border-t border-surface-100">
                    <button
                        @click="handleLogout"
                        class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-danger-50 text-danger-600 font-medium text-sm hover:bg-danger-100 active:bg-danger-200 transition-colors"
                    >
                        <LogOut class="w-4.5 h-4.5" />
                        Log Out
                    </button>
                </div>

                <!-- Safe area -->
                <div class="h-[env(safe-area-inset-bottom)]" />
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { X, LogOut } from 'lucide-vue-next';

defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
});

defineEmits(['close']);

const handleLogout = () => {
    router.post('/logout');
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-up-enter-active { transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-leave-active { transition: transform 0.2s ease-in; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }

.safe-bottom { padding-bottom: env(safe-area-inset-bottom); }
</style>

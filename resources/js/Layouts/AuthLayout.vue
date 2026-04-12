<template>
    <div class="min-h-screen flex">
        <!-- Left: Branding panel (hidden on mobile) -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-[55%] relative overflow-hidden bg-gradient-to-br from-primary-900 via-primary-800 to-primary-950">
            <!-- Decorative shapes -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl" />
            <div class="absolute bottom-0 left-0 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl" />
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-xp-500/10 rounded-full blur-3xl" />

            <!-- Grid pattern -->
            <div class="absolute inset-0 opacity-5">
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
            <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 w-full">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <img src="/images/logo-icon.png" alt="JK Michaels" class="w-11 h-11 object-contain" />
                    <div>
                        <p class="text-white font-display font-bold text-lg">JK Michaels</p>
                        <p class="text-white/50 text-xs font-medium uppercase tracking-widest">Professional Mastery</p>
                    </div>
                </div>

                <!-- Hero text -->
                <div class="max-w-md">
                    <h1 class="text-4xl xl:text-5xl font-display font-bold text-white leading-tight mb-4">
                        Master your
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-accent-300 to-accent-500">
                            professional
                        </span>
                        journey
                    </h1>
                    <p class="text-white/60 text-lg leading-relaxed">
                        Courses, certifications, mock exams, real-world internships — everything you need to grow, in one place.
                    </p>
                </div>

                <!-- Stats -->
                <div class="flex gap-8">
                    <div>
                        <p class="text-3xl font-display font-bold text-white">5K+</p>
                        <p class="text-white/40 text-sm mt-0.5">Active learners</p>
                    </div>
                    <div>
                        <p class="text-3xl font-display font-bold text-white">200+</p>
                        <p class="text-white/40 text-sm mt-0.5">Courses</p>
                    </div>
                    <div>
                        <p class="text-3xl font-display font-bold text-white">95%</p>
                        <p class="text-white/40 text-sm mt-0.5">Pass rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Form area -->
        <div class="flex-1 flex flex-col">
            <!-- Mobile logo -->
            <div class="lg:hidden flex items-center gap-3 p-6">
                <img src="/images/logo-icon.png" alt="JK Michaels" class="w-9 h-9 object-contain" />
                <div>
                    <p class="font-display font-bold text-surface-900 text-sm">JK Michaels</p>
                    <p class="text-[10px] text-primary-600 font-medium uppercase tracking-wider">Professional Mastery</p>
                </div>
            </div>

            <!-- Form container -->
            <div class="flex-1 flex items-center justify-center p-6 sm:p-8 lg:p-12">
                <div class="w-full max-w-md">
                    <slot />
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 pb-6 text-center">
                <p class="text-xs text-surface-400">
                    &copy; {{ new Date().getFullYear() }} JK Michaels Institute. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>

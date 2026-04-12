<template>
    <Head title="My Profile" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Profile header -->
        <Card>
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                <Avatar :name="user.first_name + ' ' + user.last_name" :src="user.avatar" size="2xl" />
                <div class="text-center sm:text-left flex-1">
                    <h1 class="text-2xl font-display font-bold text-surface-900">
                        {{ user.first_name }} {{ user.last_name }}
                    </h1>
                    <p class="text-surface-500 mt-1">{{ user.email }}</p>
                    <div class="flex flex-wrap items-center gap-2 mt-3 justify-center sm:justify-start">
                        <Badge v-for="role in user.roles" :key="role.name" variant="primary">
                            {{ role.name.replace('_', ' ') }}
                        </Badge>
                    </div>
                    <div class="mt-4">
                        <Link
                            href="/profile/edit"
                            class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700"
                        >
                            <Pencil class="w-4 h-4" />
                            Edit profile
                        </Link>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <Card title="Personal Information">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-surface-500">Phone</dt>
                        <dd class="text-surface-900">{{ user.phone || 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-surface-500">Timezone</dt>
                        <dd class="text-surface-900">{{ user.timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-surface-500">Language</dt>
                        <dd class="text-surface-900">{{ user.locale === 'en' ? 'English' : user.locale }}</dd>
                    </div>
                    <div v-if="user.organization">
                        <dt class="text-sm text-surface-500">Organization</dt>
                        <dd class="text-surface-900">{{ user.organization.name }}</dd>
                    </div>
                </dl>
            </Card>

            <Card title="Learning Preferences">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-surface-500">Goal</dt>
                        <dd class="text-surface-900 capitalize">{{ user.goal || 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-surface-500">Daily Study Time</dt>
                        <dd class="text-surface-900">{{ user.daily_minutes }} minutes</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-surface-500">Learning Style</dt>
                        <dd class="text-surface-900 capitalize">{{ user.learning_preference }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-surface-500">Experience Level</dt>
                        <dd class="text-surface-900 capitalize">{{ user.experience_level }}</dd>
                    </div>
                    <div v-if="user.exam_date">
                        <dt class="text-sm text-surface-500">Target Exam Date</dt>
                        <dd class="text-surface-900">{{ user.exam_date }}</dd>
                    </div>
                </dl>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Avatar from '@/Components/UI/Avatar.vue';
import Badge from '@/Components/UI/Badge.vue';
import { Pencil } from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

defineProps({
    user: Object,
});
</script>

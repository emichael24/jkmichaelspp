<template>
    <Head title="Edit Profile" />

    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Flash message -->
        <Alert v-if="$page.props.flash?.success" variant="success" dismissible>
            {{ $page.props.flash.success }}
        </Alert>

        <!-- Profile info -->
        <Card title="Personal Information" subtitle="Update your name, email, and contact details.">
            <form @submit.prevent="submitProfile" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Input v-model="profileForm.first_name" label="First name" :error="profileForm.errors.first_name" required />
                    <Input v-model="profileForm.last_name" label="Last name" :error="profileForm.errors.last_name" required />
                </div>
                <Input v-model="profileForm.phone" label="Phone" type="tel" :error="profileForm.errors.phone" />
                <Input v-model="profileForm.timezone" label="Timezone" :error="profileForm.errors.timezone" />

                <div class="flex justify-end">
                    <Button type="submit" :loading="profileForm.processing">Save changes</Button>
                </div>
            </form>
        </Card>

        <!-- Avatar -->
        <Card title="Avatar" subtitle="Upload a profile picture.">
            <form @submit.prevent="submitAvatar" class="flex items-center gap-6">
                <Avatar :name="user.first_name + ' ' + user.last_name" :src="user.avatar" size="xl" />
                <div class="flex-1">
                    <input type="file" accept="image/*" @change="avatarForm.avatar = $event.target.files[0]" class="text-sm text-surface-600" />
                    <p v-if="avatarForm.errors.avatar" class="text-sm text-danger-500 mt-1">{{ avatarForm.errors.avatar }}</p>
                </div>
                <Button type="submit" variant="secondary" :loading="avatarForm.processing" size="sm">Upload</Button>
            </form>
        </Card>

        <!-- Change password -->
        <Card title="Change Password" subtitle="Ensure your account stays secure.">
            <form @submit.prevent="submitPassword" class="space-y-5">
                <Input
                    v-model="passwordForm.current_password"
                    type="password"
                    label="Current password"
                    :error="passwordForm.errors.current_password"
                    required
                />
                <Input
                    v-model="passwordForm.password"
                    type="password"
                    label="New password"
                    :error="passwordForm.errors.password"
                    required
                    hint="Minimum 8 characters"
                />
                <Input
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    label="Confirm new password"
                    required
                />
                <div class="flex justify-end">
                    <Button type="submit" :loading="passwordForm.processing">Update password</Button>
                </div>
            </form>
        </Card>

        <!-- Learning preferences -->
        <Card title="Learning Preferences" subtitle="Customize your learning experience.">
            <form @submit.prevent="submitPreferences" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Learning Goal</label>
                    <select v-model="prefForm.goal" class="w-full rounded-xl border-surface-300 focus:border-primary-500 focus:ring-primary-500/20 text-sm">
                        <option value="">Select goal</option>
                        <option value="certification">Certification</option>
                        <option value="skill">Skill Development</option>
                        <option value="internship">Internship</option>
                    </select>
                </div>

                <Input v-model="prefForm.daily_minutes" type="number" label="Daily study time (minutes)" :min="5" :max="120" />

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Learning Style</label>
                    <select v-model="prefForm.learning_preference" class="w-full rounded-xl border-surface-300 focus:border-primary-500 focus:ring-primary-500/20 text-sm">
                        <option value="read">Read</option>
                        <option value="watch">Watch</option>
                        <option value="listen">Listen</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Experience Level</label>
                    <select v-model="prefForm.experience_level" class="w-full rounded-xl border-surface-300 focus:border-primary-500 focus:ring-primary-500/20 text-sm">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>

                <Input v-model="prefForm.exam_date" type="date" label="Target exam date" />

                <div class="flex justify-end">
                    <Button type="submit" :loading="prefForm.processing">Save preferences</Button>
                </div>
            </form>
        </Card>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Avatar from '@/Components/UI/Avatar.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Alert from '@/Components/UI/Alert.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    user: Object,
});

const profileForm = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    phone: props.user.phone || '',
    timezone: props.user.timezone || 'Africa/Lagos',
});

const avatarForm = useForm({
    avatar: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const prefForm = useForm({
    goal: props.user.goal || '',
    daily_minutes: props.user.daily_minutes || 15,
    learning_preference: props.user.learning_preference || 'read',
    experience_level: props.user.experience_level || 'beginner',
    exam_date: props.user.exam_date || '',
});

const submitProfile = () => {
    profileForm.put('/profile');
};

const submitAvatar = () => {
    avatarForm.post('/profile/avatar', {
        forceFormData: true,
    });
};

const submitPassword = () => {
    passwordForm.put('/profile/password', {
        onSuccess: () => passwordForm.reset(),
    });
};

const submitPreferences = () => {
    prefForm.put('/profile/preferences');
};
</script>

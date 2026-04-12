import { defineStore } from 'pinia';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const useAuthStore = defineStore('auth', () => {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const isAuthenticated = computed(() => !!user.value);
    const roles = computed(() => user.value?.roles ?? []);
    const permissions = computed(() => user.value?.permissions ?? []);

    function hasRole(role) {
        return roles.value.includes(role);
    }

    function hasPermission(permission) {
        return permissions.value.includes(permission);
    }

    function hasAnyRole(...roleNames) {
        return roleNames.some((r) => roles.value.includes(r));
    }

    function isAdmin() {
        return hasAnyRole('platform_admin', 'org_admin');
    }

    function isFacilitator() {
        return hasRole('facilitator');
    }

    function isLearner() {
        return hasRole('learner');
    }

    return {
        user,
        isAuthenticated,
        roles,
        permissions,
        hasRole,
        hasPermission,
        hasAnyRole,
        isAdmin,
        isFacilitator,
        isLearner,
    };
});

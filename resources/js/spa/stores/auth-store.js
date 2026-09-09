import { defineStore } from 'pinia';
import { apiFetch } from '../api-client';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('auth_token') || null,
        user: null,
        permissions: [],
        ready: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        async login(email, password) {
            const data = await apiFetch('/login', {
                method: 'POST',
                body: JSON.stringify({ email, password }),
            });

            this.setSession(data.token, data.user);
        },

        async fetchMe() {
            const user = await apiFetch('/me', {}, this.token);
            this.user = user;
            this.permissions = user.permissions || [];
            this.ready = true;
        },

        async logout() {
            try {
                if (this.token) {
                    await apiFetch('/logout', { method: 'POST' }, this.token);
                }
            } finally {
                this.clearSession();
            }
        },

        setSession(token, user) {
            this.token = token;
            this.user = user;
            this.permissions = user?.permissions || [];
            this.ready = true;
            localStorage.setItem('auth_token', token);
        },

        clearSession() {
            this.token = null;
            this.user = null;
            this.permissions = [];
            this.ready = false;
            localStorage.removeItem('auth_token');
        },

        isModulePermitted(module) {
            if (!module) return true;
            if (!this.permissions.length) return false;

            return this.permissions.some((p) => p.startsWith(`${module}.`));
        },
    },
});

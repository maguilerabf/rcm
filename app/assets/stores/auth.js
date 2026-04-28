import { defineStore } from 'pinia';
import api from '../api/client';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        error: null,
    }),
    getters: {
        isAuthenticated: (state) => !!state.user,
        displayName: (state) => state.user?.displayName
            || [state.user?.firstName, state.user?.lastName].filter(Boolean).join(' ')
            || state.user?.email
            || '',
    },
    actions: {
        async fetchMe() {
            try {
                const { data } = await api.get('/me', { _silent: true });
                this.user = data.user;
            } catch (e) {
                this.user = null;
            }
        },
        async login(email, password) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.post('/login', { email, password }, { _silent: true });
                this.user = data.user;
                return true;
            } catch (e) {
                this.error = e.response?.data?.error || 'No se pudo iniciar sesión.';
                return false;
            } finally {
                this.loading = false;
            }
        },
        async register({ firstName, lastName, email, password }) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await api.post('/register', { firstName, lastName, email, password }, { _silent: true });
                this.user = data.user;
                return true;
            } catch (e) {
                this.error = e.response?.data?.error || 'No se pudo crear la cuenta.';
                return false;
            } finally {
                this.loading = false;
            }
        },
        async logout() {
            try {
                await fetch('/logout', { credentials: 'include' });
            } catch (_) { /* ignore */ }
            this.user = null;
        },
    },
});

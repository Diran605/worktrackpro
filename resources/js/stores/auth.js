import { defineStore } from 'pinia';
import api from '../lib/axios';
import { brandCss } from '../lib/brandTheme';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('access_token') || null,
        loading: false,
        error: null,
    }),
    
    getters: {
        isAuthenticated: (state) => !!state.token,
        hasRole: (state) => (role) => {
             return state.user?.roles?.includes(role) || false;
        },
        // Modules the organisation has (work_tracking, attendance, letters, knowledge, docs_library).
        hasModule: (state) => (module) => {
            return (state.user?.modules || []).includes(module);
        }
    },
    
    actions: {
        async login(email, password) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.post('/auth/login', { email, password });
                
                this.token = response.data.access_token;
                this.user = response.data.user;
                
                // Persist the token
                localStorage.setItem('access_token', this.token);
                this.applyThemeOverrides();
                
                return true; // Used by UI to trigger redirect
            } catch (err) {
                this.error = err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Login failed. Please try again.';
                return false;
            } finally {
                this.loading = false;
            }
        },
        
        async fetchUser() {
            if (!this.token) return;
            
            try {
                const response = await api.get('/auth/me');
                this.user = response.data.data;
                this.applyThemeOverrides();
            } catch (err) {
                // If fetching user fails (e.g., token expired/invalid), wipe the state
                this.logout();
            }
        },
        
        async logout() {
            if (this.token) {
                // Also end the /admin session in this browser, so the next person doesn't inherit it.
                let adminLogoutUrl = null;
                try {
                    adminLogoutUrl = (await api.post('/auth/admin-logout-link')).data.url;
                } catch (e) {
                    // Not critical
                }

                try {
                    await api.post('/auth/logout');
                } catch (e) {
                    // Ignore errors on logout, we clear local state anyway
                }

                if (adminLogoutUrl) {
                    await fetch(adminLogoutUrl, { credentials: 'same-origin' }).catch(() => {});
                }
            }
            
            this.user = null;
            this.token = null;
            localStorage.removeItem('access_token');
            this.removeThemeOverrides();
        },
        
        applyThemeOverrides() {
            const org = this.user?.organisation;
            const css = org ? brandCss(org.primary_color, org.secondary_color) : '';
            if (!css) return;

            let style = document.getElementById('org-theme-overrides');
            if (!style) {
                style = document.createElement('style');
                style.id = 'org-theme-overrides';
                document.head.appendChild(style);
            }
            style.textContent = css;
        },

        removeThemeOverrides() {
            const style = document.getElementById('org-theme-overrides');
            if (style) {
                style.remove();
            }
        }
    }
});

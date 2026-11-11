import { defineStore } from 'pinia';
import api from '../lib/axios';

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
                try {
                    await api.post('/auth/logout');
                } catch (e) {
                    // Ignore errors on logout, we clear local state anyway
                }
            }
            
            this.user = null;
            this.token = null;
            localStorage.removeItem('access_token');
            this.removeThemeOverrides();
        },
        
        applyThemeOverrides() {
            if (!this.user?.organisation) return;
            
            const org = this.user.organisation;
            if (org.primary_color || org.secondary_color) {
                let style = document.getElementById('org-theme-overrides');
                if (!style) {
                    style = document.createElement('style');
                    style.id = 'org-theme-overrides';
                    document.head.appendChild(style);
                }
                
                let css = ':root {\n';
                if (org.primary_color) {
                    css += `  --color-teal-400: ${org.primary_color} !important;\n`;
                    css += `  --color-teal-500: ${org.primary_color} !important;\n`;
                    css += `  --color-teal-600: ${org.primary_color} !important;\n`;
                    css += `  --color-teal-700: ${org.primary_color} !important;\n`;
                }
                if (org.secondary_color) {
                    css += `  --color-indigo-400: ${org.secondary_color} !important;\n`;
                    css += `  --color-indigo-500: ${org.secondary_color} !important;\n`;
                    css += `  --color-indigo-600: ${org.secondary_color} !important;\n`;
                    css += `  --color-indigo-700: ${org.secondary_color} !important;\n`;
                }
                css += '}\n';
                
                if (org.primary_color) {
                    css += `.bg-teal-600, .bg-teal-500 { background-color: ${org.primary_color} !important; }\n`;
                    css += `.text-teal-600, .text-teal-500 { color: ${org.primary_color} !important; }\n`;
                    css += `.ring-teal-500 { --tw-ring-color: ${org.primary_color} !important; }\n`;
                    css += `.border-teal-500, .border-teal-600 { border-color: ${org.primary_color} !important; }\n`;
                }
                if (org.secondary_color) {
                    css += `.bg-indigo-600, .bg-indigo-500 { background-color: ${org.secondary_color} !important; }\n`;
                    css += `.text-indigo-600, .text-indigo-500 { color: ${org.secondary_color} !important; }\n`;
                }
                
                style.innerHTML = css;
            }
        },
        
        removeThemeOverrides() {
            const style = document.getElementById('org-theme-overrides');
            if (style) {
                style.remove();
            }
        }
    }
});

import { defineStore } from 'pinia';
import api from '../lib/axios';

export const useSessionStore = defineStore('session', {
    state: () => ({
        currentSession: null,
        loading: false,
        error: null,
        carryOvers: [],
    }),

    getters: {
        isClockedIn: (state) => state.currentSession?.status === 'active',
        isSystemClosed: (state) => state.currentSession?.status === 'system_closed',
    },

    actions: {
        async fetchCurrentSession() {
            this.loading = true;
            this.error = null;
            try {
                const res = await api.get('/sessions/current');
                this.currentSession = res.data.data;
            } catch (err) {
                this.error = err.response?.data?.message || 'Failed to fetch session.';
            } finally {
                this.loading = false;
            }
        },

        async clockIn() {
            this.loading = true;
            this.error = null;
            try {
                const res = await api.post('/sessions/clock-in');
                this.currentSession = res.data.data;
                return true;
            } catch (err) {
                this.error = err.response?.data?.message || err.response?.data?.errors?.session?.[0] || 'Clock-in failed.';
                return false;
            } finally {
                this.loading = false;
            }
        },

        async clockOut() {
            this.loading = true;
            this.error = null;
            this.carryOvers = [];
            try {
                const res = await api.post('/sessions/clock-out');
                this.currentSession = res.data.data;
                return true;
            } catch (err) {
                if (err.response?.status === 422 && Array.isArray(err.response?.data?.carry_overs)) {
                    this.carryOvers = err.response.data.carry_overs;
                    this.error = null;
                    return false;
                }
                this.error = err.response?.data?.message || err.response?.data?.errors?.session?.[0] || 'Clock-out failed.';
                return false;
            } finally {
                this.loading = false;
            }
        },

        async fetchPendingCarryOvers() {
            try {
                const res = await api.get('/carry-overs/pending');
                this.carryOvers = res.data.data || [];
            } catch (err) {
                // ignore
            }
        },

        async resolveCarryOver(planId, decision, priority = null) {
            const payload = { decision };
            if (priority) payload.priority = priority;
            const res = await api.post(`/carry-overs/${planId}/resolve`, payload);
            return res.data;
        },

        async requestReopen(reason) {
            if (!this.currentSession?.id) return false;
            this.loading = true;
            this.error = null;
            try {
                await api.post(`/sessions/${this.currentSession.id}/request-reopen`, { reason });
                return true;
            } catch (err) {
                this.error = err.response?.data?.message || 'Reopen request failed.';
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});


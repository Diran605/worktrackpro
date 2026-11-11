import { defineStore } from 'pinia';
import api from '../lib/axios';

export const useInboxStore = defineStore('inbox', {
    state: () => ({
        messages: [],
        unreadCount: 0,
        loading: false,
        error: null,
        selected: null,
    }),

    actions: {
        async fetchUnreadCount() {
            try {
                const res = await api.get('/inbox/unread-count');
                this.unreadCount = res.data.unread_count || 0;
            } catch (e) {
                // ignore
            }
        },

        async fetchMessages() {
            this.loading = true;
            this.error = null;
            try {
                const res = await api.get('/inbox');
                this.messages = res.data.data || [];
            } catch (e) {
                this.error = 'Failed to load inbox.';
            } finally {
                this.loading = false;
            }
        },

        async openMessage(id) {
            this.loading = true;
            this.error = null;
            try {
                const res = await api.get(`/inbox/${id}`);
                this.selected = res.data.data;
                await this.fetchUnreadCount();
            } catch (e) {
                this.error = 'Failed to open message.';
            } finally {
                this.loading = false;
            }
        },

        async sendMessage({ recipient_id, subject, body }) {
            this.loading = true;
            this.error = null;
            try {
                await api.post('/inbox/send', { recipient_id, subject, body });
                await this.fetchMessages();
                await this.fetchUnreadCount();
                return true;
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to send message.';
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});


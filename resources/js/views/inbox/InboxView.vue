<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-[calc(100vh-12rem)]">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <h1 class="text-lg font-bold text-gray-900">Inbox</h1>
                <div class="flex items-center gap-2">
                    <button @click="refresh" class="p-2 text-gray-400 hover:text-teal-600 transition-colors" title="Refresh">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </button>
                    <button @click="showCompose = true" class="px-3 py-1.5 bg-teal-600 text-white text-xs font-bold rounded-lg hover:bg-teal-700 transition-colors shadow-sm">
                        Compose
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div v-if="inbox.loading && !inbox.messages.length" class="p-8 text-center">
                    <div class="inline-block animate-spin w-6 h-6 border-2 border-teal-600 border-t-transparent rounded-full mb-2"></div>
                    <div class="text-sm text-gray-400">Loading your messages...</div>
                </div>
                <div v-else-if="!inbox.messages.length" class="p-12 text-center text-sm text-gray-400">
                    No messages yet.
                </div>

                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="m in inbox.messages" :key="m.id"
                        class="p-4 hover:bg-teal-50/30 cursor-pointer transition-colors border-l-4"
                        :class="[m.id === inbox.selected?.id ? 'bg-teal-50/50 border-teal-600' : 'border-transparent']"
                        @click="inbox.openMessage(m.id)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-bold truncate" :class="m.recipient_read_at ? 'text-gray-600' : 'text-gray-900'">
                                    {{ m.subject }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1 flex items-center gap-1.5">
                                    <span class="truncate">{{ m.sender?.name || 'System' }}</span>
                                    <span class="text-gray-300">•</span>
                                    <span class="whitespace-nowrap">{{ formatTime(m.created_at) }}</span>
                                </div>
                            </div>
                            <div v-if="!m.recipient_read_at" class="w-2 h-2 rounded-full bg-teal-500 mt-1.5 shrink-0"></div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-[calc(100vh-12rem)]">
            <div v-if="!inbox.selected" class="flex-1 flex flex-col items-center justify-center p-12 text-center bg-gray-50/30">
                <div class="w-16 h-16 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center text-gray-300 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                </div>
                <h3 class="text-gray-900 font-bold">Select a message</h3>
                <p class="text-sm text-gray-500 mt-1 mx-auto max-w-xs">Choose a message from the list on the left to read its content.</p>
            </div>

            <template v-else>
                <div class="p-6 border-b border-gray-100 flex items-start justify-between bg-white sticky top-0 z-10">
                    <div class="min-w-0">
                        <div class="text-2xl font-black text-gray-900 leading-tight mb-2">{{ inbox.selected.subject }}</div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-xs font-bold uppercase">
                                    {{ (inbox.selected.sender?.name || 'S')[0] }}
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-gray-900">{{ inbox.selected.sender?.name || 'System' }}</div>
                                    <div class="text-xs text-gray-500">{{ new Date(inbox.selected.created_at).toLocaleString() }}</div>
                                </div>
                            </div>
                            <button v-if="inbox.selected.sender_id" @click="startReply" class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-teal-600 hover:bg-teal-50 rounded-xl transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                Reply
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <div v-if="inbox.loading" class="animate-pulse space-y-4">
                        <div class="h-4 bg-gray-100 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-100 rounded w-full"></div>
                        <div class="h-4 bg-gray-100 rounded w-2/3"></div>
                    </div>
                    <div v-else class="prose prose-teal max-w-none">
                        <pre class="whitespace-pre-wrap font-sans text-sm text-gray-700 leading-relaxed">{{ inbox.selected.body }}</pre>
                    </div>

                    <div v-if="inbox.selected.attachments?.length" class="mt-8 border-t border-gray-100 pt-6">
                        <div class="text-xs font-black uppercase tracking-wider text-gray-400 mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            Attachments ({{ inbox.selected.attachments.length }})
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-for="a in inbox.selected.attachments" :key="a.id" 
                                class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-200 group hover:border-teal-300 hover:bg-teal-50/30 transition-all">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-gray-400 shadow-sm">
                                        <svg v-if="a.file_type === 'application/pdf'" class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6l-4-4H9z" /><path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4h4" /></svg>
                                        <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-gray-900 truncate" :title="a.file_name">{{ a.file_name }}</div>
                                        <div class="text-[10px] text-gray-500">{{ Math.round((a.file_size || 0) / 1024) }} KB</div>
                                    </div>
                                </div>
                                <button @click="downloadAttachment(a)" class="p-2 text-gray-400 hover:text-teal-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Compose Modal -->
        <div v-if="showCompose" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <div>
                        <h2 class="text-xl font-black text-gray-900">New Message</h2>
                        <div class="flex gap-2 mt-2">
                            <button @click="prepareReopenRequest" class="text-[10px] font-bold uppercase tracking-tight px-2 py-1 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors">
                                Request Session Reopen
                            </button>
                        </div>
                    </div>
                    <button @click="showCompose = false" class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="sendMessage" class="p-6 space-y-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 ml-1">Recipient</label>
                        <select v-model="form.recipient_id" required class="block w-full bg-gray-50 border-gray-200 rounded-xl focus:ring-teal-500 focus:border-teal-500 transition-all">
                            <option value="">Select recipient...</option>
                            <option v-for="u in recipients" :key="u.id" :value="u.id">{{ u.name }} ({{ u.roles[0]?.replace('_', ' ') }})</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 ml-1">Subject</label>
                        <input v-model="form.subject" type="text" required placeholder="What is this about?" class="block w-full bg-gray-50 border-gray-200 rounded-xl focus:ring-teal-500 focus:border-teal-500 transition-all">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 ml-1">Message</label>
                        <textarea v-model="form.body" rows="6" required placeholder="Type your message here..." class="block w-full bg-gray-50 border-gray-200 rounded-xl focus:ring-teal-500 focus:border-teal-500 transition-all resize-none"></textarea>
                    </div>

                    <div v-if="inbox.error" class="p-3 bg-red-50 text-red-600 text-xs font-bold rounded-xl border border-red-100">
                        {{ inbox.error }}
                    </div>

                    <div class="pt-4 flex items-center gap-3">
                        <button type="button" @click="showCompose = false" class="flex-1 py-3 text-sm font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition-all">
                            Cancel
                        </button>
                        <button type="submit" :disabled="inbox.loading" class="flex-2 py-3 bg-teal-600 text-white text-sm font-bold rounded-xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-600/20 disabled:opacity-50 flex items-center justify-center gap-2">
                            <span v-if="inbox.loading" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            {{ inbox.loading ? 'Sending...' : 'Send Message' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useInboxStore } from '../../stores/inbox';
import api from '../../lib/axios';

const inbox = useInboxStore();
const showCompose = ref(false);
const recipients = ref([]);
const form = ref({
    recipient_id: '',
    subject: '',
    body: ''
});

const refresh = async () => {
    await inbox.fetchMessages();
    await inbox.fetchUnreadCount();
};

const formatTime = (dateStr) => {
    const d = new Date(dateStr);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const fetchRecipients = async () => {
    try {
        const res = await api.get('/team');
        // Show all users in the organisation as potential recipients
        recipients.value = res.data.data || [];
    } catch (e) {
        console.error('Failed to fetch recipients', e);
    }
};

const isReopenRequest = ref(false);

const prepareReopenRequest = () => {
    isReopenRequest.value = true;
    form.value.subject = 'Session Reopen Request';
    form.value.body = `Hello,\n\nI would like to request that my work session for ${new Date().toLocaleDateString()} be reopened.\n\nReason: `;
    
    // Auto-select first admin if none selected
    if (!form.value.recipient_id && recipients.value.length > 0) {
        form.value.recipient_id = recipients.value[0].id;
    }
};

const sendMessage = async () => {
    const success = await inbox.sendMessage(form.value);
    if (success) {
        // If this was a reopen request, also create the formal SessionReopenRequest record
        if (isReopenRequest.value) {
            try {
                await api.post('/inbox/request-reopen', {
                    reason: form.value.body
                });
            } catch (e) {
                console.warn('Reopen request record creation failed (message was still sent):', e.response?.data?.message);
            }
        }

        showCompose.value = false;
        isReopenRequest.value = false;
        form.value = { recipient_id: '', subject: '', body: '' };
    }
};

const startReply = () => {
    if (!inbox.selected || !inbox.selected.sender_id) return;
    
    const s = inbox.selected;
    form.value = {
        recipient_id: s.sender_id,
        subject: s.subject.startsWith('Re:') ? s.subject : `Re: ${s.subject}`,
        body: `\n\n--- Original Message ---\nFrom: ${s.sender?.name || 'System'}\nSent: ${new Date(s.created_at).toLocaleString()}\n\n${s.body}`
    };
    showCompose.value = true;
};

const downloadAttachment = async (a) => {
    try {
        const response = await api.get(`/inbox/attachments/${a.id}/download`, {
            responseType: 'blob'
        });
        
        const blob = new Blob([response.data], { type: a.file_type || 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        
        // Open in new tab for preview
        window.open(url, '_blank');
        
        // Cleanup URL after some time
        setTimeout(() => window.URL.revokeObjectURL(url), 10000);
    } catch (e) {
        console.error('Download failed', e);
        alert('Failed to access file.');
    }
};

onMounted(() => {
    refresh();
    fetchRecipients();
});
</script>


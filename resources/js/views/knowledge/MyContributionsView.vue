<template>
    <div class="space-y-6">
        <router-link :to="{ name: 'Knowledge' }" class="text-sm text-gray-500 hover:text-gray-800">← Knowledge</router-link>

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">My contributions</h1>
                <p class="text-sm text-gray-500 mt-1">Your drafts and the changes you've sent for approval.</p>
            </div>
            <select v-model="status" @change="load(1)" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All</option>
                <option value="draft">Drafts</option>
                <option value="submitted">Awaiting approval</option>
                <option value="changes_requested">Changes requested</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div v-if="loading" class="p-6 text-sm text-gray-500">Loading…</div>
            <div v-else-if="!changes.length" class="p-8 text-sm text-gray-500 text-center">Nothing here yet.</div>

            <ul v-else class="divide-y divide-gray-100">
                <li v-for="c in changes" :key="c.id" class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-900 truncate">{{ c.title }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            {{ c.system?.name }} · {{ c.type_label }} · updated {{ formatDate(c.updated_at) }}
                        </div>
                        <div v-if="c.review_comment && c.status !== 'approved'" class="text-xs text-amber-800 bg-amber-50 rounded-lg px-2 py-1 mt-2 whitespace-pre-line">
                            {{ c.reviewer ? c.reviewer + ': ' : '' }}{{ c.review_comment }}
                        </div>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full self-start sm:self-center" :class="statusClass(c.status)">{{ c.status_label }}</span>
                    <div class="flex gap-2 shrink-0">
                        <router-link v-if="c.editable && c.type !== 'import'"
                                     :to="{ name: 'ManualEditor', params: { slug: c.system.slug }, query: { change: c.id } }"
                                     class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700">
                            {{ c.status === 'changes_requested' ? 'Update' : 'Continue' }}
                        </router-link>
                        <button v-else-if="c.editable && c.type === 'import'" @click="submitImport(c)"
                                class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700">
                            Submit
                        </button>
                        <router-link v-else-if="c.status === 'approved' && c.section"
                                     :to="{ name: 'ManualReader', params: { slug: c.system.slug, sectionId: c.section.id } }"
                                     class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                            View
                        </router-link>
                    </div>
                </li>
            </ul>
        </div>

        <div v-if="meta.last_page > 1" class="flex justify-center gap-2">
            <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="px-3 py-1.5 rounded-lg text-sm bg-white border border-gray-200 disabled:opacity-40">Previous</button>
            <span class="px-3 py-1.5 text-sm text-gray-600">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="px-3 py-1.5 rounded-lg text-sm bg-white border border-gray-200 disabled:opacity-40">Next</button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/axios';

const changes = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const loading = ref(true);
const status = ref('');

const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' }) : '';

const statusClass = (s) => ({
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-800',
    changes_requested: 'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700');

const load = async (page = 1) => {
    loading.value = true;
    try {
        const res = await api.get('/manual-changes', { params: { page, status: status.value || undefined } });
        changes.value = res.data.data;
        meta.value = res.data.meta;
    } finally {
        loading.value = false;
    }
};

const submitImport = async (c) => {
    try {
        await api.post(`/manual-changes/${c.id}/submit`);
        load(meta.value.current_page);
    } catch (e) {
        alert(e.response?.data?.message || 'Could not submit.');
    }
};

onMounted(() => load());
</script>

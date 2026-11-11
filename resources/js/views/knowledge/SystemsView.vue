<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Knowledge</h1>
                <p class="text-sm text-gray-500 mt-1">Every internal system: what it does, who owns it, who to call when it breaks, and its manual.</p>
            </div>
            <router-link :to="{ name: 'MyContributions' }"
                         class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                My contributions
            </router-link>
        </div>

        <input v-model="search" type="search" placeholder="Search systems…"
               class="w-full sm:max-w-sm px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-teal-500 focus:border-teal-500">

        <div v-if="loading" class="text-sm text-gray-500">Loading systems…</div>
        <div v-else-if="error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-4">{{ error }}</div>
        <div v-else-if="!filtered.length" class="text-sm text-gray-500 bg-white border border-gray-100 rounded-2xl p-8 text-center">
            {{ systems.length ? 'No systems match your search.' : 'No systems have been added yet. An admin can add them in the Command Center under Knowledge > Systems.' }}
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <router-link v-for="s in filtered" :key="s.id" :to="{ name: 'SystemDetail', params: { slug: s.slug } }"
                         class="block bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-teal-200 transition-all">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-bold text-gray-900">{{ s.name }}</h2>
                    <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full shrink-0" :class="criticalityClass(s.criticality)">
                        {{ s.criticality }}
                    </span>
                </div>
                <p class="text-sm text-gray-600 mt-1.5 line-clamp-2 min-h-[2.5rem]">{{ s.summary || 'No summary yet.' }}</p>
                <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                    <span>Owner: <strong class="text-gray-700">{{ ownerNames(s) || '—' }}</strong></span>
                    <span>{{ s.sections_count || 0 }} section{{ s.sections_count === 1 ? '' : 's' }} · v{{ s.manual_version }}</span>
                </div>
                <div v-if="s.status !== 'active'" class="mt-2 text-xs font-semibold text-amber-700">{{ s.status === 'deprecated' ? 'Deprecated' : s.status }}</div>
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';

const systems = ref([]);
const loading = ref(true);
const error = ref(null);
const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return systems.value;
    return systems.value.filter(s => s.name.toLowerCase().includes(term) || (s.summary || '').toLowerCase().includes(term));
});

const ownerNames = (s) => (s.people || []).filter(p => p.role === 'owner').map(p => p.name).join(', ');

const criticalityClass = (c) => ({
    critical: 'bg-red-100 text-red-700',
    high: 'bg-amber-100 text-amber-700',
    medium: 'bg-blue-50 text-blue-700',
    low: 'bg-gray-100 text-gray-600',
}[c] || 'bg-gray-100 text-gray-600');

onMounted(async () => {
    try {
        const res = await api.get('/systems');
        systems.value = res.data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load systems.';
    } finally {
        loading.value = false;
    }
});
</script>

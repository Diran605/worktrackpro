<template>
    <div class="space-y-6">
        <router-link :to="{ name: 'Knowledge' }" class="text-sm text-gray-500 hover:text-gray-800">← All systems</router-link>

        <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
        <div v-else-if="error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-4">{{ error }}</div>

        <template v-else>
            <!-- Header -->
            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-bold text-gray-900">{{ system.name }}</h1>
                            <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ system.criticality }}</span>
                            <span v-if="system.status !== 'active'" class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">{{ system.status }}</span>
                        </div>
                        <p class="text-gray-600 mt-1">{{ system.summary }}</p>
                        <a v-if="system.url" :href="system.url" target="_blank" rel="noopener" class="text-sm text-teal-700 underline mt-1 inline-block">Open {{ system.name }} ↗</a>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button @click="tab = 'overview'" :class="tabClass('overview')">Overview</button>
                        <button @click="tab = 'manual'" :class="tabClass('manual')">Manual</button>
                    </div>
                </div>
            </div>

            <!-- Overview -->
            <div v-if="tab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <h2 class="font-bold text-gray-900 mb-3">What it does</h2>
                    <!-- Description is sanitised on the server before it is stored -->
                    <div v-if="system.description" class="kb-content" v-html="system.description"></div>
                    <p v-else class="text-sm text-gray-500">No description yet.</p>
                </div>

                <div class="space-y-6">
                    <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-bold text-gray-900 mb-3">When it breaks</h2>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ system.support_contact || 'No support contact recorded.' }}</p>
                        <div v-if="system.escalation_notes" class="mt-3 pt-3 border-t border-gray-100">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Escalation</div>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ system.escalation_notes }}</p>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-bold text-gray-900 mb-3">People</h2>
                        <ul v-if="system.people?.length" class="space-y-2">
                            <li v-for="p in system.people" :key="p.id + p.role" class="flex items-center justify-between text-sm">
                                <span class="text-gray-800">{{ p.name }}</span>
                                <span class="text-xs text-gray-500">{{ p.role_label }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-gray-500">No owner assigned yet.</p>
                        <p v-if="system.department" class="text-xs text-gray-500 mt-3">Department: {{ system.department.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Manual -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <h2 class="font-bold text-gray-900">Contents <span class="text-gray-400 font-normal text-sm">· v{{ manual.manual_version }}</span></h2>
                        <input v-model="searchTerm" @input="runSearch" type="search" placeholder="Search this manual…"
                               class="w-48 sm:w-64 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500">
                    </div>

                    <div v-if="searchTerm.trim().length >= 2">
                        <p v-if="!searchResults.length" class="text-sm text-gray-500">No matches.</p>
                        <router-link v-for="r in searchResults" :key="r.id" :to="{ name: 'ManualReader', params: { slug, sectionId: r.id } }"
                                     class="block p-3 rounded-lg hover:bg-gray-50 border-b border-gray-50">
                            <div class="text-sm font-semibold text-gray-900">{{ r.number }} {{ r.title }}</div>
                            <div v-if="r.excerpt" class="text-xs text-gray-500 mt-0.5">{{ r.excerpt }}</div>
                        </router-link>
                    </div>
                    <template v-else>
                        <ManualTree v-if="manual.tree.length" :nodes="manual.tree" :slug="slug" />
                        <div v-else class="text-sm text-gray-500 py-6 text-center">
                            This manual is empty.
                            <span v-if="manual.can.contribute">Start it with a few screenshots and notes — it can grow from there.</span>
                        </div>
                    </template>
                </div>

                <div class="space-y-6">
                    <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-bold text-gray-900 mb-2">Your reading</h2>
                        <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                            <span>{{ manual.progress.read }} of {{ manual.progress.total }} sections</span>
                            <span class="font-semibold text-gray-900">{{ manual.progress.percent }}%</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-teal-500 rounded-full transition-all" :style="{ width: manual.progress.percent + '%' }"></div>
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <router-link v-if="firstUnread" :to="{ name: 'ManualReader', params: { slug, sectionId: firstUnread.id } }"
                                         class="text-center px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700">
                                {{ manual.progress.read ? 'Continue reading' : 'Start reading' }}
                            </router-link>
                            <button v-if="manual.tree.length" @click="showExport = true"
                                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                                ⬇ Download PDF
                            </button>
                            <button @click="trackStudy" :disabled="tracking"
                                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                                ⏱ Track study time
                            </button>
                            <p v-if="trackMessage" class="text-xs" :class="trackError ? 'text-red-600' : 'text-green-700'">{{ trackMessage }}</p>
                        </div>
                    </div>

                    <div v-if="manual.library" class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-bold text-gray-900 mb-1">Documentation library</h2>
                        <p class="text-sm text-gray-600">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="{
                                      'bg-green-50 text-green-700': manual.library.visibility === 'public',
                                      'bg-amber-50 text-amber-700': manual.library.visibility === 'restricted',
                                      'bg-gray-100 text-gray-600': manual.library.visibility === 'hidden',
                                  }">{{ manual.library.visibility_label }}</span>
                        </p>
                        <p class="text-xs text-gray-500 mt-2">
                            <template v-if="manual.library.visibility === 'public'">Anyone can read this manual in the library. Chapters marked 🔒 need staff sign-in or an access code.</template>
                            <template v-else-if="manual.library.visibility === 'restricted'">Listed in the library, but only staff and readers with an access code can open it.</template>
                            <template v-else>Only staff can read this manual, here in the portal.</template>
                        </p>
                        <p v-if="manual.unreleased_changes" class="text-xs text-amber-700 mt-2">
                            {{ manual.unreleased_changes }} approved {{ manual.unreleased_changes === 1 ? 'change is' : 'changes are' }} live but not in a version yet. Publish a version from the admin panel (Knowledge › Systems › Publish version).
                        </p>
                        <button v-if="manual.library.path" @click="openInLibrary(manual.library.path)"
                                class="mt-3 w-full px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                            🌐 Open in library
                        </button>
                    </div>

                    <div v-if="manual.can.contribute" class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-bold text-gray-900 mb-1">Contribute</h2>
                        <p class="text-xs text-gray-500 mb-3">
                            {{ manual.can.approve ? 'You own this system, so your changes go live straight away. Group them into a version when you are ready.' : 'Changes go to the system owner for approval before they are published.' }}
                        </p>
                        <div class="flex flex-col gap-2">
                            <router-link :to="{ name: 'ManualEditor', params: { slug } }"
                                         class="text-center px-4 py-2 rounded-xl text-sm font-semibold bg-gray-900 text-white hover:bg-gray-800">
                                ✍ Write a new section
                            </router-link>
                            <router-link :to="{ name: 'ManualUpload', params: { slug } }"
                                         class="text-center px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                                ⬆ Upload a PDF or Word manual
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <ExportManualDialog v-if="showExport" :slug="slug" :tree="manual.tree" @close="showExport = false" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/axios';
import ManualTree from '../../components/knowledge/ManualTree.vue';
import ExportManualDialog from '../../components/knowledge/ExportManualDialog.vue';
import { openInLibrary } from '../../lib/docsLink';

const route = useRoute();
const router = useRouter();
const slug = computed(() => route.params.slug);

const system = ref(null);
const manual = ref({ tree: [], progress: { read: 0, total: 0, percent: 0 }, can: {}, manual_version: '0.0' });
const loading = ref(true);
const error = ref(null);
const tab = ref(route.query.tab === 'manual' ? 'manual' : 'overview');

const searchTerm = ref('');
const searchResults = ref([]);
let searchTimer = null;

const tracking = ref(false);
const showExport = ref(false);
const trackMessage = ref('');
const trackError = ref(false);

const tabClass = (name) => [
    'px-4 py-2 rounded-xl text-sm font-semibold transition-colors',
    tab.value === name ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
];

const flat = computed(() => {
    const out = [];
    const walk = (nodes) => nodes.forEach(n => { out.push(n); walk(n.children || []); });
    walk(manual.value.tree);
    return out;
});

const firstUnread = computed(() => flat.value.find(s => !s.read) || flat.value[0]);

const load = async () => {
    loading.value = true;
    error.value = null;
    try {
        const [sys, man] = await Promise.all([
            api.get(`/systems/${slug.value}`),
            api.get(`/systems/${slug.value}/manual`),
        ]);
        system.value = sys.data.data;
        manual.value = man.data.data;
    } catch (e) {
        error.value = e.response?.status === 404 ? 'System not found.' : (e.response?.data?.message || 'Could not load this system.');
    } finally {
        loading.value = false;
    }
};

const runSearch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        if (searchTerm.value.trim().length < 2) {
            searchResults.value = [];
            return;
        }
        const res = await api.get(`/systems/${slug.value}/manual/search`, { params: { q: searchTerm.value } });
        searchResults.value = res.data.data;
    }, 250);
};

const trackStudy = async () => {
    tracking.value = true;
    trackMessage.value = '';
    try {
        await api.post(`/systems/${slug.value}/manual/track-study`);
        trackError.value = false;
        trackMessage.value = 'Study timer running. Stop it from Daily Plans when you finish.';
    } catch (e) {
        trackError.value = true;
        trackMessage.value = e.response?.data?.errors?.session?.[0] || e.response?.data?.errors?.timer?.[0] || e.response?.data?.message || 'Could not start the timer.';
    } finally {
        tracking.value = false;
    }
};

watch(tab, (value) => router.replace({ query: value === 'manual' ? { tab: 'manual' } : {} }));
watch(slug, load);
onMounted(load);
</script>

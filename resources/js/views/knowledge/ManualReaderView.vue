<template>
    <div class="space-y-4">
        <router-link :to="{ name: 'SystemDetail', params: { slug }, query: { tab: 'manual' } }" class="text-sm text-gray-500 hover:text-gray-800">
            ← {{ manual?.system?.name || 'Back' }}
        </router-link>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Table of contents -->
            <aside class="lg:col-span-1 bg-white border border-gray-100 rounded-2xl p-4 shadow-sm lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">
                <div class="flex items-center justify-between mb-3 px-2">
                    <span class="text-xs font-bold text-gray-400 uppercase">Contents</span>
                    <span v-if="manual" class="text-xs text-gray-500">{{ manual.progress.percent }}% read</span>
                </div>
                <ManualTree v-if="manual" :nodes="manual.tree" :slug="slug" :active-id="sectionId" />
            </aside>

            <!-- Section -->
            <article class="lg:col-span-3 bg-white border border-gray-100 rounded-2xl shadow-sm">
                <div v-if="loading" class="p-8 text-sm text-gray-500">Loading…</div>
                <div v-else-if="error" class="p-8 text-sm text-red-600">{{ error }}</div>

                <template v-else-if="section">
                    <header class="p-6 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <h1 class="text-2xl font-bold text-gray-900">
                                <span class="text-gray-400 mr-2">{{ section.number }}</span>{{ section.title }}
                            </h1>
                            <div class="flex gap-2 shrink-0">
                                <button v-if="section.library_path" @click="openInLibrary(section.library_path)"
                                        title="Open this section in the public documentation library"
                                        class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                                    🌐 Library
                                </button>
                                <button @click="showExport = true"
                                        class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                                    ⬇ PDF
                                </button>
                                <router-link v-if="manual?.can?.contribute" :to="{ name: 'ManualEditor', params: { slug }, query: { section: section.id } }"
                                             class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                                    ✎ {{ manual.can.approve ? 'Edit' : 'Suggest an edit' }}
                                </router-link>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                            <span>Version {{ section.version }}</span>
                            <span v-if="section.restricted" class="text-amber-700 font-semibold" title="Only staff and readers with an access code that includes restricted chapters can read it in the documentation library">🔒 Restricted in the library</span>
                            <span v-if="section.updated_at">Updated {{ formatDate(section.updated_at) }}<template v-if="section.updated_by"> by {{ section.updated_by }}</template></span>
                            <span v-if="section.last_verified_at" :class="section.is_stale ? 'text-amber-700 font-semibold' : ''">
                                {{ section.is_stale ? '⚠ Not verified since' : 'Verified' }} {{ formatDate(section.last_verified_at) }}
                            </span>
                        </div>
                    </header>

                    <div class="p-6">
                        <!-- Written section: HTML is sanitised on the server -->
                        <div v-if="section.kind === 'written'" class="kb-content" v-html="section.body"></div>

                        <!-- Uploaded PDF -->
                        <div v-else-if="section.file" class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="text-gray-600">
                                    {{ section.file.name }}
                                    <template v-if="section.file.page_from || section.file.page_to">
                                        · pages {{ section.file.page_from || 1 }}–{{ section.file.page_to || 'end' }}
                                    </template>
                                </span>
                                <a :href="section.file.url" target="_blank" rel="noopener" class="text-teal-700 underline">Open in a new tab ↗</a>
                            </div>
                            <iframe :src="pdfSrc" class="w-full h-[75vh] rounded-lg border border-gray-200" title="PDF section"></iframe>
                        </div>
                    </div>

                    <footer class="p-6 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <button v-if="section.previous_id" @click="go(section.previous_id)" class="text-sm text-gray-600 hover:text-gray-900">← Previous</button>
                        <span v-else></span>

                        <button @click="markRead" :disabled="section.read || marking"
                                class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors"
                                :class="section.read ? 'bg-green-50 text-green-700 cursor-default' : 'bg-teal-600 text-white hover:bg-teal-700'">
                            {{ section.read ? '✓ Read' : (section.next_id ? 'Mark as read & continue' : 'Mark as read') }}
                        </button>

                        <button v-if="section.next_id" @click="go(section.next_id)" class="text-sm text-gray-600 hover:text-gray-900">Next →</button>
                        <span v-else></span>
                    </footer>
                </template>
            </article>
        </div>

        <ExportManualDialog v-if="showExport && manual" :slug="slug" :tree="manual.tree" :preselect="[sectionId]" @close="showExport = false" />
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
const sectionId = computed(() => Number(route.params.sectionId));

const manual = ref(null);
const section = ref(null);
const loading = ref(true);
const error = ref(null);
const marking = ref(false);
const showExport = ref(false);

const pdfSrc = computed(() => {
    const f = section.value?.file;
    if (!f) return '';
    return f.page_from ? `${f.url}#page=${f.page_from}` : f.url;
});

const formatDate = (iso) => new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

const loadManual = async () => {
    const res = await api.get(`/systems/${slug.value}/manual`);
    manual.value = res.data.data;
};

const loadSection = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get(`/systems/${slug.value}/manual/sections/${sectionId.value}`);
        section.value = res.data.data;
    } catch (e) {
        error.value = e.response?.status === 404 ? 'This section is not available.' : 'Could not load this section.';
    } finally {
        loading.value = false;
    }
};

const markRead = async () => {
    marking.value = true;
    try {
        const res = await api.post(`/systems/${slug.value}/manual/sections/${sectionId.value}/read`);
        section.value.read = true;
        if (manual.value) manual.value.progress = res.data.data;
        markTreeRead(manual.value?.tree || [], sectionId.value);
        if (section.value.next_id) go(section.value.next_id);
    } finally {
        marking.value = false;
    }
};

const markTreeRead = (nodes, id) => nodes.forEach(n => {
    if (n.id === id) n.read = true;
    markTreeRead(n.children || [], id);
});

const go = (id) => router.push({ name: 'ManualReader', params: { slug: slug.value, sectionId: id } });

watch(sectionId, () => {
    loadSection();
    document.querySelector('main')?.scrollTo({ top: 0 });
});
watch(slug, () => { loadManual(); loadSection(); });

onMounted(() => {
    loadManual();
    loadSection();
});
</script>

<template>
    <div class="space-y-5 max-w-4xl">
        <router-link :to="{ name: 'SystemDetail', params: { slug }, query: { tab: 'manual' } }" class="text-sm text-gray-500 hover:text-gray-800">← Back to the manual</router-link>

        <div>
            <h1 class="text-2xl font-bold text-gray-900">Upload a document</h1>
            <p class="text-sm text-gray-500 mt-1">
                Uploads take a place in the manual's order, so the generated manual stays complete and in sequence.
                {{ canApprove ? 'You own this system, so uploads publish straight away.' : 'The system owner approves uploads before they appear.' }}
            </p>
        </div>

        <div class="flex gap-2">
            <button @click="mode = 'docx'" :class="tabClass('docx')">Word manual (.docx)</button>
            <button @click="mode = 'pdf'" :class="tabClass('pdf')">PDF section</button>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm space-y-5">
            <p v-if="mode === 'docx'" class="text-sm text-gray-600">
                The document is split at its headings: <strong>Heading 1</strong> becomes chapters, <strong>Heading 2</strong> sections and
                <strong>Heading 3</strong> sub-sections. Each part becomes an editable section, and images are kept.
            </p>
            <p v-else class="text-sm text-gray-600">
                The PDF is placed as one section of the manual. You can use only part of it by choosing a page range.
            </p>

            <div v-if="mode === 'pdf'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Section title</label>
                <input v-model="title" type="text" maxlength="255" placeholder="Defaults to the file name"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500">
            </div>

            <PositionPicker :tree="tree" v-model:parent-id="parentId" v-model:after-id="afterId" />

            <div>
                <span class="block text-sm font-medium text-gray-700 mb-1">File</span>
                <!-- Click or drop a file here -->
                <label @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop"
                       class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-6 py-8 text-center cursor-pointer transition-colors"
                       :class="dragging ? 'border-teal-500 bg-teal-50' : file ? 'border-teal-300 bg-teal-50/50' : 'border-gray-300 bg-gray-50 hover:border-teal-400 hover:bg-teal-50/40'">
                    <input :key="mode" type="file" class="sr-only" :accept="mode === 'pdf' ? 'application/pdf' : '.docx'"
                           @change="file = $event.target.files[0] || null">
                    <svg class="w-10 h-10 text-teal-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <template v-if="file">
                        <span class="font-semibold text-gray-900 break-all">{{ file.name }}</span>
                        <span class="text-xs text-gray-500">{{ (file.size / 1024 / 1024).toFixed(2) }} MB · click to choose a different file</span>
                    </template>
                    <template v-else>
                        <span class="inline-flex items-center px-5 py-2.5 rounded-xl bg-teal-600 text-white text-sm font-semibold shadow-sm hover:bg-teal-700">
                            Choose {{ mode === 'pdf' ? 'a PDF file' : 'a Word file (.docx)' }}
                        </span>
                        <span class="text-xs text-gray-500">or drag and drop it here</span>
                    </template>
                </label>
            </div>

            <div v-if="mode === 'pdf'" class="grid grid-cols-2 gap-3 max-w-sm">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From page <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input v-model.number="pageFrom" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To page <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input v-model.number="pageTo" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Note for the reviewer <span class="text-gray-400 font-normal">(optional)</span></label>
                <input v-model="summary" type="text" maxlength="500" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>

            <p v-if="message" class="text-sm" :class="isError ? 'text-red-600' : 'text-green-700'">{{ message }}</p>

            <!-- Word import preview -->
            <div v-if="importChange" class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                <div class="text-sm font-semibold text-gray-900 mb-2">{{ importChange.import_sections.length }} sections found</div>
                <ul class="text-sm text-gray-700 space-y-0.5 max-h-72 overflow-y-auto">
                    <li v-for="(s, i) in importChange.import_sections" :key="i" :style="{ paddingLeft: (s.depth * 1.25) + 'rem' }">
                        {{ s.depth === 0 ? '■' : '–' }} {{ s.title }}
                    </li>
                </ul>
                <p class="text-xs text-gray-500 mt-3">The owner can leave sections out when approving, and rename or reorder them afterwards.</p>
            </div>

            <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
                <template v-if="!importChange">
                    <button @click="upload" :disabled="!file || busy"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50">
                        {{ busy ? 'Uploading…' : (mode === 'docx' ? 'Upload & preview' : (canApprove ? 'Upload & publish' : 'Upload & submit for approval')) }}
                    </button>
                </template>
                <template v-else>
                    <button @click="submitImport" :disabled="busy"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50">
                        {{ canApprove ? 'Publish these sections' : 'Submit for approval' }}
                    </button>
                    <button @click="discardImport" :disabled="busy" class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700">
                        Discard
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/axios';
import PositionPicker from '../../components/knowledge/PositionPicker.vue';

const route = useRoute();
const router = useRouter();
const slug = computed(() => route.params.slug);

const mode = ref('docx');
const tree = ref([]);
const canApprove = ref(false);

const title = ref('');
const parentId = ref(null);
const afterId = ref(null);
const file = ref(null);
const dragging = ref(false);
const onDrop = (event) => {
    dragging.value = false;
    const dropped = event.dataTransfer?.files?.[0];
    if (!dropped) return;
    const ok = mode.value === 'pdf' ? /\.pdf$/i.test(dropped.name) : /\.docx$/i.test(dropped.name);
    if (ok) file.value = dropped;
    else alert(mode.value === 'pdf' ? 'Please drop a PDF file.' : 'Please drop a Word file (.docx).');
};
const pageFrom = ref(null);
const pageTo = ref(null);
const summary = ref('');

const busy = ref(false);
const message = ref('');
const isError = ref(false);
const importChange = ref(null);

const tabClass = (name) => [
    'px-4 py-2 rounded-xl text-sm font-semibold transition-colors',
    mode.value === name ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
];

const errorText = (e, fallback) => {
    const errors = e.response?.data?.errors;
    return (errors && Object.values(errors).flat()[0]) || e.response?.data?.message || fallback;
};

const say = (text, error = false) => { message.value = text; isError.value = error; };

const multipart = { headers: { 'Content-Type': 'multipart/form-data' } };

const finish = (changeData) => {
    if (changeData.status === 'approved') {
        router.push({ name: 'SystemDetail', params: { slug: slug.value }, query: { tab: 'manual' } });
    } else {
        say('Submitted for approval. You\'ll get an inbox message when the owner reviews it.');
        file.value = null;
        importChange.value = null;
    }
};

const upload = async () => {
    busy.value = true;
    say('');
    let draftId = null;
    try {
        if (mode.value === 'docx') {
            const data = new FormData();
            data.append('file', file.value);
            if (parentId.value) data.append('proposed_parent_id', parentId.value);
            if (afterId.value) data.append('proposed_after_id', afterId.value);
            if (summary.value) data.append('change_summary', summary.value);
            const res = await api.post(`/systems/${slug.value}/manual/import-docx`, data, multipart);
            importChange.value = res.data.data;
            return;
        }

        // PDF: create a draft section, attach the file, then submit
        const draft = await api.post(`/systems/${slug.value}/manual/changes`, {
            type: 'create',
            title: title.value.trim() || 'Untitled section',
            proposed_parent_id: parentId.value,
            proposed_after_id: afterId.value,
            change_summary: summary.value || null,
        });
        draftId = draft.data.data.id;

        const data = new FormData();
        data.append('file', file.value);
        if (pageFrom.value) data.append('page_from', pageFrom.value);
        if (pageTo.value) data.append('page_to', pageTo.value);
        await api.post(`/manual-changes/${draftId}/pdf`, data, multipart);

        const submitted = await api.post(`/manual-changes/${draftId}/submit`);
        finish(submitted.data.data);
    } catch (e) {
        // Don't leave an empty draft behind if the file was rejected
        if (draftId) await api.delete(`/manual-changes/${draftId}`).catch(() => {});
        say(errorText(e, 'Upload failed.'), true);
    } finally {
        busy.value = false;
    }
};

const submitImport = async () => {
    busy.value = true;
    try {
        const res = await api.post(`/manual-changes/${importChange.value.id}/submit`);
        finish(res.data.data);
    } catch (e) {
        say(errorText(e, 'Could not submit.'), true);
    } finally {
        busy.value = false;
    }
};

const discardImport = async () => {
    busy.value = true;
    try {
        await api.delete(`/manual-changes/${importChange.value.id}`);
        importChange.value = null;
        file.value = null;
        say('');
    } finally {
        busy.value = false;
    }
};

watch(mode, () => { file.value = null; say(''); });

onMounted(async () => {
    const res = await api.get(`/systems/${slug.value}/manual`);
    tree.value = res.data.data.tree;
    canApprove.value = res.data.data.can.approve;
});
</script>

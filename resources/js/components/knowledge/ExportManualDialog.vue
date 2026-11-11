<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50" @click.self="$emit('close')">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
            <div class="px-6 pt-6 pb-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">Download as PDF</h3>
                <p class="text-sm text-gray-500 mt-1">Only published content is included. The PDF is stamped with the manual version.</p>
            </div>

            <div class="px-6 py-4 space-y-5 overflow-y-auto">
                <!-- What to include -->
                <div class="space-y-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" value="full" v-model="scope" class="mt-1">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Whole manual</span>
                            <span class="block text-xs text-gray-500">Cover, contents with page numbers, every section in order.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" value="selected" v-model="scope" class="mt-1">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Selected sections</span>
                            <span class="block text-xs text-gray-500">Each chosen section comes with everything under it.</span>
                        </span>
                    </label>
                </div>

                <div v-if="scope === 'selected'" class="border border-gray-200 rounded-xl max-h-60 overflow-y-auto divide-y divide-gray-50">
                    <label v-for="s in flat" :key="s.id" class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
                           :style="{ paddingLeft: (0.75 + s.depthInTree * 1.1) + 'rem' }">
                        <input type="checkbox" :value="s.id" v-model="selected">
                        <span class="text-gray-400 tabular-nums w-10 shrink-0">{{ s.number }}</span>
                        <span class="text-gray-800">{{ s.title }}</span>
                    </label>
                </div>

                <!-- Appendices -->
                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-2">Add at the end</div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 mb-1">
                        <input type="checkbox" value="contacts" v-model="appendices">
                        Contacts &amp; escalation
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" value="changelog" v-model="appendices">
                        Change log (what changed, when and by whom)
                    </label>
                </div>

                <!-- Result -->
                <div v-if="message" class="text-sm rounded-xl p-3"
                     :class="state === 'failed' ? 'bg-red-50 text-red-700' : state === 'ready' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800'">
                    {{ message }}
                    <a v-if="state === 'ready' && downloadUrl" :href="downloadUrl" class="font-semibold underline ml-1">Download again</a>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-2">
                <button type="button" @click="$emit('close')" class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                    Close
                </button>
                <button type="button" @click="generate" :disabled="busy || (scope === 'selected' && !selected.length)"
                        class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50">
                    {{ busy ? 'Generating…' : 'Generate PDF' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import api from '../../lib/axios';

const props = defineProps({
    slug: { type: String, required: true },
    tree: { type: Array, default: () => [] },
    // Pre-select these sections (e.g. "download this section" from the reader)
    preselect: { type: Array, default: () => [] },
});

defineEmits(['close']);

const scope = ref(props.preselect.length ? 'selected' : 'full');
const selected = ref([...props.preselect]);
const appendices = ref(props.preselect.length ? [] : ['contacts']);
const busy = ref(false);
const state = ref(null);       // queued | processing | ready | failed
const message = ref('');
const downloadUrl = ref(null);
let pollTimer = null;
let pollUntil = 0;

const flat = computed(() => {
    const out = [];
    const walk = (nodes, depth) => nodes.forEach(n => { out.push({ ...n, depthInTree: depth }); walk(n.children || [], depth + 1); });
    walk(props.tree, 0);
    return out;
});

const download = (url) => {
    downloadUrl.value = url;
    window.location.assign(url); // the server sends it as a file download
};

const handle = (exp) => {
    state.value = exp.status;

    if (exp.status === 'ready') {
        busy.value = false;
        message.value = `Your PDF is ready (${exp.page_count} pages, manual v${exp.manual_version}). The download should start now.`;
        download(exp.download_url);
        return;
    }

    if (exp.status === 'failed') {
        busy.value = false;
        message.value = exp.error;
        return;
    }

    // Large manuals are generated in the background; keep checking for a while.
    message.value = 'This is a large manual, so it is being generated in the background. You can wait here or close this window: you\'ll get an inbox message when it\'s ready.';
    if (Date.now() < pollUntil) {
        pollTimer = setTimeout(() => poll(exp.id), 3000);
    } else {
        busy.value = false;
    }
};

const poll = async (id) => {
    try {
        handle((await api.get(`/manual-exports/${id}`)).data.data);
    } catch {
        busy.value = false;
    }
};

const generate = async () => {
    busy.value = true;
    state.value = null;
    message.value = '';
    pollUntil = Date.now() + 3 * 60 * 1000;

    try {
        const res = await api.post(`/systems/${props.slug}/manual/export`, {
            section_ids: scope.value === 'selected' ? selected.value : null,
            appendices: appendices.value,
        });
        handle(res.data.data);
    } catch (e) {
        busy.value = false;
        state.value = 'failed';
        const errors = e.response?.data?.errors;
        message.value = (errors && Object.values(errors).flat()[0]) || e.response?.data?.message || 'The PDF could not be generated.';
    }
};

onBeforeUnmount(() => clearTimeout(pollTimer));
</script>

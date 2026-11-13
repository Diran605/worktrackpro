<template>
    <div class="space-y-5 max-w-5xl">
        <router-link :to="backLink" class="text-sm text-gray-500 hover:text-gray-800">← Back to the manual</router-link>

        <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
        <div v-else-if="loadError" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-4">{{ loadError }}</div>

        <template v-else>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit section' : 'New section' }}
                    <span v-if="change?.section" class="text-gray-400 font-normal text-lg">· {{ change.section.number }} {{ change.section.title }}</span>
                </h1>
                <span v-if="change" class="text-xs font-semibold px-2.5 py-1 rounded-full self-start" :class="statusClass">{{ change.status_label }}</span>
            </div>

            <!-- Review feedback -->
            <div v-if="change?.status === 'changes_requested'" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
                <div class="font-semibold text-amber-800">Sent back by {{ change.reviewer || 'the owner' }}</div>
                <p class="text-amber-900 mt-1 whitespace-pre-line">{{ change.review_comment }}</p>
            </div>
            <div v-else-if="change && !change.editable" class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-900">
                {{ change.status === 'submitted' ? 'This change is waiting for approval and can\'t be edited right now.' : 'This change is closed and can no longer be edited.' }}
            </div>
            <div v-if="isEdit && change?.section && change.base_version !== null && change.section.current_version !== change.base_version"
                 class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900">
                Someone has published a newer version of this section since you started. Check the current text before submitting.
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section title</label>
                    <input v-model="form.title" :disabled="readOnly" type="text" maxlength="255" placeholder="e.g. Resolving a failed payment"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 disabled:bg-gray-50">
                </div>

                <PositionPicker v-if="!isEdit" :tree="tree" :disabled="readOnly"
                                v-model:parent-id="form.proposed_parent_id" v-model:after-id="form.proposed_after_id" />

                <!-- Uploaded PDF section being edited: replace the file -->
                <div v-if="form.kind === 'uploaded_pdf'" class="border border-dashed border-gray-300 rounded-xl p-4 space-y-3">
                    <div class="text-sm text-gray-700">
                        This section is an uploaded PDF:
                        <a v-if="change?.file?.url" :href="change.file.url" target="_blank" rel="noopener" class="text-teal-700 underline">{{ change.file.name }}</a>
                    </div>
                    <div v-if="!readOnly" class="flex flex-wrap items-end gap-3">
                        <input type="file" accept="application/pdf" @change="pdfFile = $event.target.files[0]" class="text-sm">
                        <button type="button" @click="replacePdf" :disabled="!pdfFile || busy" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-gray-900 text-white disabled:opacity-50">Replace PDF</button>
                    </div>
                </div>

                <!-- Written content -->
                <div v-else>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                    <p class="text-xs text-gray-500 mb-2">It doesn't need to be polished. A few screenshots with short notes is a great start.</p>
                    <RichEditor v-model="form.body" :slug="slug" :editable="!readOnly" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">What changed? <span class="text-gray-400 font-normal">(optional, shown to the reviewer)</span></label>
                    <input v-model="form.change_summary" :disabled="readOnly" type="text" maxlength="500"
                           :placeholder="isEdit ? 'e.g. Added the new refund screen' : 'e.g. First draft of the refunds process'"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 disabled:bg-gray-50">
                </div>

                <p v-if="message" class="text-sm" :class="messageIsError ? 'text-red-600' : 'text-green-700'">{{ message }}</p>

                <div v-if="!readOnly" class="flex flex-col-reverse sm:flex-row sm:items-center gap-2 pt-2 border-t border-gray-100">
                    <button v-if="change" type="button" @click="discard" :disabled="busy" class="text-sm text-red-600 hover:text-red-700 sm:mr-auto py-2">Delete draft</button>
                    <span v-else class="sm:mr-auto"></span>

                    <button v-if="authStore.hasModule('work_tracking')" type="button" @click="trackTime" :disabled="busy" title="Start a timer for this work"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        ⏱ Track as work
                    </button>
                    <button type="button" @click="save()" :disabled="busy"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        Save draft
                    </button>
                    <button type="button" @click="submit" :disabled="busy"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50">
                        {{ canApprove ? 'Publish' : 'Submit for approval' }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import RichEditor from '../../components/knowledge/RichEditor.vue';
import PositionPicker from '../../components/knowledge/PositionPicker.vue';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const slug = computed(() => route.params.slug);

const tree = ref([]);
const canApprove = ref(false);
const change = ref(null);
const loading = ref(true);
const loadError = ref(null);
const busy = ref(false);
const message = ref('');
const messageIsError = ref(false);
const pdfFile = ref(null);

const form = reactive({
    title: '',
    body: '',
    kind: 'written',
    change_summary: '',
    proposed_parent_id: route.query.parent ? Number(route.query.parent) : null,
    proposed_after_id: null,
});

// "Unsaved" = the form differs from what was last saved or loaded
const savedSnapshot = ref('');
const snapshot = () => JSON.stringify([form.title, form.body, form.change_summary, form.proposed_parent_id, form.proposed_after_id]);
const markSaved = () => { savedSnapshot.value = snapshot(); };

const isEdit = computed(() => change.value?.type === 'edit' || !!route.query.section);
const readOnly = computed(() => !!change.value && !change.value.editable);
const dirty = computed(() => !readOnly.value && snapshot() !== savedSnapshot.value);
const backLink = computed(() => change.value?.section
    ? { name: 'ManualReader', params: { slug: slug.value, sectionId: change.value.section.id } }
    : { name: 'SystemDetail', params: { slug: slug.value }, query: { tab: 'manual' } });

const statusClass = computed(() => ({
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-800',
    changes_requested: 'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-700',
}[change.value?.status] || 'bg-gray-100 text-gray-700'));

const errorText = (e, fallback) => {
    const errors = e.response?.data?.errors;
    return (errors && Object.values(errors).flat()[0]) || e.response?.data?.message || fallback;
};

const notify = (text, isError = false) => {
    message.value = text;
    messageIsError.value = isError;
};

const hydrate = (data) => {
    change.value = data;
    form.title = data.title === 'Untitled section' ? '' : data.title;
    form.body = data.body || '';
    form.kind = data.kind;
    form.change_summary = data.change_summary || '';
    form.proposed_parent_id = data.proposed_parent_id;
    form.proposed_after_id = data.proposed_after_id;
    markSaved();
};

const load = async () => {
    loading.value = true;
    try {
        const manual = await api.get(`/systems/${slug.value}/manual`);
        tree.value = manual.data.data.tree;
        canApprove.value = manual.data.data.can.approve;

        if (!manual.data.data.can.contribute) {
            loadError.value = 'You don\'t have permission to contribute to this manual.';
            return;
        }

        if (route.query.change) {
            const res = await api.get(`/manual-changes/${route.query.change}`);
            hydrate(res.data.data);
        } else if (route.query.section) {
            // Starts an edit draft (or reopens the one you already have for this section)
            const res = await api.post(`/systems/${slug.value}/manual/changes`, { type: 'edit', manual_section_id: Number(route.query.section) });
            hydrate(res.data.data);
            router.replace({ query: { change: res.data.data.id } });
        }
        if (!change.value) markSaved(); // a new, empty section has nothing unsaved yet
    } catch (e) {
        loadError.value = errorText(e, 'Could not open the editor.');
    } finally {
        loading.value = false;
    }
};

/**
 * Create the draft on first save, update it afterwards. Returns the change id.
 */
const save = async (quiet = false) => {
    busy.value = true;
    try {
        const payload = {
            title: form.title.trim() || 'Untitled section',
            body: form.kind === 'written' ? form.body : undefined,
            change_summary: form.change_summary || null,
            ...(isEdit.value ? {} : { proposed_parent_id: form.proposed_parent_id, proposed_after_id: form.proposed_after_id }),
        };

        const res = change.value
            ? await api.patch(`/manual-changes/${change.value.id}`, payload)
            : await api.post(`/systems/${slug.value}/manual/changes`, { type: 'create', kind: 'written', ...payload });

        const wasNew = !change.value;
        hydrate(res.data.data);
        if (wasNew) router.replace({ query: { change: res.data.data.id } });
        if (!quiet) notify('Draft saved.');
        return res.data.data.id;
    } catch (e) {
        notify(errorText(e, 'Could not save.'), true);
        return null;
    } finally {
        busy.value = false;
    }
};

const submit = async () => {
    if (!form.title.trim()) {
        notify('Give the section a title first.', true);
        return;
    }
    const id = await save(true);
    if (!id) return;

    busy.value = true;
    try {
        const res = await api.post(`/manual-changes/${id}/submit`);
        hydrate(res.data.data);

        if (res.data.data.status === 'approved' && res.data.data.section) {
            router.push({ name: 'ManualReader', params: { slug: slug.value, sectionId: res.data.data.section.id } });
        } else {
            notify('Submitted. The system owner has been notified and you\'ll get an inbox message when it\'s reviewed.');
        }
    } catch (e) {
        notify(errorText(e, 'Could not submit.'), true);
    } finally {
        busy.value = false;
    }
};

const trackTime = async () => {
    const id = change.value?.id || await save(true);
    if (!id) return;

    busy.value = true;
    try {
        await api.post(`/manual-changes/${id}/track-time`);
        notify('Timer started on a "Documentation" plan. It is marked done automatically when this change is approved.');
    } catch (e) {
        notify(errorText(e, 'Could not start the timer.'), true);
    } finally {
        busy.value = false;
    }
};

const replacePdf = async () => {
    if (!pdfFile.value || !change.value) return;
    busy.value = true;
    try {
        const data = new FormData();
        data.append('file', pdfFile.value);
        const res = await api.post(`/manual-changes/${change.value.id}/pdf`, data, { headers: { 'Content-Type': 'multipart/form-data' } });
        hydrate(res.data.data);
        pdfFile.value = null;
        notify('PDF replaced.');
    } catch (e) {
        notify(errorText(e, 'Upload failed.'), true);
    } finally {
        busy.value = false;
    }
};

const discard = async () => {
    if (!window.confirm('Delete this draft? This can\'t be undone.')) return;
    busy.value = true;
    try {
        await api.delete(`/manual-changes/${change.value.id}`);
        change.value = null;
        markSaved();
        router.push(backLink.value);
    } catch (e) {
        notify(errorText(e, 'Could not delete.'), true);
    } finally {
        busy.value = false;
    }
};

// Warn before losing unsaved writing
onBeforeRouteLeave(() => !dirty.value || window.confirm('You have unsaved changes. Leave without saving?'));
const beforeUnload = (e) => { if (dirty.value) { e.preventDefault(); e.returnValue = ''; } };
window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));

onMounted(load);
</script>

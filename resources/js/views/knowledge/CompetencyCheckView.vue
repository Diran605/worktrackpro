<template>
    <div class="space-y-6 max-w-4xl">
        <router-link :to="{ name: 'MyReadiness' }" class="text-sm text-gray-500 hover:text-gray-800">← My readiness</router-link>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="w-8 h-8 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-4">{{ error }}</div>

        <template v-else-if="check">
            <div>
                <div class="text-sm text-gray-500">{{ check.system.name }} · {{ check.required ? 'Required' : 'Optional' }}<template v-if="check.weight_label"> · {{ check.weight_label }}</template></div>
                <div class="flex flex-wrap items-center gap-3 mt-1">
                    <h1 class="text-2xl font-bold text-gray-900">{{ check.title }}</h1>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="statusClass(check.status)">{{ check.status_label }}</span>
                </div>
                <p v-if="check.expires_at" class="text-sm text-gray-500 mt-1">Your pass is valid until {{ formatDate(check.expires_at) }}.</p>
                <p v-else-if="check.valid_for_days" class="text-sm text-gray-500 mt-1">A pass is valid for {{ check.valid_for_days }} days.</p>
            </div>

            <section class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide">The situation</h2>
                    <p class="mt-2 text-gray-800 whitespace-pre-line">{{ check.scenario }}</p>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide">What a good answer includes</h2>
                    <ol class="mt-2 list-decimal pl-5 space-y-1 text-gray-800">
                        <li v-for="(c, i) in check.criteria" :key="i">{{ c }}</li>
                    </ol>
                </div>
                <div v-if="check.sections.length">
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Study first</h2>
                    <ul class="mt-2 space-y-1">
                        <li v-for="s in check.sections" :key="s.id">
                            <router-link :to="{ name: 'ManualReader', params: { slug: check.system.slug, sectionId: s.id } }" class="text-teal-700 hover:underline">
                                {{ s.number }} {{ s.title }}
                            </router-link>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Nothing in progress: start (or start again) -->
            <section v-if="!current && check.status !== 'passed'" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 text-center">
                <p class="text-gray-700">
                    {{ check.status === 'expired' ? 'Your pass has expired. Answer again to renew it.'
                        : check.status === 'recheck' ? 'The manual changed a lot since you passed. Answer again with the new version in mind.'
                        : 'Answer in writing, with screenshots, or with a link to a short video showing how you do it.' }}
                </p>
                <button @click="start" :disabled="busy" class="mt-4 px-5 py-2.5 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50">
                    {{ check.status === 'todo' ? 'Start my answer' : 'Answer again' }}
                </button>
            </section>

            <!-- My answer -->
            <section v-if="current" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-900">My answer</h2>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="statusClass(current.status)">{{ current.status_label }}</span>
                </div>

                <div v-if="lastReview" class="rounded-xl px-4 py-3 text-sm" :class="lastReview.decision === 'approved' ? 'bg-green-50 text-green-900' : 'bg-amber-50 text-amber-900'">
                    <div class="font-semibold">{{ lastReview.decision_label }} by {{ lastReview.reviewer || 'a reviewer' }} · {{ formatDate(lastReview.at) }}</div>
                    <div class="mt-0.5">{{ lastReview.criteria_met.length }} of {{ check.criteria.length }} items met</div>
                    <div v-if="lastReview.comment" class="mt-2 whitespace-pre-line">{{ lastReview.comment }}</div>
                </div>

                <!-- Editing -->
                <template v-if="current.editable">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1" for="answer">Written answer</label>
                        <textarea id="answer" v-model="form.answer" rows="10" maxlength="20000"
                                  placeholder="Explain step by step what you would do and why."
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-teal-500 focus:border-teal-500"></textarea>
                    </div>

                    <div>
                        <div class="text-sm font-semibold text-gray-800 mb-1">Screenshots</div>
                        <div class="flex flex-wrap gap-3">
                            <div v-for="shot in current.screenshots" :key="shot.id" class="relative">
                                <a :href="shot.url" target="_blank" rel="noopener"><img :src="shot.url" :alt="shot.name" class="h-28 rounded-lg border border-gray-200 object-cover"></a>
                                <button @click="removeShot(shot)" type="button" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-white border border-gray-300 text-gray-600 hover:text-red-600 text-xs" title="Remove">✕</button>
                            </div>
                            <label v-if="current.screenshots.length < check.limits.screenshots"
                                   class="h-28 w-36 flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-teal-300 bg-teal-50/50 text-teal-700 text-sm font-semibold cursor-pointer hover:bg-teal-50">
                                <span class="text-2xl leading-none">+</span>
                                {{ uploading ? 'Uploading…' : 'Add screenshot' }}
                                <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple class="hidden" @change="addShots" :disabled="uploading">
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF or WebP, up to {{ Math.round(check.limits.image_kb / 1024) }} MB each.</p>
                    </div>

                    <div>
                        <div class="text-sm font-semibold text-gray-800 mb-1">Video links</div>
                        <div class="space-y-2">
                            <div v-for="(link, i) in form.video_links" :key="i" class="flex gap-2">
                                <input v-model="form.video_links[i]" type="url" placeholder="https://www.loom.com/share/…"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500">
                                <button @click="form.video_links.splice(i, 1)" type="button" class="px-3 rounded-lg border border-gray-200 text-gray-500 hover:text-red-600">✕</button>
                            </div>
                        </div>
                        <button v-if="form.video_links.length < check.limits.video_links" @click="form.video_links.push('')" type="button" class="mt-2 text-sm font-semibold text-teal-700 hover:underline">+ Add a video link</button>
                        <p class="text-xs text-gray-500 mt-1">YouTube, Vimeo, Loom, Google Drive, OneDrive or SharePoint. Make sure reviewers are allowed to open it.</p>
                    </div>

                    <div v-if="formError" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-3">{{ formError }}</div>
                    <div v-if="saved" class="text-sm text-green-700">Saved.</div>

                    <div class="flex flex-wrap gap-2 justify-end">
                        <button @click="save()" :disabled="busy" class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-50">Save draft</button>
                        <button @click="submit" :disabled="busy" class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50">
                            {{ current.status === 'changes_requested' ? 'Submit again' : 'Submit for review' }}
                        </button>
                    </div>
                </template>

                <!-- Read only (waiting or passed) -->
                <template v-else>
                    <p v-if="current.status === 'submitted'" class="text-sm text-blue-800 bg-blue-50 rounded-xl px-4 py-3">A reviewer will look at your answer. You'll get an inbox message when they have.</p>
                    <div v-if="current.answer" class="text-gray-800 whitespace-pre-line">{{ current.answer }}</div>
                    <div v-if="current.screenshots.length" class="flex flex-wrap gap-3">
                        <a v-for="shot in current.screenshots" :key="shot.id" :href="shot.url" target="_blank" rel="noopener">
                            <img :src="shot.url" :alt="shot.name" class="h-28 rounded-lg border border-gray-200 object-cover">
                        </a>
                    </div>
                    <ul v-if="current.video_links.length" class="space-y-1">
                        <li v-for="link in current.video_links" :key="link"><a :href="link" target="_blank" rel="noopener noreferrer" class="text-teal-700 hover:underline break-all">{{ link }}</a></li>
                    </ul>
                </template>
            </section>

            <!-- Earlier attempts -->
            <section v-if="earlier.length" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Earlier answers</h2>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li v-for="s in earlier" :key="s.id">
                        {{ s.status_label }}<template v-if="s.submitted_at"> · submitted {{ formatDate(s.submitted_at) }}</template>
                        <template v-if="s.needs_recheck"> · the manual changed since</template>
                        <template v-else-if="s.expires_at"> · valid until {{ formatDate(s.expires_at) }}</template>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../lib/axios';

const route = useRoute();
const check = ref(null);
const loading = ref(true);
const error = ref(null);
const busy = ref(false);
const uploading = ref(false);
const saved = ref(false);
const formError = ref(null);
const form = reactive({ answer: '', video_links: [] });

// The answer being worked on: the newest one unless a newer pass exists (then show the pass).
const current = computed(() => {
    const subs = check.value?.submissions || [];
    if (!subs.length) return null;
    const newest = subs[0];
    if (newest.status === 'approved' && check.value.status !== 'passed') return null; // expired or re-check: start again
    return newest;
});
const earlier = computed(() => (check.value?.submissions || []).filter(s => s !== current.value));
const lastReview = computed(() => {
    const reviews = current.value?.reviews || [];
    return reviews.length ? reviews[reviews.length - 1] : null;
});

const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';

const statusClass = (s) => ({
    passed: 'bg-green-100 text-green-800',
    approved: 'bg-green-100 text-green-800',
    submitted: 'bg-blue-100 text-blue-800',
    changes_requested: 'bg-amber-100 text-amber-800',
    draft: 'bg-gray-100 text-gray-700',
    expired: 'bg-red-100 text-red-700',
    recheck: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700');

const firstError = (e, fallback) => {
    const errors = e.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    return e.response?.data?.message || fallback;
};

const fillForm = () => {
    form.answer = current.value?.answer || '';
    form.video_links = [...(current.value?.video_links || [])];
};

const load = async () => {
    try {
        const res = await api.get(`/competency-checks/${route.params.id}`);
        check.value = res.data.data;
        fillForm();
    } catch (e) {
        error.value = e.response?.status === 404 ? 'This check no longer exists.' : firstError(e, 'Could not load the check.');
    } finally {
        loading.value = false;
    }
};

const start = async () => {
    busy.value = true;
    try {
        await api.post(`/competency-checks/${check.value.id}/submissions`);
        await load();
    } catch (e) {
        error.value = firstError(e, 'Could not start.');
    } finally {
        busy.value = false;
    }
};

const save = async (quiet = false) => {
    busy.value = true;
    formError.value = null;
    saved.value = false;
    try {
        await api.patch(`/competency-submissions/${current.value.id}`, {
            answer: form.answer,
            video_links: form.video_links.filter(l => l.trim() !== ''),
        });
        if (!quiet) {
            saved.value = true;
            setTimeout(() => (saved.value = false), 2500);
        }
        return true;
    } catch (e) {
        formError.value = firstError(e, 'Could not save.');
        return false;
    } finally {
        busy.value = false;
    }
};

const submit = async () => {
    if (!(await save(true))) return;
    busy.value = true;
    try {
        await api.post(`/competency-submissions/${current.value.id}/submit`);
        await load();
    } catch (e) {
        formError.value = firstError(e, 'Could not submit.');
    } finally {
        busy.value = false;
    }
};

const addShots = async (event) => {
    const files = Array.from(event.target.files || []);
    event.target.value = '';
    if (!files.length) return;
    // Keep what was typed before reloading.
    if (!(await save(true))) return;
    uploading.value = true;
    formError.value = null;
    try {
        for (const file of files) {
            const data = new FormData();
            data.append('file', file);
            await api.post(`/competency-submissions/${current.value.id}/screenshots`, data);
        }
    } catch (e) {
        formError.value = firstError(e, 'Could not upload the screenshot.');
    } finally {
        uploading.value = false;
        const keep = { answer: form.answer, video_links: [...form.video_links] };
        await load();
        Object.assign(form, keep);
    }
};

const removeShot = async (shot) => {
    try {
        await api.delete(`/competency-evidence/${shot.id}`);
        current.value.screenshots = current.value.screenshots.filter(s => s.id !== shot.id);
    } catch (e) {
        formError.value = firstError(e, 'Could not remove the screenshot.');
    }
};

onMounted(load);
</script>

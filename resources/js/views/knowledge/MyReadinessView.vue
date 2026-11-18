<template>
    <div class="space-y-6">
        <router-link :to="{ name: 'Knowledge' }" class="text-sm text-gray-500 hover:text-gray-800">← Knowledge</router-link>

        <div>
            <h1 class="text-2xl font-bold text-gray-900">My readiness</h1>
            <p class="text-sm text-gray-500 mt-1">
                Practical checks that show you can do the job on each product. A reviewer reads your answer and passes it or sends it back with comments.
                <template v-if="plansLinked"> Required checks also appear in your daily plan.</template>
            </p>
        </div>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="w-8 h-8 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-4">{{ error }}</div>
        <div v-else-if="!products.length" class="text-sm text-gray-500 bg-white border border-gray-100 rounded-2xl p-8 text-center">
            There are no competency checks yet. When a product owner adds one, it appears here.
        </div>

        <div v-else class="space-y-4">
            <section v-for="p in products" :key="p.system.id" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="p-5 flex flex-col md:flex-row md:items-center gap-4 border-b border-gray-100">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-gray-900">{{ p.system.name }}</h2>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="levelClass(p.level)">{{ p.level_label }}</span>
                        </div>
                        <router-link :to="{ name: 'SystemDetail', params: { slug: p.system.slug }, query: { tab: 'manual' } }"
                                     class="text-sm text-teal-700 hover:underline">Read the manual</router-link>
                    </div>
                    <div class="grid grid-cols-2 gap-6 md:w-80">
                        <div v-if="p.score !== null">
                            <div class="text-xs text-gray-500 mb-1">Readiness</div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-teal-600 rounded-full" :style="{ width: p.score + '%' }"></div>
                            </div>
                            <div class="text-sm font-semibold text-gray-900 mt-1">{{ p.score }}%</div>
                        </div>
                        <div v-else>
                            <div class="text-xs text-gray-500 mb-1">Readiness</div>
                            <div class="text-sm text-gray-500">Not required for you</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 mb-1">Manual read</div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gray-400 rounded-full" :style="{ width: p.study + '%' }"></div>
                            </div>
                            <div class="text-sm font-semibold text-gray-900 mt-1">{{ p.study }}%</div>
                        </div>
                    </div>
                </div>

                <ul class="divide-y divide-gray-100">
                    <li v-for="c in p.checks" :key="c.id" class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900">{{ c.title }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ c.required ? 'Required' : 'Optional' }}
                                <template v-if="c.expires_at"> · valid until {{ formatDate(c.expires_at) }}</template>
                            </div>
                            <div v-if="c.review_comment" class="text-xs text-amber-800 bg-amber-50 rounded-lg px-2 py-1 mt-2 whitespace-pre-line">{{ c.review_comment }}</div>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full self-start sm:self-center" :class="statusClass(c.status)">{{ c.status_label }}</span>
                        <router-link :to="{ name: 'CompetencyCheck', params: { id: c.id } }"
                                     class="px-3 py-1.5 rounded-lg text-sm font-semibold shrink-0 text-center"
                                     :class="actionable(c.status) ? 'bg-teal-600 text-white hover:bg-teal-700' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'">
                            {{ actionLabel(c.status) }}
                        </router-link>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/axios';

const products = ref([]);
const plansLinked = ref(false);
const loading = ref(true);
const error = ref(null);

const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';

const levelClass = (level) => ({
    ready: 'bg-green-100 text-green-800',
    learning: 'bg-amber-100 text-amber-800',
    not_started: 'bg-red-100 text-red-700',
    not_required: 'bg-gray-100 text-gray-600',
}[level] || 'bg-gray-100 text-gray-600');

const statusClass = (s) => ({
    passed: 'bg-green-100 text-green-800',
    submitted: 'bg-blue-100 text-blue-800',
    changes_requested: 'bg-amber-100 text-amber-800',
    draft: 'bg-gray-100 text-gray-700',
    expired: 'bg-red-100 text-red-700',
    recheck: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700');

const actionable = (s) => ['todo', 'draft', 'changes_requested', 'expired', 'recheck'].includes(s);

const actionLabel = (s) => ({
    todo: 'Start',
    draft: 'Continue',
    changes_requested: 'Update',
    expired: 'Do it again',
    recheck: 'Do it again',
}[s] || 'View');

onMounted(async () => {
    try {
        const res = await api.get('/readiness');
        products.value = res.data.data;
        plansLinked.value = !!res.data.meta?.plans;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load your readiness.';
    } finally {
        loading.value = false;
    }
});
</script>

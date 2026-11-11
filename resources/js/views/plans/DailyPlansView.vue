<template>
    <div>
        <div class="mb-8 sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Your Daily Plans</h1>
                <p class="text-sm text-gray-500 mt-1">Manage what you are working on to align with your organization's goals.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <button @click.prevent.stop="openModal()" class="inline-flex items-center justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-teal-600 text-sm font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Task
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter by Date</label>
                <input type="date" v-model="filters.date" @change="fetchPlans" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500">
            </div>
            <button v-if="filters.date" @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700 py-1.5">
                Clear filter
            </button>
        </div>

        <!-- Plans List -->
        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
            <div v-if="loading" class="p-12 text-center text-gray-500">
                 Loading your plans...
            </div>
            
            <div v-else-if="plans.length === 0" class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5v2m6-2v2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No plans found</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new task.</p>
                <div class="mt-6">
                    <button @click.prevent.stop="openModal()" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-teal-700 bg-teal-100 hover:bg-teal-200 focus:outline-none">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Task
                    </button>
                </div>
            </div>

            <ul v-else class="divide-y divide-gray-200">
                <li v-for="plan in plans" :key="plan.id" class="p-6 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-xs font-semibold text-gray-500 tracking-wider mb-1">{{ plan.date }}</span>
                            <h4 class="text-base font-bold text-gray-900">{{ plan.task_name }}</h4>
                            <p v-if="plan.project_client" class="text-sm text-gray-600 mt-1 flex items-center">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ plan.project_client }}
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <!-- Badges -->
                            <div class="flex flex-col items-end gap-2">
                                <span v-if="plan.assigned_by && plan.assigned_by.name" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 uppercase tracking-wider shadow-sm">
                                    Assigned by {{ plan.assigned_by.name }}
                                </span>
                                <span :class="getStatusBadgeClass(plan.status.color)" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                                    {{ plan.status.label }}
                                </span>
                                <span :class="getPriorityBadgeClass(plan.priority.color)" class="px-2.5 py-0.5 rounded-full text-xs font-medium border border-transparent">
                                    {{ plan.priority.label }} Priority
                                </span>
                            </div>
                            
                            <div class="text-right ml-4 border-l border-gray-200 pl-4">
                                <div class="text-sm font-semibold text-gray-900">{{ plan.expected_duration_minutes }}m</div>
                                <div class="text-xs text-gray-500">Expected</div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center ml-4 gap-2">
                                <!-- Timer Controls -->
                                <div class="flex items-center gap-2 mr-2">
                                    <div class="text-xs font-mono text-gray-600 w-[64px] text-right">
                                        {{ formatElapsed(plan.id) }}
                                    </div>

                                    <button v-if="plan.timer?.status === 'idle' || plan.timer?.status === 'stopped'"
                                            @click="startTimer(plan)"
                                            class="p-1.5 text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors"
                                            title="Start timer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>

                                    <button v-if="plan.timer?.status === 'running'"
                                            @click="pauseTimer(plan)"
                                            class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                                            title="Pause timer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>

                                    <button v-if="plan.timer?.status === 'paused'"
                                            @click="resumeTimer(plan)"
                                            class="p-1.5 text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors"
                                            title="Resume timer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>

                                    <button v-if="plan.timer?.status === 'running' || plan.timer?.status === 'paused'"
                                            @click="stopTimer(plan)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Stop timer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h12v12H6z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>

                                <button v-if="plan.status?.value !== 'done'" @click="markAsDone(plan.id)" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Mark as Done">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button @click="openModal(plan)" class="p-1.5 text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button @click="deletePlan(plan.id)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="plan.notes" class="mt-3 text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-100 italic">
                        "{{ plan.notes }}"
                    </div>
                </li>
            </ul>
            
            <div v-if="pagination && pagination.last_page > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <!-- Simple pagination controls -->
                <div class="flex justify-between items-center text-sm text-gray-600">
                    <button :disabled="!pagination.prev" @click="fetchPlans(pagination.current - 1)" class="px-3 py-1 bg-white border border-gray-300 rounded-md disabled:opacity-50">Previous</button>
                    <span>Page {{ pagination.current }} of {{ pagination.last_page }}</span>
                    <button :disabled="!pagination.next" @click="fetchPlans(pagination.current + 1)" class="px-3 py-1 bg-white border border-gray-300 rounded-md disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>

        <DailyPlanModal v-if="isModalOpen" :plan-data="editingPlan" @close="closeModal" @saved="handlePlanSaved" />
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import api from '../../lib/axios';
import DailyPlanModal from '../../components/DailyPlanModal.vue';

const plans = ref([]);
const loading = ref(true);
const isModalOpen = ref(false);
const editingPlan = ref(null);
const activeIntervals = new Map();
const elapsedMap = reactive({}); // planId -> elapsed seconds

const filters = reactive({
    date: new Date().toISOString().split('T')[0] // Default to today
});

const pagination = reactive({
    current: 1,
    last_page: 1,
    next: null,
    prev: null
});

const fetchPlans = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/plans', { 
            params: { 
                page,
                date: filters.date || undefined 
            } 
        });
        const payload = response.data.data || response.data;
        plans.value = Array.isArray(payload) ? payload : [];
        
        if (response.data.meta) {
            pagination.current = response.data.meta.current_page;
            pagination.last_page = response.data.meta.last_page;
            pagination.next = response.data.links?.next;
            pagination.prev = response.data.links?.prev;
        } else {
            pagination.current = 1;
            pagination.last_page = 1;
        }

        // Initialize elapsed map for all plans and start ticking
        for (const plan of plans.value) {
            elapsedMap[plan.id] = totalElapsedSeconds(plan);
        }
        syncIntervals();
    } catch (err) {
        console.error("Failed to fetch plans", err);
    } finally {
        loading.value = false;
    }
};

const clearFilters = () => {
    filters.date = '';
    fetchPlans();
};

const deletePlan = async (id) => {
    if(confirm('Are you sure you want to delete this plan?')) {
        await api.delete(`/plans/${id}`);
        fetchPlans(pagination.current);
    }
};

const markAsDone = async (id) => {
    try {
        await api.patch(`/plans/${id}/complete`);
        fetchPlans(pagination.current);
    } catch (e) {
        console.error("Failed to mark as done", e);
    }
};

const openModal = (plan = null) => {
    editingPlan.value = plan instanceof Event ? null : plan;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingPlan.value = null;
};

const handlePlanSaved = () => {
    closeModal();
    fetchPlans(pagination.current);
};

const syncIntervals = () => {
    // Clear intervals for plans no longer running
    for (const [planId, intervalId] of activeIntervals) {
        const plan = plans.value.find(p => p.id === planId);
        if (!plan || plan?.timer?.status !== 'running') {
            clearInterval(intervalId);
            activeIntervals.delete(planId);
        }
    }

    // Start intervals for running plans
    for (const plan of plans.value) {
        if (plan?.timer?.status === 'running' && !activeIntervals.has(plan.id)) {
            const id = setInterval(() => {
                elapsedMap[plan.id] = totalElapsedSeconds(plan);
            }, 1000);
            activeIntervals.set(plan.id, id);
        }
    }
};

const totalElapsedSeconds = (plan) => {
    const base = (plan?.timer?.accumulated_seconds || 0);
    if (plan?.timer?.status === 'running' && plan?.timer?.started_at) {
        const started = new Date(plan.timer.started_at).getTime();
        const nowMs = Date.now();
        const seg = Math.max(0, Math.floor((nowMs - started) / 1000));
        return base + seg;
    }
    return base;
};

const formatElapsed = (planId) => {
    const secs = elapsedMap[planId] || 0;
    const h = Math.floor(secs / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = secs % 60;
    if (h > 0) {
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

const startTimer = async (plan) => {
    try {
        const res = await api.post(`/timers/${plan.id}/start`);
        await replacePlan(res.data.data);
    } catch (e) {
        alert(e.response?.data?.message || e.response?.data?.errors?.timer?.[0] || 'Failed to start timer');
    }
};

const pauseTimer = async (plan) => {
    try {
        const res = await api.post(`/timers/${plan.id}/pause`);
        await replacePlan(res.data.data);
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to pause timer');
    }
};

const resumeTimer = async (plan) => {
    try {
        const res = await api.post(`/timers/${plan.id}/resume`);
        await replacePlan(res.data.data);
    } catch (e) {
        alert(e.response?.data?.message || e.response?.data?.errors?.timer?.[0] || 'Failed to resume timer');
    }
};

const stopTimer = async (plan) => {
    try {
        const res = await api.post(`/timers/${plan.id}/stop`);
        await replacePlan(res.data.data);
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to stop timer');
    }
};

const replacePlan = async (updated) => {
    plans.value = plans.value.map(p => (p.id === updated.id ? updated : p));
    elapsedMap[updated.id] = totalElapsedSeconds(updated);
    syncIntervals();
};

// Styling helpers that map our PHP enum colors to Tailwind classes
const getStatusBadgeClass = (color) => {
    const map = {
        'success': 'bg-green-100 text-green-800',
        'warning': 'bg-yellow-100 text-yellow-800',
        'danger': 'bg-red-100 text-red-800',
        'info': 'bg-blue-100 text-blue-800',
    };
    return map[color] || 'bg-gray-100 text-gray-800';
};

const getPriorityBadgeClass = (color) => {
    const map = {
        'success': 'border-green-200 text-green-700 bg-green-50',
        'warning': 'border-yellow-200 text-yellow-700 bg-yellow-50',
        'danger': 'border-red-200 text-red-700 bg-red-50',
    };
    return map[color] || 'border-gray-200 text-gray-700 bg-gray-50';
};

onMounted(() => {
    fetchPlans();
});

onUnmounted(() => {
    // Clean up all intervals to prevent memory leaks
    for (const [, intervalId] of activeIntervals) {
        clearInterval(intervalId);
    }
    activeIntervals.clear();
});
</script>


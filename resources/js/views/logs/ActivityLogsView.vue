<template>
    <div>
        <div class="mb-8 sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Activity Logs</h1>
                <p class="text-sm text-gray-500 mt-1">Track exactly what you spent your time on today.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <button @click="openModal()" class="inline-flex items-center justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-teal-600 text-sm font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Log Activity
                </button>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter by Date</label>
                <input type="date" v-model="filters.date" @change="fetchLogs" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Work Type</label>
                <select v-model="filters.work_type" @change="fetchLogs" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="">All Types</option>
                    <option value="direct">Direct</option>
                    <option value="indirect">Indirect</option>
                    <option value="growth">Growth</option>
                </select>
            </div>
            <button v-if="filters.date || filters.work_type" @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700 py-1.5">
                Clear filters
            </button>
        </div>

        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
            <div v-if="loading" class="p-12 text-center text-gray-500">
                 Loading your logs...
            </div>
            
            <div v-else-if="logs.length === 0" class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No activity logged yet</h3>
                <p class="mt-1 text-sm text-gray-500">Log your first activity to start tracking time.</p>
                <div class="mt-6">
                    <button @click="openModal()" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-teal-700 bg-teal-100 hover:bg-teal-200 focus:outline-none">
                        New Activity
                    </button>
                </div>
            </div>

            <ul v-else class="divide-y divide-gray-200">
                <li v-for="log in logs" :key="log.id" class="p-6 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <div class="flex items-center mb-1">
                                <span class="text-xs font-semibold text-gray-500 tracking-wider mr-2">{{ log.date }}</span>
                                <span v-if="log.daily_plan_id" class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full border border-blue-200 font-semibold flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    Planned
                                </span>
                                <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full border border-gray-200 font-semibold flex items-center">
                                     Unplanned
                                </span>
                            </div>
                            
                            <h4 class="text-base font-bold text-gray-900">{{ log.task_name }}</h4>
                            
                            <div class="flex gap-4 mt-2">
                                <span :class="getWorkTypeBadge(log.work_type.color)" class="px-2 py-0.5 rounded text-xs font-medium border border-transparent shadow-sm">
                                    Type: {{ log.work_type.label }}
                                </span>
                                <span :class="getCompletionBadge(log.completion_type.color)" class="px-2 py-0.5 rounded text-xs font-medium border border-transparent shadow-sm">
                                    {{ log.completion_type.label }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div class="text-right bg-teal-50 p-3 rounded-xl border border-teal-100">
                                <div class="text-lg font-bold text-teal-700">{{ log.duration_minutes }} <span class="text-xs font-normal text-teal-500">mins</span></div>
                                <div v-if="log.start_time && log.end_time" class="text-xs text-teal-500">
                                    {{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center ml-4 gap-2 border-l border-gray-200 pl-4">
                                <button @click="openModal(log)" class="p-1.5 text-gray-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button @click="deleteLog(log.id)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="log.output" class="mt-4 text-sm text-gray-700 bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
                        <span class="font-semibold text-gray-900 block mb-1">Deliverable / Output:</span>
                        {{ log.output }}
                    </div>
                </li>
            </ul>
            
            <div v-if="pagination && pagination.last_page > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex justify-between items-center text-sm text-gray-600">
                    <button :disabled="!pagination.prev" @click="fetchLogs(pagination.current - 1)" class="px-3 py-1 bg-white border border-gray-300 rounded-md disabled:opacity-50">Previous</button>
                    <span>Page {{ pagination.current }} of {{ pagination.last_page }}</span>
                    <button :disabled="!pagination.next" @click="fetchLogs(pagination.current + 1)" class="px-3 py-1 bg-white border border-gray-300 rounded-md disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>

        <ActivityLogModal v-if="isModalOpen" :log-data="editingLog" @close="closeModal" @saved="handleLogSaved" />
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../lib/axios';
import ActivityLogModal from '../../components/ActivityLogModal.vue';

const logs = ref([]);
const loading = ref(true);
const isModalOpen = ref(false);
const editingLog = ref(null);

const filters = reactive({
    date: new Date().toISOString().split('T')[0],
    work_type: ''
});

const pagination = reactive({
    current: 1,
    last_page: 1,
    next: null,
    prev: null
});

const fetchLogs = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/logs', { 
            params: { 
                page,
                date: filters.date || undefined,
                work_type: filters.work_type || undefined
            } 
        });
        const payload = response.data.data || response.data;
        logs.value = Array.isArray(payload) ? payload : [];
        
        if (response.data.meta) {
            pagination.current = response.data.meta.current_page;
            pagination.last_page = response.data.meta.last_page;
            pagination.next = response.data.links?.next;
            pagination.prev = response.data.links?.prev;
        } else {
            pagination.current = 1;
            pagination.last_page = 1;
        }
    } catch (err) {
        console.error("Failed to fetch logs", err);
    } finally {
        loading.value = false;
    }
};

const clearFilters = () => {
    filters.date = '';
    filters.work_type = '';
    fetchLogs();
};

const deleteLog = async (id) => {
    if(confirm('Are you sure you want to delete this activity log?')) {
        await api.delete(`/logs/${id}`);
        fetchLogs(pagination.current);
    }
};

const openModal = (log = null) => {
    editingLog.value = log;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingLog.value = null;
};

const handleLogSaved = () => {
    closeModal();
    fetchLogs(pagination.current);
};

// Utils
const formatTime = (timeString) => {
    if(!timeString) return '';
    return timeString.substring(0, 5); // "HH:MM:SS" to "HH:MM"
};

const getWorkTypeBadge = (color) => {
    const map = {
        'success': 'bg-green-100 text-green-800',
        'warning': 'bg-orange-100 text-orange-800', 
        'info': 'bg-blue-100 text-blue-800',
    };
    return map[color] || 'bg-gray-100 text-gray-800';
};

const getCompletionBadge = (color) => {
    const map = {
        'success': 'bg-emerald-100 text-emerald-800',
        'warning': 'bg-amber-100 text-amber-800',
        'danger': 'bg-rose-100 text-rose-800',
    };
    return map[color] || 'bg-gray-100 text-gray-800';
};

onMounted(() => {
    fetchLogs();
});
</script>


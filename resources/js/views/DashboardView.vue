<template>
    <div>
        <CarryOverModal
            v-if="sessionStore.carryOvers?.length"
            :plans="sessionStore.carryOvers"
            @close="sessionStore.carryOvers = []"
            @resolve="handleResolveCarryOver"
        />

        <!-- Attendance banner -->
        <div class="mb-4 p-4 rounded-2xl border shadow-sm"
             :class="sessionStore.isClockedIn ? 'bg-emerald-50 border-emerald-200' : (sessionStore.isSystemClosed ? 'bg-amber-50 border-amber-200' : 'bg-white border-gray-100')">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">
                        <span v-if="sessionStore.isClockedIn">You are clocked in.</span>
                        <span v-else-if="sessionStore.isSystemClosed">Your session was auto-closed.</span>
                        <span v-else>Clock in to unlock plans and timers.</span>
                    </div>
                    <div class="text-xs text-gray-600 mt-0.5" v-if="sessionStore.currentSession?.clock_in">
                        Started at {{ new Date(sessionStore.currentSession.clock_in).toLocaleTimeString() }}
                    </div>
                    <div v-if="sessionStore.error" class="text-xs text-red-600 mt-1">
                        {{ sessionStore.error }}
                    </div>
                </div>

                <div class="flex gap-2">
                    <button v-if="sessionStore.isSystemClosed"
                            @click="handleRequestReopen"
                            :disabled="sessionStore.loading"
                            class="inline-flex items-center justify-center rounded-xl px-4 py-2 bg-amber-600 text-sm font-semibold text-white hover:bg-amber-700 shadow-md shadow-amber-200 transition-all disabled:opacity-60">
                        Request Reopen
                    </button>
                    <button v-else-if="!sessionStore.isClockedIn"
                            @click="handleClockIn"
                            :disabled="sessionStore.loading"
                            class="inline-flex items-center justify-center rounded-xl px-4 py-2 bg-teal-600 text-sm font-semibold text-white hover:bg-teal-700 shadow-md shadow-teal-200 transition-all disabled:opacity-60">
                        Clock In
                    </button>
                    <button v-else
                            @click="handleClockOut"
                            :disabled="sessionStore.loading"
                            class="inline-flex items-center justify-center rounded-xl px-4 py-2 bg-gray-900 text-sm font-semibold text-white hover:bg-black transition-all disabled:opacity-60">
                        Clock Out
                    </button>
                </div>
            </div>
        </div>

        <!-- Dashboard Header -->
        <div class="mb-8 p-6 bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col md:flex-row md:items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Welcome back, {{ authStore.user?.name }}</h1>
                <p class="text-sm text-gray-500 mt-1 capitalize">{{ authStore.user?.roles?.[0]?.name?.replace('_', ' ') || 'Worker' }} • {{ authStore.user?.department?.name || authStore.user?.organisation?.name }}</p>
            </div>
            
            <!-- Quick Actions (Worker Level Action) -->
            <div class="mt-4 md:mt-0 flex gap-3">
                <router-link to="/plans"
                             :class="sessionStore.isClockedIn ? 'inline-flex items-center justify-center rounded-xl border-2 border-teal-50 px-4 py-2 bg-white text-sm font-semibold text-teal-600 hover:bg-teal-50 hover:border-teal-100 transition-all shadow-sm' : 'inline-flex items-center justify-center rounded-xl border-2 border-gray-100 px-4 py-2 bg-gray-50 text-sm font-semibold text-gray-400 cursor-not-allowed'">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5v2m6-2v2"/>
                    </svg>
                    Daily Plans
                </router-link>
                <router-link to="/logs"
                             :class="sessionStore.isClockedIn ? 'inline-flex items-center justify-center rounded-xl px-4 py-2 bg-teal-600 text-sm font-semibold text-white hover:bg-teal-700 shadow-md shadow-teal-200 transition-all' : 'inline-flex items-center justify-center rounded-xl px-4 py-2 bg-gray-200 text-sm font-semibold text-gray-500 cursor-not-allowed'">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Activity Logs
                </router-link>
            </div>
        </div>

        <!-- Filters / Week Selector -->
        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-900">Performance Overview</h2>
            <div class="flex items-center space-x-3 bg-white p-1 rounded-lg border border-gray-200 shadow-sm">
                <button @click="changeWeek(-1)" class="p-1.5 rounded bg-white text-gray-400 hover:text-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="text-sm font-semibold text-gray-700 tracking-wide px-2 font-mono">
                    {{ weekLabel }}
                </div>
                <button @click="changeWeek(1)" :disabled="isCurrentWeek" class="p-1.5 rounded transition" :class="isCurrentWeek ? 'text-gray-200' : 'bg-white text-gray-400 hover:text-gray-700'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>

        <div v-if="loading" class="py-20 flex justify-center items-center text-gray-400">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading metrics...
        </div>

        <div v-else class="space-y-8">
            
            <!-- ====== PERSONAL STATS (TEAL) ====== -->
            <div v-if="stats.personal" class="space-y-4">
                <div class="flex items-center text-teal-600 mb-2">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <h3 class="font-bold uppercase tracking-wider text-sm">Personal Focus</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Metric Card -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-teal-100 flex flex-col justify-between group hover:shadow-md transition-all">
                        <div class="text-teal-500 text-xs font-bold uppercase tracking-widest mb-1">Total Time Tracked</div>
                        <div class="flex items-end gap-2">
                            <div class="text-4xl font-black text-gray-900 group-hover:text-teal-600 transition-colors">{{ Math.floor(stats.personal.total_minutes / 60) }}<span class="text-xl text-gray-400 font-semibold ml-1">hrs</span></div>
                            <div class="text-4xl font-black text-gray-900 group-hover:text-teal-600 transition-colors">{{ stats.personal.total_minutes % 60 }}<span class="text-xl text-gray-400 font-semibold ml-1">m</span></div>
                        </div>
                    </div>
                    
                    <!-- Metric Card -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-teal-100 flex flex-col justify-between group hover:shadow-md transition-all">
                        <div class="text-teal-500 text-xs font-bold uppercase tracking-widest mb-1">Execution Rate</div>
                        <div class="text-4xl font-black text-gray-900 group-hover:text-teal-600 transition-colors">
                            {{ stats.personal.planner_stats.execution_rate }}<span class="text-2xl text-gray-400 font-semibold ml-1">%</span>
                        </div>
                        <div class="text-sm text-gray-500 mt-2 font-medium">{{ stats.personal.planner_stats.completed_planned }} out of {{ stats.personal.planner_stats.total_planned }} planned tasks done</div>
                    </div>

                    <!-- Metric Card (Work Breakdown) -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-teal-100 group hover:shadow-md transition-all">
                        <div class="text-teal-500 text-xs font-bold uppercase tracking-widest mb-4">Work Type Breakdown</div>
                        <div class="space-y-3">
                            <div>
                                <div class="flex flex-row justify-between text-xs font-semibold mb-1"><span class="text-gray-700">Direct Work</span> <span class="text-teal-600">{{ formatMins(stats.personal.work_breakdown.direct) }}</span></div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                     <div class="bg-teal-500 h-full rounded-full" :style="{ width: getPercentage(stats.personal.work_breakdown.direct, stats.personal.total_minutes) + '%' }"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex flex-row justify-between text-xs font-semibold mb-1"><span class="text-gray-700">Indirect Work</span> <span class="text-gray-500">{{ formatMins(stats.personal.work_breakdown.indirect) }}</span></div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                     <div class="bg-gray-400 h-full rounded-full" :style="{ width: getPercentage(stats.personal.work_breakdown.indirect, stats.personal.total_minutes) + '%' }"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex flex-row justify-between text-xs font-semibold mb-1"><span class="text-gray-700">Growth / Training</span> <span class="text-emerald-500">{{ formatMins(stats.personal.work_breakdown.growth) }}</span></div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                     <div class="bg-emerald-400 h-full rounded-full" :style="{ width: getPercentage(stats.personal.work_breakdown.growth, stats.personal.total_minutes) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin/Super Admin: View detailed team stats in the Admin Panel -->
            <div v-if="stats.department || stats.organisation" class="mt-8 p-4 bg-gray-50 rounded-xl border border-gray-200 text-center">
                <p class="text-sm text-gray-500">
                    <svg class="w-4 h-4 inline mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Department and organisation-level stats are now available in the 
                    <a href="/admin" class="text-teal-600 font-semibold hover:underline">Admin Panel Dashboard</a>.
                </p>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useSessionStore } from '../stores/session';
import api from '../lib/axios';
import CarryOverModal from '../components/CarryOverModal.vue';

const authStore = useAuthStore();
const sessionStore = useSessionStore();
const stats = ref({});
const weekLabel = ref('');
const loading = ref(true);

const currentOffset = ref(0); // 0 = this week, -1 = last week

const isCurrentWeek = computed(() => currentOffset.value === 0);

const fetchDashboardStats = async () => {
    loading.value = true;
    
    // Calculate the target week start date (Monday) based on offset
    const today = new Date();
    const day = today.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
    const diff = day === 0 ? -6 : 1 - day; // Distance from today back to Monday
    const monday = new Date(today);
    monday.setDate(today.getDate() + diff + (currentOffset.value * 7));
    
    const yyyy = monday.getFullYear();
    const mm = String(monday.getMonth() + 1).padStart(2, '0');
    const dd = String(monday.getDate()).padStart(2, '0');
    const formattedDate = `${yyyy}-${mm}-${dd}`;

    try {
        const response = await api.get('/dashboard/stats/weekly', { params: { week_start: formattedDate } });
        stats.value = response.data;
        weekLabel.value = response.data.week_label;
    } catch (err) {
        console.error("Failed to load dashboard metrics", err);
    } finally {
        loading.value = false;
    }
};

const changeWeek = (offset) => {
    currentOffset.value += offset;
    // Don't allow navigating to FUTURE weeks
    if (currentOffset.value > 0) {
        currentOffset.value = 0;
    }
    fetchDashboardStats();
};

const formatMins = (minutes) => {
    if (!minutes) return '0h';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return (h > 0 ? h + 'h ' : '') + m + 'm';
};

const getPercentage = (value, total) => {
    if (!total || !value) return 0;
    return Math.round((value / total) * 100);
};

onMounted(() => {
    sessionStore.fetchCurrentSession();
    sessionStore.fetchPendingCarryOvers();
    fetchDashboardStats();
});

const handleClockIn = async () => {
    await sessionStore.clockIn();
};

const handleClockOut = async () => {
    await sessionStore.clockOut();
};

const handleRequestReopen = async () => {
    const reason = window.prompt('Please enter the reason for requesting a session reopen:');
    if (!reason || reason.trim().length < 5) {
        alert('A valid reason (at least 5 characters) is required.');
        return;
    }

    const success = await sessionStore.requestReopen(reason);
    if (success) {
        alert('Your request has been submitted to the admin.');
    }
};

const handleResolveCarryOver = async ({ plan, decision, priority }) => {
    try {
        await sessionStore.resolveCarryOver(plan.id, decision, decision === 'carry_over' ? priority : null);
        sessionStore.carryOvers = sessionStore.carryOvers.filter(p => p.id !== plan.id);
    } catch (e) {
        // keep modal open; error surfaces in console for now
        console.error('Failed to resolve carry over', e);
    }
};
</script>

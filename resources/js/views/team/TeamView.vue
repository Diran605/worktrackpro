<template>
    <div>
        <div class="mb-8 sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Team Directory</h1>
                <p class="text-sm text-gray-500 mt-1">View and manage your organisation's team members.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
                <input type="text" v-model="filters.search" @input="debouncedFetch" placeholder="Name or email..."
                       class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500">
            </div>
            <div v-if="departments.length > 0">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Department</label>
                <select v-model="filters.department_id" @change="fetchTeam" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="">All Departments</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }} ({{ dept.users_count }})</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Role</label>
                <select v-model="filters.role" @change="fetchTeam" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500 bg-white">
                    <option value="">All Roles</option>
                    <option value="super_admin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="worker">Worker</option>
                </select>
            </div>
        </div>

        <!-- Team Grid -->
        <div v-if="loading" class="py-20 flex justify-center items-center text-gray-400">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading team...
        </div>

        <div v-else-if="members.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No team members found</h3>
            <p class="mt-1 text-sm text-gray-500">Try adjusting your filters.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div v-for="member in members" :key="member.id" 
                 class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-all group relative overflow-hidden">
                
                <!-- Subtle role-colored accent bar at top -->
                <div class="absolute top-0 left-0 right-0 h-1" :class="getRoleAccent(member.roles)"></div>

                <div class="flex items-start gap-4">
                    <!-- Avatar -->
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold border-2 shrink-0"
                         :class="getAvatarStyle(member.roles)">
                        {{ member.name?.charAt(0) || '?' }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-bold text-gray-900 truncate">{{ member.name }}</h4>
                            <span v-if="!member.is_active" class="px-1.5 py-0.5 bg-gray-100 text-gray-400 text-xs rounded-full font-medium border border-gray-200">Inactive</span>
                        </div>
                        <p class="text-xs text-gray-500 truncate mt-0.5">{{ member.email }}</p>
                        
                        <div class="flex items-center gap-2 mt-3">
                            <!-- Role badge -->
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="getRoleBadge(member.roles)">
                                {{ formatRole(member.roles) }}
                            </span>
                            <!-- Department badge -->
                            <span v-if="member.department" class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                {{ member.department.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Last login & actions -->
                <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-xs text-gray-400">
                        <span v-if="member.last_login_at">Last login: {{ formatDate(member.last_login_at) }}</span>
                        <span v-else class="italic">Never logged in</span>
                    </div>
                    
                    <!-- Toggle active (Super Admin only) -->
                    <button v-if="canManageUsers && member.id !== currentUserId"
                            @click="toggleUserStatus(member)"
                            class="text-xs px-2 py-1 rounded-lg transition-colors font-medium"
                            :class="member.is_active 
                                ? 'text-red-500 hover:bg-red-50 hover:text-red-700' 
                                : 'text-teal-500 hover:bg-teal-50 hover:text-teal-700'">
                        {{ member.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="mt-8 flex justify-center">
            <div class="flex items-center space-x-2 bg-white rounded-lg border border-gray-200 shadow-sm p-1">
                <button :disabled="pagination.current <= 1" @click="fetchTeam(pagination.current - 1)" class="px-3 py-1.5 text-sm rounded-md disabled:opacity-40 hover:bg-gray-50 text-gray-600 font-medium">Previous</button>
                <span class="px-3 py-1.5 text-sm text-gray-500">Page {{ pagination.current }} of {{ pagination.last_page }}</span>
                <button :disabled="pagination.current >= pagination.last_page" @click="fetchTeam(pagination.current + 1)" class="px-3 py-1.5 text-sm rounded-md disabled:opacity-40 hover:bg-gray-50 text-gray-600 font-medium">Next</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../lib/axios';

const authStore = useAuthStore();
const members = ref([]);
const departments = ref([]);
const loading = ref(true);

const currentUserId = computed(() => authStore.user?.id);
const canManageUsers = computed(() => {
    const roles = authStore.user?.roles || [];
    return roles.includes('super_admin') || (Array.isArray(roles) && roles.some(r => r.name === 'super_admin' || r === 'super_admin'));
});

const filters = reactive({
    search: '',
    department_id: '',
    role: ''
});

const pagination = reactive({
    current: 1,
    last_page: 1,
});

let debounceTimer = null;
const debouncedFetch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchTeam(), 300);
};

const fetchTeam = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/team', {
            params: {
                page,
                search: filters.search || undefined,
                department_id: filters.department_id || undefined,
                role: filters.role || undefined
            }
        });
        const payload = response.data.data || response.data;
        members.value = Array.isArray(payload) ? payload : [];

        if (response.data.meta) {
            pagination.current = response.data.meta.current_page;
            pagination.last_page = response.data.meta.last_page;
        }
    } catch (err) {
        console.error("Failed to fetch team", err);
    } finally {
        loading.value = false;
    }
};

const fetchDepartments = async () => {
    try {
        const response = await api.get('/team/departments');
        departments.value = response.data || [];
    } catch (err) {
        console.error("Failed to fetch departments", err);
    }
};

const toggleUserStatus = async (member) => {
    const action = member.is_active ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} ${member.name}?`)) return;

    try {
        const response = await api.patch(`/team/${member.id}/toggle-status`);
        const idx = members.value.findIndex(m => m.id === member.id);
        if (idx !== -1) {
            members.value[idx] = response.data.data || response.data;
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to update user status.');
    }
};

// Styling helpers
const getRoleAccent = (roles) => {
    const role = getPrimaryRole(roles);
    if (role === 'super_admin') return 'bg-indigo-500';
    if (role === 'admin') return 'bg-blue-500';
    return 'bg-teal-500';
};

const getAvatarStyle = (roles) => {
    const role = getPrimaryRole(roles);
    if (role === 'super_admin') return 'bg-indigo-100 text-indigo-700 border-indigo-200';
    if (role === 'admin') return 'bg-blue-100 text-blue-700 border-blue-200';
    return 'bg-teal-100 text-teal-700 border-teal-200';
};

const getRoleBadge = (roles) => {
    const role = getPrimaryRole(roles);
    if (role === 'super_admin') return 'bg-indigo-100 text-indigo-700 border border-indigo-200';
    if (role === 'admin') return 'bg-blue-100 text-blue-700 border border-blue-200';
    return 'bg-teal-100 text-teal-700 border border-teal-200';
};

const getPrimaryRole = (roles) => {
    if (!roles || roles.length === 0) return 'worker';
    const r = roles[0];
    return typeof r === 'string' ? r : r?.name || 'worker';
};

const formatRole = (roles) => {
    const role = getPrimaryRole(roles);
    return role.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

onMounted(() => {
    fetchTeam();
    fetchDepartments();
});
</script>

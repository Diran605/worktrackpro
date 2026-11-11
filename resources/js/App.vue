<template>
    <div class="h-screen w-full flex bg-gray-50 selection:bg-teal-500 selection:text-white overflow-hidden" :style="orgColorVars">
        
        <!-- Sidebar Navigation -->
        <aside v-if="authStore.isAuthenticated" class="w-64 shrink-0 bg-white border-r border-gray-200 flex-col h-full shadow-sm relative z-20 transition-all duration-300 hidden md:flex">
            <!-- Brand / Org Name -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold shadow-sm mr-3" :style="{ backgroundColor: orgPrimaryColor }">
                    {{ orgInitial }}
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-gray-900 tracking-tight leading-tight">{{ orgName }}</span>
                    <span class="text-xs text-gray-400 leading-tight">WorkTrack Pro</span>
                </div>
            </div>
            
            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto py-6 px-4 space-y-1">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 px-3">Main Menu</div>
                
                <router-link :to="{ name: 'Dashboard' }" active-class="bg-teal-50 text-teal-700 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'Dashboard' ? 'text-teal-600' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </router-link>

                <router-link :to="{ name: 'DailyPlans' }" active-class="bg-teal-50 text-teal-700 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'DailyPlans' ? 'text-teal-600' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5v2m6-2v2"/>
                    </svg>
                    Daily Plans
                </router-link>

                <router-link :to="{ name: 'ActivityLogs' }" active-class="bg-teal-50 text-teal-700 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'ActivityLogs' ? 'text-teal-600' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Activity Logs
                </router-link>

                <router-link :to="{ name: 'Inbox' }" active-class="bg-teal-50 text-teal-700 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'Inbox' ? 'text-teal-600' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0l-2 6H8l-2-6m16 0H4" />
                    </svg>
                    <span class="flex-1">Inbox</span>
                    <span v-if="inboxStore.unreadCount > 0" class="ml-2 text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">
                        {{ inboxStore.unreadCount }}
                    </span>
                </router-link>

                <router-link :to="{ name: 'RecurringTasks' }" active-class="bg-teal-50 text-teal-700 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'RecurringTasks' ? 'text-teal-600' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Recurring Tasks
                </router-link>

                <div v-if="isAdmin">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-6 mb-4 px-3">Management</div>

                    <router-link :to="{ name: 'Team' }" active-class="bg-blue-50 text-blue-700 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                        <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'Team' ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Team Directory
                    </router-link>

                    <!-- Admin Command Center Link (External to SPA) -->
                    <a href="/admin" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all mt-1">
                        <svg class="w-5 h-5 mr-3 shrink-0 text-gray-400 group-hover:text-indigo-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Command Center
                    </a>
                </div>

                <!-- Settings -->
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-6 mb-4 px-3">Account</div>
                <router-link :to="{ name: 'Settings' }" active-class="bg-gray-100 text-gray-900 font-semibold" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors" :class="$route.name === 'Settings' ? 'text-gray-700' : 'text-gray-400 group-hover:text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Settings
                </router-link>
            </div>

            <!-- Bottom User Profile Area -->
            <div class="shrink-0 p-4 border-t border-gray-100 flex items-center group">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold border" :style="{ backgroundColor: orgPrimaryColor, borderColor: orgPrimaryColor + '40' }">
                    {{ authStore.user?.name?.charAt(0) || 'U' }}
                </div>
                <div class="ml-3 flex-1 overflow-hidden">
                    <p class="text-sm font-bold text-gray-900 truncate">{{ authStore.user?.name || 'User' }}</p>
                    <p class="text-xs text-gray-500 truncate capitalize">{{ authStore.user?.roles?.[0]?.replace('_', ' ') || 'Worker' }}</p>
                </div>
                
                <!-- Notification Bell -->
                <button class="ml-1 p-2 text-gray-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-colors relative" title="Notifications">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span v-if="unreadCount > 0" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full"></span>
                </button>

                <button @click="handleLogout" class="ml-1 p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Log out">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Mobile Header (Visible only on small screens) -->
        <header v-if="authStore.isAuthenticated" class="md:hidden bg-white border-b border-gray-200 w-full h-16 flex items-center justify-between px-4 fixed top-0 z-30">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold shadow-sm mr-3" :style="{ backgroundColor: orgPrimaryColor }">{{ orgInitial }}</div>
                <span class="text-lg font-bold text-gray-900 tracking-tight">{{ orgName }}</span>
            </div>
             <nav class="flex items-center space-x-4">
                <router-link :to="{ name: 'Dashboard' }" class="text-gray-500 hover:text-teal-600 font-medium">Dash</router-link>
                <router-link :to="{ name: 'DailyPlans' }" class="text-gray-500 hover:text-teal-600 font-medium">Plans</router-link>
                <router-link :to="{ name: 'ActivityLogs' }" class="text-gray-500 hover:text-teal-600 font-medium">Logs</router-link>
                <router-link :to="{ name: 'RecurringTasks' }" class="text-gray-500 hover:text-teal-600 font-medium">Recurring</router-link>
                <router-link v-if="isAdmin" :to="{ name: 'Team' }" class="text-gray-500 hover:text-blue-600 font-medium">Team</router-link>
                <router-link :to="{ name: 'Settings' }" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </router-link>
                <button @click="handleLogout" class="text-gray-400 hover:text-red-500 transition-colors ml-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
             </nav>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto w-full h-full bg-gray-50 relative" :class="authStore.isAuthenticated ? 'pt-16 md:pt-0' : ''">
            <div class="h-full w-full max-w-7xl mx-auto p-4 md:p-8">
                <router-view />
            </div>
        </main>

    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useAuthStore } from './stores/auth';
import { useRoute, useRouter } from 'vue-router';
import api from './lib/axios';
import { useInboxStore } from './stores/inbox';

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

const unreadCount = ref(0);
const inboxStore = useInboxStore();

const isAdmin = computed(() => {
    const roles = authStore.user?.roles || [];
    const perms = authStore.user?.permissions || [];
    return roles.includes('super_admin') || perms.includes('manage_users') || perms.includes('view_team_stats');
});

const orgName = computed(() => authStore.user?.organisation?.name || 'WorkTrack Pro');
const orgInitial = computed(() => orgName.value.charAt(0).toUpperCase());
const orgPrimaryColor = computed(() => authStore.user?.organisation?.primary_color || '#0d9488');

const orgColorVars = computed(() => ({
    '--org-primary': orgPrimaryColor.value,
    '--org-secondary': authStore.user?.organisation?.secondary_color || '#6366f1',
}));

const fetchNotifications = async () => {
    if (!authStore.isAuthenticated) return;
    try {
        const response = await api.get('/notifications');
        unreadCount.value = response.data.unread_count;
    } catch (e) {
        console.error('Failed to fetch notifications', e);
    }
};

onMounted(() => {
    fetchNotifications();
    setInterval(fetchNotifications, 60000); // Check every minute

    inboxStore.fetchUnreadCount();
    setInterval(() => inboxStore.fetchUnreadCount(), 60000);
});

const handleLogout = async () => {
    await authStore.logout();
    router.push('/login');
};
</script>

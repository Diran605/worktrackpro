import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import { useSessionStore } from './stores/session';

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('./views/auth/LoginView.vue'),
        meta: { guestOnly: true }
    },
    {
        // "Staff sign in" from the documentation library (?return=<library path>)
        path: '/library',
        name: 'LibrarySignIn',
        component: () => import('./views/auth/LibrarySignInView.vue'),
    },
    {
        path: '/',
        name: 'Dashboard',
        component: () => import('./views/DashboardView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/plans',
        name: 'DailyPlans',
        component: () => import('./views/plans/DailyPlansView.vue'),
        meta: { requiresAuth: true, module: 'work_tracking' }
    },
    {
        path: '/logs',
        name: 'ActivityLogs',
        component: () => import('./views/logs/ActivityLogsView.vue'),
        meta: { requiresAuth: true, module: 'work_tracking' }
    },
    {
        path: '/team',
        name: 'Team',
        component: () => import('./views/team/TeamView.vue'),
        meta: { requiresAuth: true, module: 'work_tracking' }
    },
    {
        path: '/settings',
        name: 'Settings',
        component: () => import('./views/settings/SettingsView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/inbox',
        name: 'Inbox',
        component: () => import('./views/inbox/InboxView.vue'),
        meta: { requiresAuth: true }
    },
    // Knowledge base
    {
        path: '/knowledge',
        name: 'Knowledge',
        component: () => import('./views/knowledge/SystemsView.vue'),
        meta: { requiresAuth: true, module: 'knowledge' }
    },
    {
        path: '/knowledge/contributions',
        name: 'MyContributions',
        component: () => import('./views/knowledge/MyContributionsView.vue'),
        meta: { requiresAuth: true, module: 'knowledge' }
    },
    {
        path: '/knowledge/:slug',
        name: 'SystemDetail',
        component: () => import('./views/knowledge/SystemDetailView.vue'),
        meta: { requiresAuth: true, module: 'knowledge' }
    },
    {
        path: '/knowledge/:slug/read/:sectionId',
        name: 'ManualReader',
        component: () => import('./views/knowledge/ManualReaderView.vue'),
        meta: { requiresAuth: true, module: 'knowledge' }
    },
    {
        // ?section=<id> edits a section, ?change=<id> continues a draft, neither starts a new section
        path: '/knowledge/:slug/write',
        name: 'ManualEditor',
        component: () => import('./views/knowledge/ManualEditorView.vue'),
        meta: { requiresAuth: true, module: 'knowledge' }
    },
    {
        path: '/knowledge/:slug/upload',
        name: 'ManualUpload',
        component: () => import('./views/knowledge/ManualUploadView.vue'),
        meta: { requiresAuth: true, module: 'knowledge' }
    },
    {
        path: '/recurring-tasks',
        name: 'RecurringTasks',
        component: () => import('./views/recurring/RecurringTasksView.vue'),
        meta: { requiresAuth: true, module: 'work_tracking' }
    }
];

const router = createRouter({
    // The portal lives under /staff (the public docs library has the front of the site).
    history: createWebHistory('/staff/'),
    routes,
});

// Global authentication navigation guard
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();
    const sessionStore = useSessionStore();
    
    // If we have a token but no user object, fetch it
    if (authStore.token && !authStore.user) {
        await authStore.fetchUser();
    }
    
    const isAuthenticated = authStore.isAuthenticated;
    
    // Check if route requires auth but user isn't logged in
    if (to.meta.requiresAuth && !isAuthenticated) {
        // Come back to the requested page after signing in.
        next({ name: 'Login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} });
    }
    // Check if route is guest-only (like login) but user IS authenticated
    else if (to.meta.guestOnly && isAuthenticated) {
        next(safeRedirect(to.query.redirect) || { name: 'Dashboard' });
    }
    else {
        // Pages of a module the organisation doesn't have
        if (isAuthenticated && to.meta.module && !authStore.hasModule(to.meta.module)) {
            return next({ name: 'Dashboard' });
        }

        // Attendance gate: block plans/logs until clocked in (only for organisations that clock in)
        if (isAuthenticated && authStore.user?.attendance_linked && (to.name === 'DailyPlans' || to.name === 'ActivityLogs')) {
            if (!sessionStore.currentSession && !sessionStore.loading) {
                await sessionStore.fetchCurrentSession();
            }
            if (!sessionStore.isClockedIn) {
                return next({ name: 'Dashboard' });
            }
        }

        next();
    }
});

/** A ?redirect= value that stays inside the portal, or null. */
export function safeRedirect(value) {
    const path = typeof value === 'string' ? value : '';
    return path.startsWith('/') && !path.startsWith('//') ? path : null;
}

export default router;

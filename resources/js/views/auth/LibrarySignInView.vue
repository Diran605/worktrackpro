<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl shadow-indigo-100 border border-gray-100 text-center">
            <div class="mx-auto w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center text-white text-2xl font-bold">W</div>
            <h1 class="mt-5 text-xl font-bold text-gray-900">Documentation library</h1>

            <template v-if="authStore.user">
                <p class="mt-2 text-sm text-gray-600">You are signed in to WorkTrack Pro as</p>
                <p class="mt-1 font-semibold text-gray-900">{{ authStore.user.name }}</p>
                <p class="text-xs text-gray-500">{{ authStore.user.email }}</p>

                <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

                <div class="mt-6 flex flex-col gap-2">
                    <button @click="continueToLibrary" :disabled="working"
                            class="w-full py-2.5 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60">
                        {{ working ? 'Opening the library…' : `Continue as ${authStore.user.name}` }}
                    </button>
                    <button @click="switchAccount" :disabled="working"
                            class="w-full py-2.5 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-60">
                        Sign in as someone else
                    </button>
                    <a :href="returnPath" class="mt-2 text-xs text-gray-500 hover:underline">Back to the library without signing in</a>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';

/**
 * "Staff sign in" from the documentation library lands here. The portal may already be signed in
 * (it remembers you for days), so ask who should continue, then return to the library page
 * signed in there too.
 */
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const working = ref(false);
const error = ref(null);

// Only pages on this site.
const returnPath = computed(() => {
    const value = String(route.query.return || '/');
    return value.startsWith('/') && !value.startsWith('//') ? value : '/';
});

const loginRoute = () => ({ name: 'Login', query: { redirect: route.fullPath } });

const continueToLibrary = async () => {
    working.value = true;
    error.value = null;
    try {
        const res = await api.post('/auth/docs-link', { path: returnPath.value });
        window.location.replace(res.data.url);
    } catch (e) {
        error.value = 'Could not sign you in to the library. Please try again.';
        working.value = false;
    }
};

const switchAccount = async () => {
    working.value = true;
    await authStore.logout();
    router.replace(loginRoute());
};

onMounted(() => {
    if (!authStore.isAuthenticated) router.replace(loginRoute());
});
</script>

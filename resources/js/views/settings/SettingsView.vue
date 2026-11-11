<template>
  <div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Settings</h1>
    <p class="text-gray-500 mb-8">Manage your account preferences</p>

    <!-- Change Password Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-1">Change Password</h2>
      <p class="text-sm text-gray-500 mb-6">Update your password to keep your account secure</p>

      <div v-if="successMessage" class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg border border-green-100 text-sm flex items-center">
        <svg class="w-5 h-5 mr-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg border border-red-100 text-sm">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="changePassword" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
          <input type="password" v-model="form.current_password" required
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                 placeholder="Enter your current password">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
          <input type="password" v-model="form.password" required minlength="8"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                 placeholder="Enter new password (min. 8 characters)">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" v-model="form.password_confirmation" required minlength="8"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                 placeholder="Confirm new password">
        </div>
        <div class="pt-2">
          <button type="submit" :disabled="loading"
                  class="px-6 py-2.5 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors disabled:opacity-60">
            {{ loading ? 'Updating...' : 'Update Password' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Account Info Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
      <div class="space-y-3">
        <div class="flex justify-between items-center py-2 border-b border-gray-50">
          <span class="text-sm text-gray-500">Name</span>
          <span class="text-sm font-medium text-gray-900">{{ user?.name }}</span>
        </div>
        <div class="flex justify-between items-center py-2 border-b border-gray-50">
          <span class="text-sm text-gray-500">Email</span>
          <span class="text-sm font-medium text-gray-900">{{ user?.email }}</span>
        </div>
        <div class="flex justify-between items-center py-2 border-b border-gray-50">
          <span class="text-sm text-gray-500">Organisation</span>
          <span class="text-sm font-medium text-gray-900">{{ user?.organisation?.name || '—' }}</span>
        </div>
        <div class="flex justify-between items-center py-2 border-b border-gray-50">
          <span class="text-sm text-gray-500">Department</span>
          <span class="text-sm font-medium text-gray-900">{{ user?.department?.name || '—' }}</span>
        </div>
        <div class="flex justify-between items-center py-2">
          <span class="text-sm text-gray-500">Role</span>
          <span class="text-sm font-medium text-gray-900 capitalize">{{ user?.roles?.[0]?.replace('_', ' ') || 'Worker' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../lib/axios';

const authStore = useAuthStore();
const user = computed(() => authStore.user);

const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const changePassword = async () => {
  loading.value = true;
  successMessage.value = '';
  errorMessage.value = '';

  try {
    await api.patch('/auth/password', form);
    successMessage.value = 'Password updated successfully!';
    form.current_password = '';
    form.password = '';
    form.password_confirmation = '';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to update password.';
    const errors = err.response?.data?.errors;
    if (errors) {
      errorMessage.value = Object.values(errors).flat().join(' ');
    } else {
      errorMessage.value = msg;
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-gray-900/50 backdrop-blur-sm overflow-y-auto" @click.self="$emit('close')">
    <!-- Modal panel -->
    <div class="bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all w-full max-w-lg border border-gray-100 flex flex-col max-h-[90vh]">
        <div class="px-6 pt-6 pb-4 overflow-y-auto">
            <h3 class="text-xl leading-6 font-semibold text-gray-900" id="modal-title">
                {{ isEditing ? 'Edit Plan' : 'Add New Task' }}
              </h3>
              
              <form @submit.prevent="submitForm" class="mt-6 space-y-4">
                
                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg border border-red-100">
                    {{ error }}
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Task Name</label>
                    <input type="text" v-model="form.task_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                           placeholder="What are you working on?">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" v-model="form.date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="form.status" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm">
                            <option value="pending">Pending</option>
                            <option value="done">Done</option>
                            <option value="carried_over">Carried Over</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project / Client</label>
                    <select v-model="form.project_client_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm">
                        <option value="">Select a project/client...</option>
                        <option v-for="pc in projectClients" :key="pc.id" :value="pc.id">
                            {{ pc.name }}
                        </option>
                    </select>
                </div>

                <div v-if="form.project_client_id">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Details <span class="text-gray-400 font-normal">(Optional)</span></label>
                    <input type="text" v-model="form.project_client" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                           placeholder="Additional context for this task...">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select v-model="form.priority" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm">
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expected Time (mins)</label>
                        <input type="number" min="1" v-model="form.expected_duration_minutes" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                               placeholder="e.g. 120">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(Optional)</span></label>
                    <textarea v-model="form.notes" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 shadow-sm"
                              placeholder="Any extra context..."></textarea>
                </div>

                <div class="px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-100 mt-6 -mx-4 -mb-4 bg-gray-50 object-bottom rounded-b-2xl">
                    <button type="submit" :disabled="loading"
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-teal-600 text-base font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-70">
                        {{ loading ? 'Saving...' : 'Save Plan' }}
                    </button>
                    <button type="button" @click="$emit('close')"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
              </form>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import api from '../lib/axios';

const props = defineProps({
    planData: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'saved']);

const isEditing = computed(() => !!props.planData);
const loading = ref(false);
const error = ref(null);

const form = reactive({
    task_name: '',
    date: new Date().toISOString().split('T')[0],
    project_client: '',
    project_client_id: '',
    priority: 'medium',
    expected_duration_minutes: 60,
    status: 'pending',
    notes: ''
});

const projectClients = ref([]);

const fetchProjectClients = async () => {
    try {
        const resp = await api.get('/project-clients');
        projectClients.value = resp.data;
    } catch (e) {
        console.error('Failed to load project clients', e);
    }
};
fetchProjectClients();

// Watch for edits and hydrate the form
watch(() => props.planData, (newData) => {
    if (newData) {
        form.task_name = newData.task_name;
        form.date = newData.date;
        form.project_client = newData.project_client || '';
        form.project_client_id = newData.project_client_id || '';
        form.priority = newData.priority?.value || newData.priority;
        form.expected_duration_minutes = newData.expected_duration_minutes;
        form.status = newData.status?.value || newData.status;
        form.notes = newData.notes || '';
    } else {
        form.task_name = '';
        form.date = new Date().toISOString().split('T')[0];
        form.project_client = '';
        form.project_client_id = '';
        form.priority = 'medium';
        form.expected_duration_minutes = 60;
        form.status = 'pending';
        form.notes = '';
    }
}, { immediate: true });

const submitForm = async () => {
    loading.value = true;
    error.value = null;

    try {
        if (isEditing.value) {
            await api.put(`/plans/${props.planData.id}`, form);
        } else {
            await api.post(`/plans`, form);
        }
        emit('saved');
    } catch (err) {
        error.value = err.response?.data?.message || 'An error occurred while saving the plan.';
    } finally {
        loading.value = false;
    }
};
</script>


<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Personal Recurring Tasks</h1>
                <p class="text-sm text-gray-500 mt-1">Create habits and routines that auto-generate into your daily plans.</p>
            </div>
            <button @click="openCreate" class="inline-flex items-center justify-center rounded-lg px-4 py-2 bg-teal-600 text-sm font-semibold text-white hover:bg-teal-700">
                New Recurring Task
            </button>
        </div>

        <div v-if="loading" class="p-8 bg-white rounded-2xl border border-gray-200 text-gray-500">
            Loading…
        </div>

        <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div v-if="!tasks.length" class="p-8 text-sm text-gray-500">
                No recurring tasks yet.
            </div>
            <ul v-else class="divide-y divide-gray-100">
                <li v-for="t in tasks" :key="t.id" class="p-5 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-gray-900 truncate">{{ t.title }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ t.recurrence_type }}<span v-if="t.recurrence_type === 'weekly'"> (day {{ t.recurrence_day }})</span>
                            • {{ t.priority }} • {{ t.work_type }} • {{ t.expected_duration_minutes }} mins
                        </div>
                        <div class="mt-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                                  :class="t.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                                {{ t.is_active ? 'Active' : 'Paused' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-2 shrink-0">
                        <button @click="openEdit(t)" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">Edit</button>
                        <button @click="toggleActive(t)" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                            {{ t.is_active ? 'Pause' : 'Resume' }}
                        </button>
                        <button @click="remove(t)" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100">Delete</button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="w-full max-w-xl bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-sm font-bold text-gray-900">{{ editing?.id ? 'Edit recurring task' : 'New recurring task' }}</div>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-700">✕</button>
                    </div>

                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Title</label>
                            <input v-model="form.title" class="mt-1 w-full px-3 py-2 border border-gray-200 rounded-xl" />
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Work Type</label>
                            <select v-model="form.work_type" class="mt-1 w-full px-3 py-2 border border-gray-200 rounded-xl bg-white">
                                <option value="direct">Direct</option>
                                <option value="indirect">Indirect</option>
                                <option value="growth">Growth</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</label>
                            <select v-model="form.priority" class="mt-1 w-full px-3 py-2 border border-gray-200 rounded-xl bg-white">
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Expected minutes</label>
                            <input type="number" v-model.number="form.expected_duration_minutes" class="mt-1 w-full px-3 py-2 border border-gray-200 rounded-xl" />
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Recurrence</label>
                            <select v-model="form.recurrence_type" class="mt-1 w-full px-3 py-2 border border-gray-200 rounded-xl bg-white">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>

                        <div v-if="form.recurrence_type === 'weekly'">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Day (0=Sun…6=Sat)</label>
                            <input type="number" min="0" max="6" v-model.number="form.recurrence_day" class="mt-1 w-full px-3 py-2 border border-gray-200 rounded-xl" />
                        </div>

                        <div class="md:col-span-2 flex items-center gap-2">
                            <input id="active" type="checkbox" v-model="form.is_active" />
                            <label for="active" class="text-sm text-gray-700">Active</label>
                        </div>
                    </div>

                    <div class="p-5 border-t border-gray-100 flex justify-end gap-2">
                        <button @click="closeModal" class="px-4 py-2 rounded-xl text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">Cancel</button>
                        <button @click="save" class="px-4 py-2 rounded-xl text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700">
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import api from '../../lib/axios';

const tasks = ref([]);
const loading = ref(true);

const isModalOpen = ref(false);
const editing = ref(null);

const form = reactive({
    title: '',
    work_type: 'direct',
    priority: 'medium',
    expected_duration_minutes: 0,
    recurrence_type: 'daily',
    recurrence_day: 0,
    is_active: true,
});

const fetchTasks = async () => {
    loading.value = true;
    try {
        const res = await api.get('/recurring-tasks');
        tasks.value = res.data.data || [];
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editing.value = null;
    Object.assign(form, {
        title: '',
        work_type: 'direct',
        priority: 'medium',
        expected_duration_minutes: 0,
        recurrence_type: 'daily',
        recurrence_day: 0,
        is_active: true,
    });
    isModalOpen.value = true;
};

const openEdit = (t) => {
    editing.value = t;
    Object.assign(form, {
        title: t.title,
        work_type: t.work_type,
        priority: t.priority,
        expected_duration_minutes: t.expected_duration_minutes,
        recurrence_type: t.recurrence_type,
        recurrence_day: t.recurrence_day ?? 0,
        is_active: !!t.is_active,
    });
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editing.value = null;
};

const save = async () => {
    const payload = {
        title: form.title,
        work_type: form.work_type,
        priority: form.priority,
        expected_duration_minutes: form.expected_duration_minutes,
        recurrence_type: form.recurrence_type,
        recurrence_day: form.recurrence_type === 'weekly' ? form.recurrence_day : null,
        is_active: form.is_active,
    };

    if (editing.value?.id) {
        await api.put(`/recurring-tasks/${editing.value.id}`, payload);
    } else {
        await api.post('/recurring-tasks', payload);
    }

    await fetchTasks();
    closeModal();
};

const toggleActive = async (t) => {
    await api.put(`/recurring-tasks/${t.id}`, { is_active: !t.is_active });
    await fetchTasks();
};

const remove = async (t) => {
    if (!confirm('Delete this recurring task?')) return;
    await api.delete(`/recurring-tasks/${t.id}`);
    await fetchTasks();
};

onMounted(() => {
    fetchTasks();
});
</script>


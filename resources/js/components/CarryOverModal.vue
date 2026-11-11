<template>
    <div class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Resolve incomplete tasks</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Choose what to do for each pending task before clocking out.</p>
                    </div>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-700">✕</button>
                </div>

                <div class="p-5 space-y-4 max-h-[70vh] overflow-auto">
                    <div v-for="p in localPlans" :key="p.id" class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-bold text-gray-900">{{ p.task_name }}</div>
                                <div class="text-xs text-gray-600 mt-0.5">From {{ p.date }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <select v-model="p._priority" class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white">
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button @click="resolve(p, 'carry_over')" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-teal-600 text-white hover:bg-teal-700">
                                Carry Over
                            </button>
                            <button @click="resolve(p, 'cancel')" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-100">
                                Cancel
                            </button>
                            <button @click="resolve(p, 'leave')" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-100">
                                Leave for now
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button @click="$emit('close')" class="px-4 py-2 rounded-xl text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';

const props = defineProps({
    plans: { type: Array, required: true },
});

const emit = defineEmits(['close', 'resolve']);

const localPlans = reactive(
    (props.plans || []).map(p => ({
        ...p,
        _priority: p.priority?.value || 'medium',
    }))
);

const resolve = (plan, decision) => {
    emit('resolve', { plan, decision, priority: plan._priority });
};
</script>


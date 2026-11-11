<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Put it under</label>
            <select :value="parentId ?? ''" @change="onParent($event.target.value)" :disabled="disabled"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500 disabled:bg-gray-50">
                <option value="">Top level (a new chapter)</option>
                <option v-for="s in flat" :key="s.id" :value="s.id">
                    {{ '  '.repeat(s.depth) }}{{ s.number }} {{ s.title }}
                </option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
            <select :value="afterId ?? ''" @change="$emit('update:afterId', $event.target.value ? Number($event.target.value) : null)" :disabled="disabled"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500 disabled:bg-gray-50">
                <option value="">At the end</option>
                <option v-for="s in siblings" :key="s.id" :value="s.id">After {{ s.number }} {{ s.title }}</option>
            </select>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    tree: { type: Array, default: () => [] },
    parentId: { type: Number, default: null },
    afterId: { type: Number, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:parentId', 'update:afterId']);

// Depth-first list of every published section
const flat = computed(() => {
    const out = [];
    const walk = (nodes) => nodes.forEach(n => { out.push(n); walk(n.children || []); });
    walk(props.tree);
    return out;
});

const siblings = computed(() => {
    if (!props.parentId) return props.tree;
    return flat.value.find(s => s.id === props.parentId)?.children || [];
});

const onParent = (value) => {
    emit('update:parentId', value ? Number(value) : null);
    emit('update:afterId', null);
};
</script>

<template>
    <ul :class="depth === 0 ? 'space-y-0.5' : 'mt-0.5 space-y-0.5 border-l border-gray-100 ml-3 pl-2'">
        <li v-for="node in nodes" :key="node.id">
            <router-link
                :to="{ name: 'ManualReader', params: { slug, sectionId: node.id } }"
                class="flex items-start gap-2 px-2 py-1.5 rounded-lg text-sm transition-colors"
                :class="node.id === activeId ? 'bg-teal-50 text-teal-800 font-semibold' : 'text-gray-700 hover:bg-gray-50'"
            >
                <span class="text-gray-400 tabular-nums shrink-0 min-w-[2rem]">{{ node.number }}</span>
                <span class="flex-1 min-w-0">{{ node.title }}</span>
                <svg v-if="node.restricted" class="w-3.5 h-3.5 mt-0.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <title>Restricted in the documentation library: only staff and readers with an access code that includes restricted chapters can read it there</title>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span v-if="node.kind === 'uploaded_pdf'" class="text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 rounded shrink-0" title="Uploaded PDF">PDF</span>
                <svg v-if="node.read" class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-label="Read">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </router-link>
            <ManualTree v-if="node.children?.length" :nodes="node.children" :slug="slug" :active-id="activeId" :depth="depth + 1" />
        </li>
    </ul>
</template>

<script setup>
defineOptions({ name: 'ManualTree' });

defineProps({
    nodes: { type: Array, required: true },
    slug: { type: String, required: true },
    activeId: { type: Number, default: null },
    depth: { type: Number, default: 0 },
});
</script>

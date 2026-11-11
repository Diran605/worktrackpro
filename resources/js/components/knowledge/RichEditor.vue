<template>
    <div class="kb-editor border border-gray-300 rounded-xl bg-white overflow-hidden focus-within:ring-2 focus-within:ring-teal-500 focus-within:border-teal-500">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1.5 sticky top-0 z-10">
            <button v-for="b in markButtons" :key="b.name" type="button" :title="b.title" @click="b.run()"
                    class="px-2 py-1 rounded text-sm font-semibold min-w-[2rem]"
                    :class="editor.isActive(b.name, b.attrs) ? 'bg-teal-100 text-teal-800' : 'text-gray-700 hover:bg-gray-200'">
                <span :class="b.class">{{ b.label }}</span>
            </button>

            <span class="w-px h-5 bg-gray-300 mx-1"></span>

            <button type="button" title="Link" @click="setLink"
                    class="px-2 py-1 rounded text-sm"
                    :class="editor.isActive('link') ? 'bg-teal-100 text-teal-800' : 'text-gray-700 hover:bg-gray-200'">🔗 Link</button>
            <button type="button" title="Insert screenshot or image" @click="fileInput?.click()" :disabled="uploading"
                    class="px-2 py-1 rounded text-sm text-gray-700 hover:bg-gray-200 disabled:opacity-50">
                {{ uploading ? 'Uploading…' : '🖼 Image' }}
            </button>
            <button type="button" title="Insert table" @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
                    class="px-2 py-1 rounded text-sm text-gray-700 hover:bg-gray-200">▦ Table</button>
            <template v-if="editor.isActive('table')">
                <button type="button" @click="editor.chain().focus().addRowAfter().run()" class="px-2 py-1 rounded text-xs text-gray-700 hover:bg-gray-200">+ Row</button>
                <button type="button" @click="editor.chain().focus().addColumnAfter().run()" class="px-2 py-1 rounded text-xs text-gray-700 hover:bg-gray-200">+ Col</button>
                <button type="button" @click="editor.chain().focus().deleteRow().run()" class="px-2 py-1 rounded text-xs text-gray-700 hover:bg-gray-200">− Row</button>
                <button type="button" @click="editor.chain().focus().deleteColumn().run()" class="px-2 py-1 rounded text-xs text-gray-700 hover:bg-gray-200">− Col</button>
                <button type="button" @click="editor.chain().focus().deleteTable().run()" class="px-2 py-1 rounded text-xs text-red-600 hover:bg-red-50">Delete table</button>
            </template>

            <span class="flex-1"></span>
            <button type="button" title="Undo" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="px-2 py-1 rounded text-sm text-gray-700 hover:bg-gray-200 disabled:opacity-30">↶</button>
            <button type="button" title="Redo" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="px-2 py-1 rounded text-sm text-gray-700 hover:bg-gray-200 disabled:opacity-30">↷</button>
        </div>

        <EditorContent :editor="editor" class="kb-content px-5 py-4" />

        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="hidden" @change="onFilePicked">
        <p v-if="uploadError" class="px-4 pb-3 text-sm text-red-600">{{ uploadError }}</p>
        <p class="px-4 pb-3 text-xs text-gray-400">Tip: paste a screenshot (Ctrl+V) or drag an image into the text to add it.</p>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import { Placeholder } from '@tiptap/extensions';
import api from '../../lib/axios';

const props = defineProps({
    modelValue: { type: String, default: '' },
    // System slug, used for screenshot uploads
    slug: { type: String, required: true },
    placeholder: { type: String, default: 'Start writing… paste screenshots straight in.' },
    editable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref(null);

const uploadImage = async (file) => {
    if (!file || !file.type.startsWith('image/')) return;

    uploading.value = true;
    uploadError.value = null;

    try {
        const form = new FormData();
        form.append('file', file);
        const res = await api.post(`/systems/${props.slug}/manual/assets`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        editor.value?.chain().focus().setImage({ src: res.data.data.url, alt: file.name.replace(/\.[^.]+$/, '') }).run();
    } catch (e) {
        uploadError.value = e.response?.data?.errors?.file?.[0] || e.response?.data?.message || 'Image upload failed.';
    } finally {
        uploading.value = false;
    }
};

const imagesFrom = (dataTransfer) => Array.from(dataTransfer?.files || []).filter(f => f.type.startsWith('image/'));

const editor = useEditor({
    content: props.modelValue,
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
        }),
        Image.configure({ inline: false }),
        TableKit.configure({ table: { resizable: false } }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        handlePaste(view, event) {
            const images = imagesFrom(event.clipboardData);
            if (!images.length) return false;
            event.preventDefault();
            images.forEach(uploadImage);
            return true;
        },
        handleDrop(view, event) {
            const images = imagesFrom(event.dataTransfer);
            if (!images.length) return false;
            event.preventDefault();
            images.forEach(uploadImage);
            return true;
        },
    },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
});

// Content loaded after mount (e.g. an existing draft) replaces the editor content.
watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value || '', { emitUpdate: false });
    }
});

// false = don't fire an update event: locking the editor is not an edit
watch(() => props.editable, (value) => editor.value?.setEditable(value, false));

const markButtons = computed(() => {
    const e = editor.value;
    if (!e) return [];
    return [
        { name: 'bold', label: 'B', title: 'Bold', class: 'font-bold', run: () => e.chain().focus().toggleBold().run() },
        { name: 'italic', label: 'I', title: 'Italic', class: 'italic', run: () => e.chain().focus().toggleItalic().run() },
        { name: 'underline', label: 'U', title: 'Underline', class: 'underline', run: () => e.chain().focus().toggleUnderline().run() },
        { name: 'heading', attrs: { level: 2 }, label: 'H2', title: 'Heading', run: () => e.chain().focus().toggleHeading({ level: 2 }).run() },
        { name: 'heading', attrs: { level: 3 }, label: 'H3', title: 'Sub-heading', run: () => e.chain().focus().toggleHeading({ level: 3 }).run() },
        { name: 'bulletList', label: '• List', title: 'Bullet list', run: () => e.chain().focus().toggleBulletList().run() },
        { name: 'orderedList', label: '1. List', title: 'Numbered list (steps)', run: () => e.chain().focus().toggleOrderedList().run() },
        { name: 'blockquote', label: '❝ Note', title: 'Note / callout', run: () => e.chain().focus().toggleBlockquote().run() },
        { name: 'codeBlock', label: '</>', title: 'Code block', run: () => e.chain().focus().toggleCodeBlock().run() },
    ];
});

const setLink = () => {
    const e = editor.value;
    const previous = e.getAttributes('link').href || '';
    const url = window.prompt('Link address (leave empty to remove the link):', previous);
    if (url === null) return;
    if (url.trim() === '') {
        e.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    e.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
};

const onFilePicked = (event) => {
    Array.from(event.target.files || []).forEach(uploadImage);
    event.target.value = '';
};

onBeforeUnmount(() => editor.value?.destroy());
</script>

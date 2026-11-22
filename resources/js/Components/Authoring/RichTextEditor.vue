<script setup>
import { computed, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import Highlight from '@tiptap/extension-highlight';
import DOMPurify from 'dompurify';
import { Bold, Italic, Underline, List, ListOrdered, Quote, Code2, Undo2, Redo2, Link2, ImagePlus, Table2, Highlighter, Minus, Maximize2, Minimize2, X } from 'lucide-vue-next';
import MediaUpload from './MediaUpload.vue';

const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, default: 'Lesson content' }, placeholder: { type: String, default: 'Start writing your lesson. Use headings, examples and activities to guide your learners…' }, courseId: [Number, String], disabled: Boolean, compact: Boolean, emailMode: Boolean });
const emit = defineEmits(['update:modelValue', 'busy']);
const fullscreen = ref(false), panel = ref(''), linkUrl = ref(''), linkError = ref(''), imageUrl = ref(''), imageAlt = ref(''), decorative = ref(false);
const clean = value => DOMPurify.sanitize(value || '', { ...(props.emailMode ? { ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'mark'], ALLOWED_ATTR: ['href', 'colspan', 'rowspan'] } : {}), FORBID_TAGS: ['style', 'form', 'input', 'button', 'iframe'], FORBID_ATTR: ['style'] });
const editor = useEditor({
    content: clean(props.modelValue), editable: !props.disabled,
    extensions: [StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: { openOnClick: false, defaultProtocol: 'https', protocols: ['http', 'https', 'mailto'] } }), Image.configure({ allowBase64: false }), TableKit.configure({ table: { resizable: false } }), Placeholder.configure({ placeholder: props.placeholder }), Highlight],
    editorProps: { attributes: { role: 'textbox', 'aria-label': props.label, 'aria-multiline': 'true', class: 'authoring-prose focus:outline-none' }, transformPastedHTML: clean },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML()),
});
watch(() => props.modelValue, value => { if (editor.value && value !== editor.value.getHTML() && !(editor.value.isEmpty && !value)) editor.value.commands.setContent(clean(value), { emitUpdate: false }); });
watch(() => props.disabled, value => editor.value?.setEditable(!value));
const words = computed(() => (editor.value?.getText() || '').trim().split(/\s+/).filter(Boolean).length);
const toolbar = [
    { label: 'Bold', icon: Bold, name: 'bold', command: 'toggleBold' }, { label: 'Italic', icon: Italic, name: 'italic', command: 'toggleItalic' },
    { label: 'Underline', icon: Underline, name: 'underline', command: 'toggleUnderline' }, { label: 'Highlight', icon: Highlighter, name: 'highlight', command: 'toggleHighlight' },
    { label: 'Bullet list', icon: List, name: 'bulletList', command: 'toggleBulletList' }, { label: 'Numbered list', icon: ListOrdered, name: 'orderedList', command: 'toggleOrderedList' },
    { label: 'Quote', icon: Quote, name: 'blockquote', command: 'toggleBlockquote' }, { label: 'Code block', icon: Code2, name: 'codeBlock', command: 'toggleCodeBlock' },
];
function heading(event) { const chain = editor.value.chain().focus(); event.target.value === 'paragraph' ? chain.setParagraph().run() : chain.setHeading({ level: Number(event.target.value) }).run(); }
function openLink() { linkUrl.value = editor.value.getAttributes('link').href || ''; linkError.value = ''; panel.value = panel.value === 'link' ? '' : 'link'; }
function setLink() {
    if (!linkUrl.value.trim()) { editor.value.chain().focus().extendMarkRange('link').unsetLink().run(); panel.value = ''; return; }
    try { const url = new URL(linkUrl.value); if (!['https:', 'http:', 'mailto:'].includes(url.protocol)) throw new Error(); editor.value.chain().focus().extendMarkRange('link').setLink({ href: url.href }).run(); panel.value = ''; }
    catch { linkError.value = 'Use a complete HTTPS, HTTP or mailto link.'; }
}
function insertImage() { if (!imageUrl.value || (!imageAlt.value.trim() && !decorative.value)) return; editor.value.chain().focus().setImage({ src: imageUrl.value, alt: decorative.value ? '' : imageAlt.value.trim() }).run(); imageUrl.value = ''; imageAlt.value = ''; decorative.value = false; panel.value = ''; }
</script>

<template>
    <div :class="[fullscreen ? 'fixed inset-3 z-50 flex flex-col bg-white shadow-2xl sm:inset-8' : 'bg-white', 'overflow-hidden rounded-xl border border-surface-300 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-100']" @keydown.esc="fullscreen = false; panel = ''">
        <div v-if="editor" role="group" :aria-label="`${label} formatting`" class="flex flex-wrap items-center gap-0.5 border-b border-surface-200 bg-surface-50/80 px-2 py-2">
            <select aria-label="Text style" :disabled="disabled" :value="editor.isActive('heading') ? editor.getAttributes('heading').level : 'paragraph'" @change="heading" class="mr-1 h-9 rounded-lg border border-surface-200 bg-white px-2 text-xs font-medium"><option value="paragraph">Paragraph</option><option value="2">Heading 2</option><option value="3">Heading 3</option><option value="4">Heading 4</option></select>
            <button v-for="tool in toolbar" :key="tool.name" type="button" :aria-label="tool.label" :title="tool.label" :aria-pressed="editor.isActive(tool.name)" :disabled="disabled" @click="editor.chain().focus()[tool.command]().run()" :class="['editor-tool', editor.isActive(tool.name) ? 'bg-primary-100 text-primary-800' : 'text-surface-600 hover:bg-surface-200']"><component :is="tool.icon" class="h-4 w-4" /></button>
            <span class="mx-1 h-5 border-l border-surface-200" aria-hidden="true" />
            <button type="button" aria-label="Insert or edit link" title="Link" :disabled="disabled" @click="openLink" class="editor-tool"><Link2 class="h-4 w-4" /></button>
            <button v-if="courseId" type="button" aria-label="Insert image" title="Insert image" :disabled="disabled" @click="panel = panel === 'image' ? '' : 'image'" class="editor-tool"><ImagePlus class="h-4 w-4" /></button>
            <button type="button" aria-label="Insert table" title="Insert table" :disabled="disabled" @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()" class="editor-tool"><Table2 class="h-4 w-4" /></button>
            <button type="button" aria-label="Insert divider" title="Divider" :disabled="disabled" @click="editor.chain().focus().setHorizontalRule().run()" class="editor-tool"><Minus class="h-4 w-4" /></button>
            <span class="flex-1" />
            <button type="button" aria-label="Undo" title="Undo" :disabled="disabled || !editor.can().undo()" @click="editor.chain().focus().undo().run()" class="editor-tool"><Undo2 class="h-4 w-4" /></button>
            <button type="button" aria-label="Redo" title="Redo" :disabled="disabled || !editor.can().redo()" @click="editor.chain().focus().redo().run()" class="editor-tool"><Redo2 class="h-4 w-4" /></button>
            <button type="button" :aria-label="fullscreen ? 'Exit expanded editor' : 'Expand editor'" @click="fullscreen = !fullscreen" class="editor-tool"><Minimize2 v-if="fullscreen" class="h-4 w-4" /><Maximize2 v-else class="h-4 w-4" /></button>
        </div>
        <div v-if="panel === 'link'" class="border-b border-surface-200 bg-primary-50 p-4"><label class="mb-2 block text-xs font-semibold" for="authoring-link">Link address</label><div class="flex gap-2"><input id="authoring-link" v-model="linkUrl" placeholder="https://example.com" class="min-w-0 flex-1 rounded-lg border border-surface-300 px-3 py-2 text-sm" @keydown.enter.prevent="setLink" /><button type="button" @click="setLink" class="rounded-lg bg-primary-700 px-3 text-sm font-medium text-white">Apply</button><button type="button" @click="linkUrl = ''; setLink()" class="text-xs font-medium">Remove link</button><button type="button" @click="panel = ''" aria-label="Close link editor"><X class="h-4 w-4" /></button></div><p v-if="linkError" role="alert" class="mt-2 text-xs text-rose-700">{{ linkError }}</p></div>
        <div v-if="panel === 'image'" class="space-y-3 border-b border-surface-200 bg-surface-50 p-4"><div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Insert an image</h3><button type="button" @click="panel = ''" aria-label="Close image editor"><X class="h-4 w-4" /></button></div><MediaUpload v-model="imageUrl" type="image" :course-id="courseId" :allow-link="false" @busy="emit('busy', $event)" /><label class="block text-xs font-medium">Image description<input v-model="imageAlt" :disabled="decorative" class="mt-1 w-full rounded-lg border border-surface-300 px-3 py-2 text-sm" placeholder="Describe what learners should understand from this image" /></label><label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="decorative" />This image is decorative</label><button type="button" :disabled="!imageUrl || (!imageAlt.trim() && !decorative)" @click="insertImage" class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-40">Insert into lesson</button></div>
        <div v-if="editor?.isActive('table') && !disabled" class="flex flex-wrap gap-3 border-b border-surface-200 bg-primary-50 px-4 py-2 text-xs font-medium"><button type="button" @click="editor.chain().focus().addRowAfter().run()">Add row</button><button type="button" @click="editor.chain().focus().addColumnAfter().run()">Add column</button><button type="button" @click="editor.chain().focus().deleteRow().run()">Remove row</button><button type="button" @click="editor.chain().focus().deleteColumn().run()">Remove column</button><button type="button" @click="editor.chain().focus().deleteTable().run()">Remove table</button></div>
        <EditorContent :editor="editor" :class="['min-h-0 overflow-auto', fullscreen ? 'flex-1' : compact ? 'max-h-[480px]' : 'max-h-[680px]', compact ? 'compact-editor' : '']" />
        <div class="flex items-center justify-between gap-3 border-t border-surface-100 px-4 py-2 text-[11px] text-surface-500"><span>{{ disabled ? 'Read-only content' : 'Select text to format · Ctrl / ⌘ + Z to undo' }}</span><span>{{ words }} words<span v-if="!emailMode"> · {{ Math.max(1, Math.ceil(words / 200)) }} min read</span></span></div>
    </div>
</template>

<style scoped>
.editor-tool { display: inline-flex; align-items: center; justify-content: center; height: 36px; width: 36px; border-radius: 7px; color: #475569; }
.editor-tool:hover { background: #e2e8f0; }.editor-tool:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }.editor-tool:disabled { opacity: .3; cursor: not-allowed; }
:deep(.authoring-prose) { min-height: 360px; padding: 28px 32px; color: #334155; font-size: 15px; line-height: 1.85; }
.compact-editor :deep(.authoring-prose) { min-height: 200px; padding: 20px; }
:deep(.authoring-prose p) { margin: 0 0 1em; }:deep(.authoring-prose h2) { font-size: 1.6em; font-weight: 700; margin: 1em 0 .5em; color: #0f172a; }:deep(.authoring-prose h3) { font-size: 1.25em; font-weight: 650; margin: 1em 0 .5em; }:deep(.authoring-prose h4) { font-weight: 700; margin: 1em 0 .5em; }
:deep(.authoring-prose ul) { list-style: disc; padding-left: 1.5em; }:deep(.authoring-prose ol) { list-style: decimal; padding-left: 1.5em; }:deep(.authoring-prose blockquote) { border-left: 3px solid #d97706; padding: 8px 20px; background: #fffbeb; margin: 1em 0; }:deep(.authoring-prose pre) { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 10px; white-space: pre-wrap; }:deep(.authoring-prose a) { color: #1d4ed8; text-decoration: underline; }:deep(.authoring-prose img) { max-width: 100%; max-height: 480px; border-radius: 10px; margin: 1em auto; }:deep(.authoring-prose table) { border-collapse: collapse; width: 100%; margin: 1em 0; table-layout: fixed; }:deep(.authoring-prose td), :deep(.authoring-prose th) { border: 1px solid #cbd5e1; padding: 8px; min-width: 60px; vertical-align: top; position: relative; }:deep(.authoring-prose th) { background: #f1f5f9; font-weight: 700; }:deep(.authoring-prose .selectedCell) { background: #dbeafe; }:deep(.authoring-prose hr) { border-color: #cbd5e1; margin: 24px 0; }:deep(.authoring-prose p.is-editor-empty:first-child::before) { color: #94a3b8; content: attr(data-placeholder); float: left; height: 0; pointer-events: none; }:deep(.ProseMirror-selectednode) { outline: 2px solid #2563eb; }
@media (max-width: 640px) { :deep(.authoring-prose) { padding: 20px 16px; } }
</style>

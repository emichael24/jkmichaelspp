<template>
    <div class="space-y-6">
        <!-- Add note form -->
        <div class="rounded-xl border border-surface-200 p-4">
            <h3 class="font-semibold text-surface-900 text-sm mb-3 flex items-center gap-2">
                <PenLine class="w-4 h-4 text-primary-500" />
                Add a Note
            </h3>
            <form @submit.prevent="addNote">
                <textarea
                    v-model="newNoteContent"
                    rows="3"
                    placeholder="Write your thoughts, key takeaways, or questions..."
                    class="w-full px-4 py-3 rounded-xl border border-surface-200 text-sm text-surface-700 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-400 resize-none transition-colors"
                />
                <div class="flex items-center justify-between mt-3">
                    <p class="text-xs text-surface-400">{{ newNoteContent.length }} characters</p>
                    <button
                        type="submit"
                        :disabled="!newNoteContent.trim() || saving"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <Save class="w-3.5 h-3.5" />
                        {{ saving ? 'Saving...' : 'Save Note' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Notes list -->
        <div v-if="localNotes.length" class="space-y-3">
            <h3 class="font-semibold text-surface-900 text-sm flex items-center gap-2">
                <StickyNote class="w-4 h-4 text-accent-500" />
                Your Notes ({{ localNotes.length }})
            </h3>

            <div
                v-for="note in localNotes"
                :key="note.id"
                class="rounded-xl border border-surface-200 p-4 hover:border-surface-300 transition-colors group"
            >
                <!-- Editing mode -->
                <div v-if="editingNote === note.id">
                    <textarea
                        v-model="editContent"
                        rows="3"
                        class="w-full px-3 py-2 rounded-lg border border-surface-200 text-sm text-surface-700 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-400 resize-none"
                    />
                    <div class="flex items-center gap-2 mt-2">
                        <button
                            @click="saveEdit(note.id)"
                            class="px-3 py-1.5 rounded-lg bg-primary-600 text-white text-xs font-medium hover:bg-primary-700 transition-colors"
                        >
                            Save
                        </button>
                        <button
                            @click="editingNote = null"
                            class="px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 text-xs font-medium hover:bg-surface-200 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Display mode -->
                <div v-else>
                    <!-- Highlight text if present -->
                    <div v-if="note.highlight_text" class="mb-2 pl-3 border-l-3 border-accent-400">
                        <p class="text-sm text-surface-500 italic">"{{ note.highlight_text }}"</p>
                    </div>

                    <p class="text-sm text-surface-700 whitespace-pre-wrap">{{ note.content }}</p>

                    <div class="flex items-center justify-between mt-3">
                        <span class="text-xs text-surface-400">{{ formatDate(note.created_at) }}</span>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                @click="startEdit(note)"
                                class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-600 transition-colors"
                                title="Edit note"
                            >
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click="deleteNote(note.id)"
                                class="p-1.5 rounded-lg hover:bg-danger-50 text-surface-400 hover:text-danger-500 transition-colors"
                                title="Delete note"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-8">
            <StickyNote class="w-10 h-10 text-surface-300 mx-auto mb-3" />
            <p class="text-surface-500 text-sm">No notes yet. Start capturing your thoughts!</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { PenLine, StickyNote, Save, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    notes: { type: Array, default: () => [] },
    lessonId: { type: Number, required: true },
});

const localNotes = computed(() => props.notes);
const newNoteContent = ref('');
const saving = ref(false);
const editingNote = ref(null);
const editContent = ref('');

function addNote() {
    if (!newNoteContent.value.trim()) return;
    saving.value = true;

    router.post(`/lessons/${props.lessonId}/notes`, {
        content: newNoteContent.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newNoteContent.value = '';
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function startEdit(note) {
    editingNote.value = note.id;
    editContent.value = note.content;
}

function saveEdit(noteId) {
    router.put(`/notes/${noteId}`, {
        content: editContent.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingNote.value = null;
        },
    });
}

function deleteNote(noteId) {
    if (!confirm('Delete this note?')) return;
    router.delete(`/notes/${noteId}`, { preserveScroll: true });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

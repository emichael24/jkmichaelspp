<template>
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm text-surface-500 mb-2">
                    <Link :href="route('practice.dashboard')" class="hover:text-primary-600">Practice</Link>
                    <span>/</span>
                    <span class="text-surface-700">Flashcards</span>
                </nav>
                <h1 class="text-2xl font-display font-bold text-surface-900">My Flashcards</h1>
            </div>
            <button @click="showCreateModal = true" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition">
                <Plus class="w-4 h-4" /> New Flashcard
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6">
            <input
                v-model="search"
                @input="debouncedFilter"
                type="text"
                placeholder="Search cards..."
                class="px-4 py-2 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 w-64"
            />
            <select v-model="sourceFilter" @change="applyFilters" class="px-4 py-2 border border-surface-300 rounded-xl text-sm">
                <option value="">All Sources</option>
                <option value="wrong_answer">From Quiz Mistakes</option>
                <option value="lesson_term">Key Terms</option>
                <option value="user_created">User Created</option>
            </select>
        </div>

        <!-- Cards list -->
        <div v-if="flashcards.data.length" class="space-y-3">
            <div
                v-for="card in flashcards.data"
                :key="card.id"
                class="bg-white rounded-xl border border-surface-200 p-5 hover:shadow-sm transition"
            >
                <div class="flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-surface-900 mb-1">{{ card.front }}</p>
                        <p class="text-sm text-surface-600">{{ card.back }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span v-if="card.domain" class="text-xs px-2 py-0.5 rounded bg-primary-50 text-primary-700">{{ card.domain }}</span>
                            <span class="text-xs px-2 py-0.5 rounded" :class="sourceBadge(card.source)">{{ sourceLabel(card.source) }}</span>
                            <span v-if="card.course" class="text-xs text-surface-400">{{ card.course.title }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button v-if="card.source === 'user_created'" @click="editCard(card)" class="p-2 text-surface-400 hover:text-primary-600 transition">
                            <Pencil class="w-4 h-4" />
                        </button>
                        <button @click="confirmDelete(card)" class="p-2 text-surface-400 hover:text-danger-600 transition">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <div v-else class="text-center py-16">
            <CreditCard class="w-12 h-12 text-surface-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-surface-700">No flashcards yet</h3>
            <p class="text-sm text-surface-500 mt-1">Create your own or take quizzes to generate cards automatically.</p>
        </div>

        <!-- Pagination -->
        <div v-if="flashcards.links?.length > 3" class="mt-8 flex justify-center gap-1">
            <Link
                v-for="link in flashcards.links"
                :key="link.label"
                :href="link.url || '#'"
                v-html="link.label"
                class="px-3 py-1.5 text-sm rounded-lg"
                :class="link.active ? 'bg-primary-600 text-white' : 'text-surface-600 hover:bg-surface-100'"
                preserve-scroll
            />
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || editingCard" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 max-w-lg w-full">
                <h3 class="text-lg font-bold text-surface-900 mb-4">{{ editingCard ? 'Edit Flashcard' : 'New Flashcard' }}</h3>
                <form @submit.prevent="saveCard" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Front (Question)</label>
                        <textarea v-model="form.front" rows="3" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Back (Answer)</label>
                        <textarea v-model="form.back" rows="4" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Domain (optional)</label>
                        <input v-model="form.domain" type="text" class="w-full px-4 py-2.5 border border-surface-300 rounded-xl text-sm focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="closeModal" class="px-5 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700">
                            {{ form.processing ? 'Saving...' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete confirm -->
        <div v-if="deletingCard" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center">
                <Trash2 class="w-10 h-10 text-danger-500 mx-auto mb-3" />
                <h3 class="text-lg font-bold text-surface-900 mb-2">Delete Flashcard?</h3>
                <p class="text-sm text-surface-600 mb-6">This will also remove it from your review schedule.</p>
                <div class="flex gap-3 justify-center">
                    <button @click="deletingCard = null" class="px-5 py-2.5 text-sm font-medium border border-surface-300 rounded-xl hover:bg-surface-50">Cancel</button>
                    <button @click="deleteCard" class="px-5 py-2.5 text-sm font-semibold bg-danger-600 text-white rounded-xl hover:bg-danger-700">Delete</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2, CreditCard } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    flashcards: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const sourceFilter = ref(props.filters?.source || '')
const showCreateModal = ref(false)
const editingCard = ref(null)
const deletingCard = ref(null)

const form = useForm({
    front: '',
    back: '',
    domain: '',
})

let filterTimeout = null
function debouncedFilter() {
    clearTimeout(filterTimeout)
    filterTimeout = setTimeout(applyFilters, 400)
}

function applyFilters() {
    router.get(route('practice.flashcards'), {
        search: search.value || undefined,
        source: sourceFilter.value || undefined,
    }, { preserveState: true })
}

function editCard(card) {
    editingCard.value = card
    form.front = card.front
    form.back = card.back
    form.domain = card.domain || ''
}

function closeModal() {
    showCreateModal.value = false
    editingCard.value = null
    form.reset()
}

function saveCard() {
    if (editingCard.value) {
        form.put(route('practice.flashcards.update', editingCard.value.id), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('practice.flashcards.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

function confirmDelete(card) {
    deletingCard.value = card
}

function deleteCard() {
    router.delete(route('practice.flashcards.destroy', deletingCard.value.id), {
        onSuccess: () => { deletingCard.value = null },
    })
}

function sourceLabel(source) {
    const map = { wrong_answer: 'Quiz Mistake', lesson_term: 'Key Term', user_created: 'Custom' }
    return map[source] || source
}

function sourceBadge(source) {
    const map = {
        wrong_answer: 'bg-danger-50 text-danger-700',
        lesson_term: 'bg-accent-50 text-accent-700',
        user_created: 'bg-xp-50 text-xp-700',
    }
    return map[source] || 'bg-surface-50 text-surface-600'
}
</script>

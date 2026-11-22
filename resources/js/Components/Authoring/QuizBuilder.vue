<script setup>
import { computed, ref } from 'vue';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { Plus, Trash2, ChevronUp, ChevronDown, Library, CheckCircle2, Circle, Square, CheckSquare, X, Search } from 'lucide-vue-next';

const props = defineProps({
    activity: { type: Object, required: true },
    bank: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    disabled: Boolean,
});

const letters = 'abcdefghij'.split('');
const typeLabels = { multiple_choice: 'Multiple choice', multiple_select: 'Multiple select', true_false: 'True / False' };
const picker = ref(false), search = ref(''), picked = ref([]);

const questions = computed(() => props.activity.questions);
const usedIds = computed(() => new Set(questions.value.map(q => q.id).filter(Boolean)));
const bankResults = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.bank.filter(q => !usedIds.value.has(q.id) && (!term || q.stem.toLowerCase().includes(term) || (q.domain || '').toLowerCase().includes(term)));
});
const totalErrors = computed(() => Object.keys(props.errors).filter(key => key.startsWith('activity.')).length);

function questionError(index) {
    return Object.entries(props.errors).filter(([key]) => key === `activity.questions.${index}` || key.startsWith(`activity.questions.${index}.`)).map(([, message]) => message)[0];
}

function newQuestion(type = 'multiple_choice') {
    const options = type === 'true_false'
        ? [{ id: 'true', text: 'True' }, { id: 'false', text: 'False' }]
        : letters.slice(0, 4).map(id => ({ id, text: '' }));
    return { id: null, type, stem: '', options, correct_answer_ids: [], explanation: '', from_bank: false };
}

function add(type) { questions.value.push(newQuestion(type)); }
function remove(index) { questions.value.splice(index, 1); }
function move(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= questions.value.length) return;
    const [item] = questions.value.splice(index, 1);
    questions.value.splice(target, 0, item);
}

function changeType(question, type) {
    if (type === 'true_false') {
        question.options = [{ id: 'true', text: 'True' }, { id: 'false', text: 'False' }];
        question.correct_answer_ids = [];
    } else if (question.type === 'true_false') {
        question.options = letters.slice(0, 4).map(id => ({ id, text: '' }));
        question.correct_answer_ids = [];
    } else if (type === 'multiple_choice' && question.correct_answer_ids.length > 1) {
        question.correct_answer_ids = question.correct_answer_ids.slice(0, 1);
    }
    question.type = type;
}

function toggleCorrect(question, optionId) {
    if (question.type === 'multiple_select') {
        const index = question.correct_answer_ids.indexOf(optionId);
        index >= 0 ? question.correct_answer_ids.splice(index, 1) : question.correct_answer_ids.push(optionId);
    } else {
        question.correct_answer_ids = [optionId];
    }
}

function addOption(question) {
    const id = letters.find(letter => !question.options.some(option => option.id === letter));
    if (id) question.options.push({ id, text: '' });
}

function removeOption(question, index) {
    const [option] = question.options.splice(index, 1);
    question.correct_answer_ids = question.correct_answer_ids.filter(id => id !== option.id);
}

function addFromBank() {
    for (const id of picked.value) {
        const source = props.bank.find(q => q.id === id);
        if (source) questions.value.push({ ...source, options: source.options.map(o => ({ ...o })), correct_answer_ids: [...source.correct_answer_ids], from_bank: true });
    }
    picked.value = []; picker.value = false; search.value = '';
}
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-4 rounded-xl border border-surface-200 bg-surface-50 p-4 sm:grid-cols-4">
            <label class="text-xs font-medium text-surface-600">Pass mark (%)
                <input v-model.number="activity.passing_score_pct" type="number" min="1" max="100" :disabled="disabled" class="mt-1.5 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="text-xs font-medium text-surface-600">Time limit (minutes)
                <input v-model="activity.time_limit_minutes" type="number" min="1" max="600" placeholder="No limit" :disabled="disabled" class="mt-1.5 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="text-xs font-medium text-surface-600">Attempts allowed
                <input v-model="activity.max_attempts" type="number" min="1" max="100" placeholder="Unlimited" :disabled="disabled" class="mt-1.5 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="flex items-center gap-2 self-end pb-2 text-xs font-medium text-surface-600">
                <input v-model="activity.is_randomized" type="checkbox" :disabled="disabled" class="h-4 w-4 accent-primary-700" />Shuffle question order
            </label>
        </div>
        <p class="text-xs leading-5 text-surface-500">Learners complete this lesson by scoring at least {{ activity.passing_score_pct || 70 }}%. Questions you write here are also saved to the course question bank.</p>

        <div v-if="totalErrors" role="alert" class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">Some questions need attention. Check the highlighted questions below.</div>

        <div v-if="!questions.length" class="rounded-xl border border-dashed border-surface-300 px-6 py-10 text-center">
            <CheckCircle2 class="mx-auto h-8 w-8 text-primary-400" />
            <p class="mt-3 text-sm font-semibold">No questions yet</p>
            <p class="mt-1 text-xs text-surface-500">Write a question or reuse one from your course question bank.</p>
        </div>

        <article v-for="(question, index) in questions" :key="index" class="rounded-xl border bg-white p-4 shadow-sm sm:p-5" :class="questionError(index) ? 'border-rose-300' : 'border-surface-200'">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-primary-50 text-xs font-bold text-primary-700">{{ index + 1 }}</span>
                <select v-if="!question.from_bank" :value="question.type" :disabled="disabled" :aria-label="`Question ${index + 1} type`" class="rounded-lg border border-surface-200 px-2 py-1.5 text-xs" @change="changeType(question, $event.target.value)">
                    <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
                </select>
                <span v-else class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-800">From question bank · {{ typeLabels[question.type] }}</span>
                <div class="ml-auto flex items-center gap-0.5">
                    <button type="button" :disabled="disabled || index === 0" class="quiz-icon" :aria-label="`Move question ${index + 1} up`" @click="move(index, -1)"><ChevronUp class="h-4 w-4" /></button>
                    <button type="button" :disabled="disabled || index === questions.length - 1" class="quiz-icon" :aria-label="`Move question ${index + 1} down`" @click="move(index, 1)"><ChevronDown class="h-4 w-4" /></button>
                    <button type="button" :disabled="disabled" class="quiz-icon hover:text-rose-600" :aria-label="`Remove question ${index + 1}`" @click="remove(index)"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>

            <template v-if="question.from_bank">
                <p class="whitespace-pre-wrap text-sm font-medium text-surface-800">{{ question.stem }}</p>
                <ul class="mt-3 space-y-1.5">
                    <li v-for="option in question.options" :key="option.id" class="flex items-center gap-2 text-sm" :class="question.correct_answer_ids.includes(option.id) ? 'font-semibold text-emerald-700' : 'text-surface-600'">
                        <CheckCircle2 v-if="question.correct_answer_ids.includes(option.id)" class="h-4 w-4" /><Circle v-else class="h-4 w-4 text-surface-300" />{{ option.text }}
                    </li>
                </ul>
                <p class="mt-3 text-[11px] text-surface-500">Edit bank questions in the Question Bank so every quiz using them stays consistent.</p>
            </template>
            <template v-else>
                <label class="block text-xs font-medium text-surface-600">Question
                    <textarea v-model="question.stem" rows="2" :disabled="disabled" placeholder="e.g. Which document formally authorizes a project?" class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm" />
                </label>
                <fieldset class="mt-3">
                    <legend class="mb-2 text-xs font-medium text-surface-600">Answers <span class="font-normal text-surface-400">· select the correct {{ question.type === 'multiple_select' ? 'answers' : 'answer' }}</span></legend>
                    <div v-for="(option, optionIndex) in question.options" :key="option.id" class="mb-2 flex items-center gap-2">
                        <button type="button" :disabled="disabled" :aria-pressed="question.correct_answer_ids.includes(option.id)" :aria-label="`Mark option ${optionIndex + 1} correct`" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" :class="question.correct_answer_ids.includes(option.id) ? 'bg-emerald-50 text-emerald-600' : 'text-surface-300 hover:bg-surface-100'" @click="toggleCorrect(question, option.id)">
                            <template v-if="question.type === 'multiple_select'"><CheckSquare v-if="question.correct_answer_ids.includes(option.id)" class="h-5 w-5" /><Square v-else class="h-5 w-5" /></template>
                            <template v-else><CheckCircle2 v-if="question.correct_answer_ids.includes(option.id)" class="h-5 w-5" /><Circle v-else class="h-5 w-5" /></template>
                        </button>
                        <input v-model="option.text" :disabled="disabled || question.type === 'true_false'" :aria-label="`Option ${optionIndex + 1}`" :placeholder="`Option ${optionIndex + 1}`" class="min-w-0 flex-1 rounded-lg border border-surface-200 px-3 py-2 text-sm disabled:bg-surface-50" />
                        <button v-if="question.type !== 'true_false' && question.options.length > 2" type="button" :disabled="disabled" class="quiz-icon hover:text-rose-600" :aria-label="`Remove option ${optionIndex + 1}`" @click="removeOption(question, optionIndex)"><X class="h-4 w-4" /></button>
                    </div>
                    <button v-if="question.type !== 'true_false' && question.options.length < 10" type="button" :disabled="disabled" class="mt-1 text-xs font-semibold text-primary-700" @click="addOption(question)">+ Add option</button>
                </fieldset>
                <label class="mt-3 block text-xs font-medium text-surface-600">Explanation <span class="font-normal text-surface-400">(shown after the quiz)</span>
                    <textarea v-model="question.explanation" rows="2" :disabled="disabled" placeholder="Why is this the right answer?" class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm" />
                </label>
            </template>
            <p v-if="questionError(index)" role="alert" class="mt-2 text-xs text-rose-700">{{ questionError(index) }}</p>
        </article>

        <div class="flex flex-wrap gap-2">
            <button type="button" :disabled="disabled" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-40" @click="add('multiple_choice')"><Plus class="h-4 w-4" />Multiple choice</button>
            <button type="button" :disabled="disabled" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 px-4 py-2.5 text-xs font-semibold text-surface-700 hover:bg-surface-50" @click="add('multiple_select')"><Plus class="h-4 w-4" />Multiple select</button>
            <button type="button" :disabled="disabled" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 px-4 py-2.5 text-xs font-semibold text-surface-700 hover:bg-surface-50" @click="add('true_false')"><Plus class="h-4 w-4" />True / False</button>
            <button type="button" :disabled="disabled || !bank.length" :title="bank.length ? '' : 'No published multiple-choice questions in this course yet'" class="inline-flex items-center gap-2 rounded-xl border border-surface-200 px-4 py-2.5 text-xs font-semibold text-surface-700 hover:bg-surface-50 disabled:opacity-40" @click="picker = true"><Library class="h-4 w-4" />From question bank ({{ bank.length }})</button>
        </div>

        <Dialog :open="picker" class="relative z-50" @close="picker = false">
            <div class="fixed inset-0 bg-surface-950/50" aria-hidden="true" />
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <DialogPanel class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
                    <div class="border-b border-surface-100 p-5">
                        <DialogTitle class="text-lg font-semibold">Add questions from the bank</DialogTitle>
                        <label class="relative mt-3 block"><Search class="absolute left-3 top-2.5 h-4 w-4 text-surface-400" /><input v-model="search" type="search" placeholder="Search questions or domains" aria-label="Search question bank" class="w-full rounded-xl border border-surface-200 py-2 pl-9 pr-3 text-sm" /></label>
                    </div>
                    <div class="flex-1 space-y-2 overflow-y-auto p-5">
                        <label v-for="item in bankResults" :key="item.id" class="flex cursor-pointer gap-3 rounded-xl border border-surface-200 p-3 hover:bg-surface-50">
                            <input v-model="picked" type="checkbox" :value="item.id" class="mt-1 h-4 w-4 accent-primary-700" />
                            <span class="min-w-0"><span class="block text-sm text-surface-800">{{ item.stem }}</span><span class="mt-1 block text-[11px] text-surface-500">{{ typeLabels[item.type] }} · {{ item.domain }}</span></span>
                        </label>
                        <p v-if="!bankResults.length" class="py-8 text-center text-sm text-surface-500">No matching questions.</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-surface-100 p-4">
                        <button type="button" class="rounded-xl border border-surface-200 px-4 py-2 text-sm" @click="picker = false">Cancel</button>
                        <button type="button" :disabled="!picked.length" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" @click="addFromBank">Add {{ picked.length || '' }} question{{ picked.length === 1 ? '' : 's' }}</button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.quiz-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; color: #64748b; }
.quiz-icon:hover { background: #f1f5f9; }
.quiz-icon:disabled { opacity: .3; cursor: not-allowed; }
</style>

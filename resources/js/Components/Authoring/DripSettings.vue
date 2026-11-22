<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { CalendarClock, ListOrdered, Save, Wand2, Unlock } from 'lucide-vue-next';
import { lessonIcon, lessonTypeLabel } from './lessonTypes';

const props = defineProps({ course: { type: Object, required: true }, disabled: Boolean });

const toLocal = value => {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
const modules = computed(() => props.course.modules || []);
const state = reactive({ sequential: false, rows: {} });
const pending = ref(false), errors = ref([]), saved = ref(false);
const moduleGap = ref(7), lessonGap = ref(1);

function load() {
    state.sequential = !!props.course.sequential_lessons;
    state.rows = {};
    for (const module of modules.value) {
        for (const lesson of module.lessons || []) {
            state.rows[lesson.id] = {
                unlock_type: lesson.unlock_type || 'immediate',
                unlock_at: toLocal(lesson.unlock_at),
                unlock_days_after_enrollment: lesson.unlock_days_after_enrollment || '',
            };
        }
    }
}
watch(() => props.course, load, { immediate: true, deep: false });

const totalLessons = computed(() => Object.keys(state.rows).length);
const scheduled = computed(() => Object.values(state.rows).filter(row => row.unlock_type !== 'immediate').length);

function scheduleByModule() {
    const gap = Math.max(1, Number(moduleGap.value) || 1);
    modules.value.forEach((module, index) => (module.lessons || []).forEach(lesson => {
        state.rows[lesson.id] = index === 0
            ? { unlock_type: 'immediate', unlock_at: '', unlock_days_after_enrollment: '' }
            : { unlock_type: 'days_after_enrollment', unlock_at: '', unlock_days_after_enrollment: Math.min(365, index * gap) };
    }));
    saved.value = false;
}
function scheduleByLesson() {
    const gap = Math.max(1, Number(lessonGap.value) || 1);
    let position = 0;
    for (const module of modules.value) {
        for (const lesson of module.lessons || []) {
            state.rows[lesson.id] = position === 0
                ? { unlock_type: 'immediate', unlock_at: '', unlock_days_after_enrollment: '' }
                : { unlock_type: 'days_after_enrollment', unlock_at: '', unlock_days_after_enrollment: Math.min(365, position * gap) };
            position++;
        }
    }
    saved.value = false;
}
function releaseAll() {
    Object.keys(state.rows).forEach(id => { state.rows[id] = { unlock_type: 'immediate', unlock_at: '', unlock_days_after_enrollment: '' }; });
    saved.value = false;
}

function save() {
    errors.value = []; saved.value = false;
    router.put(`/authoring/courses/${props.course.id}/drip`, {
        sequential_lessons: state.sequential,
        lessons: Object.entries(state.rows).map(([id, row]) => ({
            id: Number(id),
            unlock_type: row.unlock_type,
            unlock_at: row.unlock_type === 'date' && row.unlock_at ? new Date(row.unlock_at).toISOString() : null,
            unlock_days_after_enrollment: row.unlock_type === 'days_after_enrollment' ? Number(row.unlock_days_after_enrollment) || null : null,
        })),
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { pending.value = true; },
        onFinish: () => { pending.value = false; },
        onSuccess: () => { saved.value = true; },
        onError: result => { errors.value = [...new Set(Object.values(result).flat())]; },
    });
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-surface-900 p-6 text-white">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-orange-300">DRIP CONTENT</p>
                <h2 class="mt-2 text-xl font-semibold">Release lessons on a schedule.</h2>
                <p class="mt-2 max-w-xl text-xs leading-5 text-surface-400">Unlock lessons a number of days after each learner enrolls, on a fixed date, or only after the previous lesson is complete.</p>
            </div>
            <div class="flex gap-5 text-center"><div><p class="text-xl font-semibold">{{ scheduled }}</p><p class="mt-1 text-[10px] text-surface-400">SCHEDULED</p></div><div><p class="text-xl font-semibold">{{ totalLessons - scheduled }}</p><p class="mt-1 text-[10px] text-surface-400">OPEN NOW</p></div></div>
        </div>

        <p v-if="disabled" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">This course is published or in review. Create a draft revision to change its drip schedule.</p>
        <div v-if="errors.length" role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800"><p v-for="message in errors" :key="message">{{ message }}</p></div>

        <fieldset :disabled="disabled || pending" class="space-y-5">
            <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
                <input v-model="state.sequential" type="checkbox" class="mt-1 h-5 w-5 accent-primary-700" @change="saved = false" />
                <span>
                    <span class="flex items-center gap-2 text-sm font-semibold text-surface-900"><ListOrdered class="h-4 w-4 text-primary-600" />Lock lessons in order</span>
                    <span class="mt-1 block text-xs leading-5 text-surface-500">Learners must complete each lesson (pass its quiz or submit its assignment) before the next one opens. Works together with the release dates below.</span>
                </span>
            </label>

            <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
                <h3 class="flex items-center gap-2 text-sm font-semibold"><Wand2 class="h-4 w-4 text-primary-600" />Quick schedule</h3>
                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                    <div class="flex items-end gap-2 rounded-xl bg-surface-50 p-3"><label class="flex-1 text-xs text-surface-600">Release a module every<span class="mt-1 flex items-center gap-2"><input v-model="moduleGap" type="number" min="1" max="365" class="w-20 rounded-lg border border-surface-200 px-2 py-1.5 text-sm" /> days</span></label><button type="button" class="rounded-lg bg-primary-700 px-3 py-2 text-xs font-semibold text-white" @click="scheduleByModule">Apply</button></div>
                    <div class="flex items-end gap-2 rounded-xl bg-surface-50 p-3"><label class="flex-1 text-xs text-surface-600">Release a lesson every<span class="mt-1 flex items-center gap-2"><input v-model="lessonGap" type="number" min="1" max="365" class="w-20 rounded-lg border border-surface-200 px-2 py-1.5 text-sm" /> days</span></label><button type="button" class="rounded-lg bg-primary-700 px-3 py-2 text-xs font-semibold text-white" @click="scheduleByLesson">Apply</button></div>
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-surface-50 p-3"><span class="text-xs text-surface-600">Open every lesson immediately</span><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-surface-200 bg-white px-3 py-2 text-xs font-semibold" @click="releaseAll"><Unlock class="h-3.5 w-3.5" />Apply</button></div>
                </div>
            </div>

            <div v-if="!modules.length" class="rounded-2xl border border-dashed border-surface-300 bg-white p-10 text-center text-sm text-surface-500">Add modules and lessons in the Curriculum tab, then schedule them here.</div>
            <section v-for="(module, moduleIndex) in modules" :key="module.id" class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
                <h3 class="border-b border-surface-100 bg-surface-50 px-5 py-3 text-sm font-semibold">{{ String(moduleIndex + 1).padStart(2, '0') }} · {{ module.title }}</h3>
                <div v-for="lesson in module.lessons" :key="lesson.id" class="grid items-center gap-3 border-b border-surface-100 px-5 py-3 last:border-b-0 md:grid-cols-[minmax(0,1fr)_220px_220px]">
                    <div class="flex min-w-0 items-center gap-3"><component :is="lessonIcon(lesson)" class="h-4 w-4 shrink-0 text-primary-600" /><div class="min-w-0"><p class="truncate text-sm font-medium">{{ lesson.title }}</p><p class="text-[11px] text-surface-400">{{ lessonTypeLabel(lesson) }}{{ lesson.is_published ? '' : ' · hidden' }}</p></div></div>
                    <select v-model="state.rows[lesson.id].unlock_type" :aria-label="`Release rule for ${lesson.title}`" class="rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" @change="saved = false">
                        <option value="immediate">Available immediately</option>
                        <option value="days_after_enrollment">Days after enrollment</option>
                        <option value="date">On a specific date</option>
                    </select>
                    <div>
                        <label v-if="state.rows[lesson.id].unlock_type === 'days_after_enrollment'" class="flex items-center gap-2 text-xs text-surface-500"><CalendarClock class="h-4 w-4" />Day<input v-model="state.rows[lesson.id].unlock_days_after_enrollment" type="number" min="1" max="365" required :aria-label="`Days after enrollment for ${lesson.title}`" class="w-24 rounded-lg border border-surface-200 px-2 py-1.5 text-sm" @input="saved = false" /></label>
                        <input v-else-if="state.rows[lesson.id].unlock_type === 'date'" v-model="state.rows[lesson.id].unlock_at" type="datetime-local" required :aria-label="`Release date for ${lesson.title}`" class="w-full rounded-lg border border-surface-200 px-2 py-1.5 text-sm" @input="saved = false" />
                        <span v-else class="text-xs text-surface-400">—</span>
                    </div>
                </div>
                <p v-if="!module.lessons?.length" class="px-5 py-4 text-xs text-surface-400">No lessons in this module yet.</p>
            </section>
        </fieldset>

        <div v-if="!disabled" class="sticky bottom-4 flex items-center justify-end gap-3 rounded-2xl border border-surface-200 bg-white/95 p-4 shadow-lg backdrop-blur">
            <span role="status" class="text-xs text-surface-500">{{ pending ? 'Saving…' : saved ? 'Drip schedule saved' : 'Changes apply after saving' }}</span>
            <button type="button" :disabled="pending" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-40" @click="save"><Save class="h-4 w-4" />Save drip schedule</button>
        </div>
    </div>
</template>

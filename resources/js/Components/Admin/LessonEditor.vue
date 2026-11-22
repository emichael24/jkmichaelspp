<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Eye, Pencil, Plus, X, Save, Inbox, Trash2 } from 'lucide-vue-next';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
import SafeHtml from '@/Components/SafeHtml.vue';
import RichTextEditor from '@/Components/Authoring/RichTextEditor.vue';
import MediaUpload from '@/Components/Authoring/MediaUpload.vue';
import QuizBuilder from '@/Components/Authoring/QuizBuilder.vue';
import CourseRevisionBanner from './CourseRevisionBanner.vue';
import LessonPreview from '@/Components/Authoring/LessonPreview.vue';
import { lessonTypes, lessonIcon } from '@/Components/Authoring/lessonTypes';

const props = defineProps({
    form: Object, course: Object, module: Object, lesson: Object, editing: Boolean,
    activity: { type: Object, default: null },
    questionBank: { type: Array, default: () => [] },
    workspace: { type: String, default: 'admin' },
});
const emit = defineEmits(['save']);
const uploadStates = ref({});
const uploading = computed(() => Object.values(uploadStates.value).some(Boolean));
const locked = computed(() => props.course.is_published || !!props.course.published_at || ['approved', 'pending_review'].includes(props.course.approval_status));
const save = () => { if (!locked.value && !uploading.value && !props.form.processing) emit('save'); };
const shortcut = event => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); save(); } };
const lessonLink = (module, lesson) => props.workspace === 'admin' ? `/admin/lessons/${lesson.id}/edit` : `/facilitator/courses/${props.course.id}/modules/${module.id}/lessons/${lesson.id}/edit`;

const type = computed(() => props.form.content_type);
const isQuiz = computed(() => type.value === 'quiz');
const isSubmission = computed(() => ['assignment', 'case_study'].includes(type.value));
const isActivity = computed(() => isQuiz.value || isSubmission.value);
const words = computed(() => (props.form.text_content || '').replace(/<[^>]*>/g, ' ').trim().split(/\s+/).filter(Boolean).length);

const checks = computed(() => {
    const base = [{ label: 'Lesson title', done: !!props.form.title?.trim() }];
    if (isQuiz.value) {
        base.push({ label: 'At least one question', done: props.form.activity.questions.length > 0 });
        base.push({ label: 'Every question has a correct answer', done: props.form.activity.questions.length > 0 && props.form.activity.questions.every(q => q.correct_answer_ids.length) });
    } else if (type.value === 'assignment') {
        base.push({ label: 'Instructions', done: words.value > 0 });
        base.push({ label: 'Maximum score', done: Number(props.form.activity.max_score) > 0 });
    } else if (type.value === 'case_study') {
        base.push({ label: 'Scenario', done: words.value > 0 || !!props.form.pdf_url });
        base.push({ label: 'At least one question', done: props.form.activity.prompts.some(p => p.trim()) });
    } else {
        base.push({ label: 'Learning content', done: words.value > 0 || ['video_url', 'audio_url', 'pdf_url', 'slides_url', 'scorm_path'].some(key => props.form[key]) });
        base.push({ label: 'Summary', done: !!props.form.summary?.trim() });
    }
    base.push({ label: 'Estimated duration', done: Number(props.form.duration_minutes) > 0 });
    return base;
});
const doneCount = computed(() => checks.value.filter(item => item.done).length);

const preview = ref(false);
const learnerPreview = ref(false);
const mediaFields = computed(() => [
    { type: 'video', field: 'video_url', title: 'Video lesson' },
    { type: 'audio', field: 'audio_url', title: 'Audio lesson' },
    { type: 'pdf', field: 'pdf_url', title: 'Lesson PDF' },
    { type: 'slides', field: 'slides_url', title: 'Presentation (PowerPoint .pptx or PDF)' },
].filter(field => type.value === 'mixed' || field.type === type.value));
const showArticle = computed(() => ['text', 'mixed', 'assignment', 'case_study'].includes(type.value));
const articleLabel = computed(() => ({ assignment: 'Assignment instructions', case_study: 'Case scenario' }[type.value] || 'Article'));
const showReference = computed(() => isSubmission.value);
const leaveWarning = event => { if ((props.form.isDirty || uploading.value) && !props.form.processing) { event.preventDefault(); event.returnValue = ''; } };
let removeNavigationGuard;
onMounted(() => {
    window.addEventListener('beforeunload', leaveWarning);
    window.addEventListener('keydown', shortcut);
    removeNavigationGuard = router.on('before', event => {
        if (event.detail.visit.method === 'get' && (props.form.isDirty || uploading.value) && !props.form.processing && !window.confirm('Leave this lesson without saving your changes?')) event.preventDefault();
    });
});
onUnmounted(() => { window.removeEventListener('beforeunload', leaveWarning); window.removeEventListener('keydown', shortcut); removeNavigationGuard?.(); });
</script>

<template>
    <div class="space-y-5">
    <CourseRevisionBanner v-if="locked" :course="course" :workspace="workspace" />
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-surface-900 px-6 py-5 text-white"><div><p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-orange-300">JK MICHAELS · LESSON STUDIO</p><h2 class="mt-1 text-lg font-semibold">{{ isActivity ? 'Build an activity that checks understanding.' : 'Build a lesson worth learning.' }}</h2><p class="mt-1 text-xs text-surface-400">Write, add media and refine the experience before it reaches your learners.</p></div><div class="flex gap-2"><button type="button" @click="learnerPreview = true" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 px-4 text-sm font-medium hover:bg-white/10"><Eye class="h-4 w-4" />Preview</button><button type="button" @click="save" :disabled="locked || form.processing || uploading" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary-600 px-5 text-sm font-semibold hover:bg-primary-500 disabled:opacity-40"><Save class="h-4 w-4" />{{ form.processing ? 'Saving…' : 'Save lesson' }}</button></div></div>
    <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="font-semibold">Your lesson has not been saved. Please review:</p><ul class="mt-2 list-inside list-disc"><li v-for="(error, key) in form.errors" :key="key">{{ error }}</li></ul></div>
    <LessonPreview :open="learnerPreview" :lesson="form" :module="module" @close="learnerPreview = false" />
    <form @submit.prevent="save" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
        <fieldset :disabled="locked" class="min-w-0 space-y-6">
            <section class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm sm:p-7">
                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-primary-700">01 · Lesson details</p><h2 class="mb-6 text-lg font-semibold">Give your lesson a clear purpose</h2>
                <div class="space-y-5">
                    <Input v-model="form.title" label="Lesson title" required :placeholder="isQuiz ? 'e.g. Module 1 knowledge check' : 'e.g. Build a project charter'" :error="form.errors.title" maxlength="255" />
                    <div><label for="lesson-summary" class="mb-1.5 block text-sm font-medium text-surface-700">Summary</label><textarea id="lesson-summary" v-model="form.summary" rows="3" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-100" :placeholder="isActivity ? 'What should learners demonstrate?' : 'What will learners understand or be able to do?'" /></div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-surface-700">Lesson type</legend>
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9">
                            <label v-for="format in lessonTypes" :key="format.value" :title="format.hint" :class="['relative flex cursor-pointer flex-col items-center gap-2 rounded-xl border px-2 py-4 text-center text-xs font-medium transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-700', form.content_type === format.value ? 'border-primary-600 bg-primary-50 text-primary-800' : 'border-surface-200 text-surface-600 hover:bg-surface-50']"><input v-model="form.content_type" type="radio" name="lesson-format" :value="format.value" class="sr-only" /><component :is="format.icon" class="h-5 w-5" aria-hidden="true" />{{ format.short }}</label>
                        </div>
                    </fieldset>
                </div>
            </section>

            <section class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3"><div><p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-primary-700">02 · {{ isQuiz ? 'Questions' : isSubmission ? 'Brief' : 'Learning content' }}</p><h2 class="text-lg font-semibold">{{ isQuiz ? 'Write the quiz' : type === 'case_study' ? 'Set the scene' : type === 'assignment' ? 'Explain the task' : 'Build the learning experience' }}</h2></div><button v-if="showArticle" type="button" @click="preview = !preview" :aria-pressed="preview" class="flex min-h-10 items-center gap-2 rounded-lg border border-surface-200 px-3 text-xs font-medium text-surface-600"><Pencil v-if="preview" class="h-4 w-4" /><Eye v-else class="h-4 w-4" />{{ preview ? 'Edit content' : 'Preview' }}</button></div>
                <div class="space-y-5">
                    <QuizBuilder v-if="isQuiz" :activity="form.activity" :bank="questionBank" :errors="form.errors" :disabled="locked" />
                    <div v-if="showArticle">
                        <p v-if="isSubmission" class="mb-2 text-sm font-semibold">{{ articleLabel }}</p>
                        <RichTextEditor v-if="!preview" v-model="form.text_content" :course-id="course.id" :disabled="locked" :placeholder="type === 'case_study' ? 'Describe the organization, the situation and the data learners need to analyse…' : type === 'assignment' ? 'Describe the deliverable, format, length and how it will be assessed…' : undefined" @busy="uploadStates.image = $event" />
                        <div v-else class="min-h-64 rounded-xl border border-surface-200 bg-surface-50 p-6"><p class="mb-5 text-[10px] font-semibold uppercase tracking-widest text-surface-500">{{ articleLabel }} preview</p><SafeHtml v-if="form.text_content" :content="form.text_content" /><p v-else class="text-sm text-surface-500">Add content to preview your lesson.</p></div>
                    </div>
                    <div v-if="showReference" class="space-y-3">
                        <h3 class="text-sm font-semibold">{{ type === 'case_study' ? 'Case exhibit' : 'Reference document' }} <span class="font-normal text-surface-400">(optional PDF)</span></h3>
                        <MediaUpload v-model="form.pdf_url" type="pdf" :allow-link="false" :course-id="course.id" :disabled="locked" @busy="uploadStates.pdf_url = $event" />
                        <p v-if="form.errors.pdf_url" role="alert" class="text-xs text-rose-700">{{ form.errors.pdf_url }}</p>
                    </div>
                    <div v-if="type === 'case_study'" class="space-y-3">
                        <h3 class="text-sm font-semibold">Case questions</h3>
                        <p class="text-xs text-surface-500">Learners answer each question in writing. You review and grade the responses.</p>
                        <div v-for="(prompt, index) in form.activity.prompts" :key="index" class="flex items-start gap-2">
                            <span class="mt-2.5 grid h-6 w-6 shrink-0 place-items-center rounded-md bg-primary-50 text-[11px] font-bold text-primary-700">{{ index + 1 }}</span>
                            <textarea v-model="form.activity.prompts[index]" rows="2" :aria-label="`Case question ${index + 1}`" placeholder="e.g. What are the three biggest risks to this project, and how would you respond?" class="min-w-0 flex-1 rounded-xl border border-surface-200 px-3 py-2 text-sm" />
                            <button type="button" :aria-label="`Remove case question ${index + 1}`" class="mt-1 grid h-9 w-9 place-items-center rounded-lg text-surface-400 hover:bg-rose-50 hover:text-rose-600" @click="form.activity.prompts.splice(index, 1)"><Trash2 class="h-4 w-4" /></button>
                        </div>
                        <Button type="button" variant="secondary" size="sm" @click="form.activity.prompts.push('')"><Plus class="mr-2 h-4 w-4" />Add question</Button>
                    </div>
                    <div v-if="isSubmission" class="grid gap-4 rounded-xl border border-surface-200 bg-surface-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-xs font-medium text-surface-600">Maximum score<input v-model="form.activity.max_score" type="number" min="1" max="1000" class="mt-1.5 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" /></label>
                        <label class="text-xs font-medium text-surface-600">Pass mark <span class="text-surface-400">(optional)</span><input v-model="form.activity.passing_score" type="number" min="0" :max="form.activity.max_score || 1000" class="mt-1.5 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" /></label>
                        <label class="text-xs font-medium text-surface-600">Resubmissions allowed<input v-model="form.activity.max_resubmissions" type="number" min="0" max="20" class="mt-1.5 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 text-sm" /></label>
                        <label class="flex items-center gap-2 self-end pb-2 text-xs font-medium text-surface-600"><input v-model="form.activity.allow_file_upload" type="checkbox" class="h-4 w-4 accent-primary-700" />Allow file attachments</label>
                        <p class="text-[11px] leading-5 text-surface-500 sm:col-span-2 lg:col-span-4">The lesson counts as complete when the learner submits. If you return work for revision, learners can resubmit up to the allowance.</p>
                    </div>
                    <div v-for="field in mediaFields" :key="field.field" class="space-y-3"><h3 class="text-sm font-semibold">{{ field.title }}</h3><MediaUpload v-model="form[field.field]" :type="field.type" :allow-link="!['pdf', 'slides'].includes(field.type)" :course-id="course.id" :disabled="locked" @busy="uploadStates[field.field] = $event" /><p v-if="form.errors[field.field]" role="alert" class="text-xs text-rose-700">{{ form.errors[field.field] }}</p><video v-if="field.type === 'video' && form[field.field]?.includes('/media/')" :src="form[field.field]" controls preload="metadata" class="max-h-80 w-full rounded-xl bg-black" /><audio v-if="field.type === 'audio' && form[field.field]" :src="form[field.field]" controls preload="metadata" class="w-full" /></div>
                    <div v-if="['video', 'mixed'].includes(type)"><label for="lesson-transcript" class="mb-1.5 block text-sm font-medium text-surface-700">Video transcript</label><textarea id="lesson-transcript" v-model="form.video_transcript" rows="5" class="w-full rounded-xl border border-surface-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-100" placeholder="Include a transcript for learners who prefer reading or need an accessible alternative." /></div>
                </div>
            </section>

            <section v-if="!isActivity" class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm sm:p-7"><p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-primary-700">03 · Reinforcement</p><h2 class="mb-5 text-lg font-semibold">Key terms</h2><p v-if="!form.key_terms.length" class="mb-4 text-sm text-surface-500">Add definitions learners can revisit while studying.</p><div v-for="(term, index) in form.key_terms" :key="index" class="mb-4 flex items-start gap-2"><div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[1fr_2fr]"><Input v-model="term.term" :label="`Term ${index + 1}`" placeholder="Term" /><Input v-model="term.definition" label="Definition" placeholder="Explain the meaning" /></div><button type="button" @click="form.key_terms.splice(index, 1)" :aria-label="`Remove term ${index + 1}`" class="mt-6 grid h-11 w-11 shrink-0 place-items-center rounded-lg text-surface-500 hover:bg-rose-50 hover:text-rose-700"><X class="h-4 w-4" /></button></div><Button type="button" variant="secondary" size="sm" @click="form.key_terms.push({ term: '', definition: '' })"><Plus class="mr-2 h-4 w-4" /> Add key term</Button></section>
            <details v-if="type === 'mixed' || form.scorm_path" class="rounded-2xl border border-surface-200 bg-white p-5 sm:p-7" :open="!!form.scorm_path"><summary class="cursor-pointer text-sm font-semibold">Interactive package (SCORM)</summary><p class="mt-2 text-xs leading-5 text-surface-500">Upload a SCORM 1.2 or 2004 package. The package becomes the primary learning content for this lesson.</p><div class="mt-5"><MediaUpload v-model="form.scorm_path" type="scorm" :course-id="course.id" :disabled="locked" @busy="uploadStates.scorm = $event" @uploaded="form.scorm_version = $event.scorm_version" /><p v-if="form.scorm_path" class="mt-3 text-xs text-surface-500">SCORM {{ form.scorm_version }} detected automatically.</p></div></details>
        </fieldset>

        <aside class="space-y-5 xl:sticky xl:top-24">
            <section class="rounded-2xl border border-surface-200 bg-white p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-semibold">Lesson checklist</h2><span class="text-xs font-semibold text-primary-700">{{ doneCount }}/{{ checks.length }}</span></div><div class="my-4 h-1.5 rounded-full bg-surface-100"><div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${(doneCount / checks.length) * 100}%` }" /></div><ul class="space-y-3"><li v-for="item in checks" :key="item.label" class="flex items-center gap-2 text-xs" :class="item.done ? 'text-surface-700' : 'text-surface-400'"><CheckCircle2 class="h-4 w-4" :class="item.done ? 'text-emerald-600' : 'text-surface-300'" />{{ item.label }}</li></ul></section>
            <section v-if="editing && isSubmission" class="rounded-2xl border border-primary-100 bg-primary-50 p-5"><h2 class="flex items-center gap-2 text-sm font-semibold text-primary-950"><Inbox class="h-4 w-4" />Submissions</h2><p class="mt-2 text-xs text-primary-800">{{ activity?.submission_count || 0 }} received · {{ activity?.pending_count || 0 }} awaiting review</p><Link :href="`/authoring/lessons/${lesson.id}/submissions`" class="mt-3 inline-block text-xs font-semibold text-primary-800 underline">Review and grade →</Link></section>
            <section class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-semibold">Lesson settings</h2><p class="mt-1 text-xs leading-5 text-surface-500">These settings apply when the course and module are published.</p><div class="mt-5 space-y-5"><Input v-model="form.duration_minutes" type="number" min="0" label="Estimated duration (minutes)" :error="form.errors.duration_minutes" /><label class="flex items-start gap-3 text-sm"><input v-model="form.is_published" type="checkbox" class="mt-1 h-4 w-4 accent-primary-700" /><span>Visible lesson<span class="mt-1 block text-xs text-surface-500">Include this lesson in the course.</span></span></label><label class="flex items-start gap-3 text-sm"><input v-model="form.is_free_preview" type="checkbox" class="mt-1 h-4 w-4 accent-primary-700" /><span>Free preview<span class="mt-1 block text-xs text-surface-500">Let signed-in visitors view before enrolling.</span></span></label><label v-if="!isQuiz" class="flex items-start gap-3 text-sm"><input v-model="form.is_downloadable" type="checkbox" class="mt-1 h-4 w-4 accent-primary-700" /><span>Allow source downloads<span class="mt-1 block text-xs leading-5 text-surface-500">Leave off for protected PDF/PPTX reading. Watermarked pages remain visible; screenshots cannot be prevented.</span></span></label></div></section>
            <section class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm"><h2 class="mb-1 text-sm font-semibold">Drip release</h2><p class="mb-4 text-xs text-surface-500">Manage every lesson at once from the course's Drip tab.</p><label for="lesson-unlock" class="mb-2 block text-xs text-surface-500">When can learners begin?</label><select id="lesson-unlock" v-model="form.unlock_type" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-2.5 text-sm"><option value="immediate">Immediately</option><option value="date">On a specific date</option><option value="days_after_enrollment">Days after enrollment</option></select><div class="mt-4"><Input v-if="form.unlock_type === 'date'" v-model="form.unlock_at" label="Release date and time" type="datetime-local" :error="form.errors.unlock_at" required /><Input v-if="form.unlock_type === 'days_after_enrollment'" v-model="form.unlock_days_after_enrollment" label="Days after enrollment" type="number" min="1" max="365" :error="form.errors.unlock_days_after_enrollment" required /></div></section>
            <div class="rounded-2xl border border-surface-200 bg-surface-900 p-5"><p role="status" class="mb-4 text-xs text-surface-300">{{ uploading ? 'Waiting for your upload to finish…' : form.processing ? 'Saving your lesson…' : form.isDirty ? 'You have unsaved changes' : editing ? 'All changes saved' : 'Ready to create your lesson' }}</p><Button type="submit" class="w-full" :disabled="form.processing || uploading || locked"><Save class="mr-2 h-4 w-4" />{{ form.processing ? 'Saving…' : 'Save lesson' }}</Button><Link :href="`/${workspace}/courses/${course.id}/edit?tab=curriculum`" class="mt-3 block py-2 text-center text-xs text-surface-300 hover:text-white">Back to course</Link></div>
            <details class="rounded-2xl border border-surface-200 bg-white p-5" open><summary class="cursor-pointer text-sm font-semibold">Course outline</summary><div class="mt-4 max-h-64 space-y-4 overflow-auto"><div v-for="outlineModule in course.modules || [module]" :key="outlineModule.id"><p class="mb-2 text-[11px] font-semibold text-surface-500">{{ outlineModule.title }}</p><Link v-for="outlineLesson in outlineModule.lessons || []" :key="outlineLesson.id" :href="lessonLink(outlineModule, outlineLesson)" :class="['mb-1 flex items-center gap-2 rounded-lg px-2 py-2 text-xs', outlineLesson.id === lesson?.id ? 'bg-primary-50 font-semibold text-primary-800' : 'text-surface-600 hover:bg-surface-50']"><component :is="lessonIcon(outlineLesson)" class="h-3.5 w-3.5 shrink-0" /><span class="truncate">{{ outlineLesson.title }}</span></Link><p v-if="!editing && outlineModule.id === module.id" class="rounded-lg bg-primary-50 px-2 py-2 text-xs font-semibold text-primary-800">{{ form.title || 'New lesson' }}</p></div></div><Link :href="`/${workspace}/courses/${course.id}/edit?tab=curriculum`" class="mt-4 block text-xs font-semibold text-primary-700">Manage curriculum →</Link></details>
            <p class="px-2 text-center text-[11px] leading-5 text-surface-400">Ctrl / ⌘ + S to save. Changes stay in the editor until you save.</p>
        </aside>
    </form>
    </div>
</template>

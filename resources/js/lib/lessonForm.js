import { useForm } from '@inertiajs/vue3';

export const LESSON_TYPES = ['text', 'video', 'audio', 'pdf', 'slides', 'mixed', 'quiz', 'assignment', 'case_study'];
export const ACTIVITY_TYPES = ['quiz', 'assignment', 'case_study'];

const blank = value => value === '' || value === undefined ? null : value;

function activityDefaults(activity = {}) {
    return {
        passing_score_pct: activity.passing_score_pct ?? 70,
        time_limit_minutes: activity.time_limit_minutes ?? '',
        max_attempts: activity.max_attempts ?? '',
        is_randomized: !!activity.is_randomized,
        questions: (activity.questions || []).map(question => ({
            ...question,
            options: question.options.map(option => ({ ...option })),
            correct_answer_ids: [...question.correct_answer_ids],
        })),
        max_score: activity.max_score ?? 100,
        passing_score: activity.passing_score ?? '',
        allow_file_upload: activity.allow_file_upload ?? true,
        max_resubmissions: activity.max_resubmissions ?? 1,
        prompts: [...(activity.prompts || [])],
    };
}

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

/**
 * Shared lesson form for admin and facilitator lesson pages. The activity block
 * (quiz questions, assignment and case-study settings) is saved with the lesson.
 */
export function useLessonForm({ lesson = null, activity = null, initialType = 'text', url, method = 'post' }) {
    const type = LESSON_TYPES.includes(lesson?.content_type) ? lesson.content_type : (LESSON_TYPES.includes(initialType) ? initialType : 'text');
    const form = useForm({
        title: lesson?.title || '',
        content_type: type,
        text_content: lesson?.text_content || '',
        summary: lesson?.summary || '',
        key_terms: lesson?.key_terms?.length ? lesson.key_terms.map(term => ({ ...term })) : [],
        video_url: lesson?.video_url || '',
        video_duration_seconds: lesson?.video_duration_seconds || '',
        video_transcript: lesson?.video_transcript || '',
        audio_url: lesson?.audio_url || '',
        audio_duration_seconds: lesson?.audio_duration_seconds || '',
        pdf_url: lesson?.pdf_url || '',
        slides_url: lesson?.slides_url || '',
        scorm_path: lesson?.scorm_path || '',
        scorm_version: lesson?.scorm_version || '',
        duration_minutes: lesson?.duration_minutes || '',
        is_free_preview: lesson?.is_free_preview || false,
        is_downloadable: lesson?.is_downloadable || false,
        is_published: lesson ? (lesson.is_published ?? true) : true,
        unlock_type: lesson?.unlock_type || 'immediate',
        unlock_at: toDateTimeLocal(lesson?.unlock_at),
        unlock_days_after_enrollment: lesson?.unlock_days_after_enrollment || '',
        activity: activityDefaults(activity || {}),
    });

    function submit() {
        form.key_terms = form.key_terms.filter(term => term.term?.trim());
        form.transform(data => {
            const isActivity = ACTIVITY_TYPES.includes(data.content_type);
            const a = data.activity;
            return {
                ...data,
                stay_in_editor: true,
                unlock_at: data.unlock_at ? new Date(data.unlock_at).toISOString() : null,
                duration_minutes: blank(data.duration_minutes),
                video_duration_seconds: blank(data.video_duration_seconds),
                audio_duration_seconds: blank(data.audio_duration_seconds),
                unlock_days_after_enrollment: blank(data.unlock_days_after_enrollment),
                scorm_version: blank(data.scorm_version),
                activity: isActivity ? {
                    passing_score_pct: blank(a.passing_score_pct),
                    time_limit_minutes: blank(a.time_limit_minutes),
                    max_attempts: blank(a.max_attempts),
                    is_randomized: !!a.is_randomized,
                    questions: data.content_type === 'quiz' ? a.questions.map(question => ({
                        id: question.id || null,
                        type: question.type,
                        stem: question.stem,
                        options: question.options.map(option => ({ id: option.id, text: option.text })),
                        correct_answer_ids: question.correct_answer_ids,
                        explanation: question.explanation || null,
                    })) : [],
                    max_score: blank(a.max_score),
                    passing_score: blank(a.passing_score),
                    allow_file_upload: !!a.allow_file_upload,
                    max_resubmissions: blank(a.max_resubmissions),
                    prompts: data.content_type === 'case_study' ? a.prompts.filter(prompt => prompt.trim()) : [],
                } : null,
            };
        });
        form[method](url, { preserveScroll: true, onSuccess: () => form.defaults() });
    }

    return { form, submit };
}

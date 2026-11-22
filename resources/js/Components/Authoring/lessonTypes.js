import { FileText, File, Presentation, Play, Headphones, Layers, ListChecks, ClipboardList, Briefcase } from 'lucide-vue-next';

/** Lesson formats shown in the "Select lesson type" picker, curriculum lists and the lesson player. */
export const lessonTypes = [
    { value: 'text', label: 'Text lesson', short: 'Article', icon: FileText, group: 'content', hint: 'Rich article with images and tables' },
    { value: 'pdf', label: 'PDF lesson', short: 'PDF', icon: File, group: 'content', hint: 'Protected in-lesson PDF reader' },
    { value: 'slides', label: 'PowerPoint lesson', short: 'Slides', icon: Presentation, group: 'content', hint: 'Upload a .pptx or PDF deck' },
    { value: 'video', label: 'Video lesson', short: 'Video', icon: Play, group: 'content', hint: 'Upload or link YouTube/Vimeo' },
    { value: 'audio', label: 'Audio lesson', short: 'Audio', icon: Headphones, group: 'content', hint: 'Podcast-style listening' },
    { value: 'mixed', label: 'Mixed / SCORM', short: 'Mixed', icon: Layers, group: 'content', hint: 'Combine formats or upload SCORM' },
    { value: 'quiz', label: 'Quiz', short: 'Quiz', icon: ListChecks, group: 'assessment', hint: 'Auto-graded questions with a pass mark' },
    { value: 'assignment', label: 'Assignment', short: 'Assignment', icon: ClipboardList, group: 'assessment', hint: 'Written or file submission, graded by you' },
    { value: 'case_study', label: 'Case study', short: 'Case study', icon: Briefcase, group: 'assessment', hint: 'Scenario with questions learners answer' },
];

export const lessonTypeMap = Object.fromEntries(lessonTypes.map(type => [type.value, type]));

export const lessonIcon = lesson => lessonTypeMap[lesson?.scorm_path ? 'mixed' : lesson?.content_type]?.icon || FileText;

export const lessonTypeLabel = lesson => lesson?.scorm_path ? 'SCORM' : (lessonTypeMap[lesson?.content_type]?.short || lesson?.content_type);

<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ScormProgress;
use App\Models\SpacedRepetitionItem;
use App\Services\CourseCompletionService;
use App\Services\LearningAccessService;
use App\Services\LessonActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LessonController extends Controller
{
    /**
     * Show the lesson viewer page.
     */
    public function show(Course $course, Lesson $lesson)
    {
        $user = Auth::user();
        $access = app(LearningAccessService::class);
        $lesson->loadMissing('module.course');
        $activeEnrollment = $course->enrollmentFor($user);
        if ($lesson->module?->course_id === $course->id && $course->is_published && $lesson->is_published && $activeEnrollment?->allowsAccess()) {
            // Explain drip and sequence locks with a redirect rather than a bare 403 page.
            if (! $lesson->isUnlockedFor($activeEnrollment)) {
                return redirect()->route('courses.show', $course->slug)
                    ->with('error', $lesson->availabilityLabel($activeEnrollment) ?? 'This lesson is not yet available.');
            }
            if ($blocker = $access->sequentialBlocker($lesson, $user)) {
                return redirect()->route('lessons.show', ['course' => $course->slug, 'lesson' => $blocker['id']])
                    ->with('error', 'This course unlocks lessons in order. Complete “'.$blocker['title'].'” first.');
            }
        }
        $access->assertLesson($lesson, $user, true);

        $lesson->loadMissing('module');
        abort_unless($lesson->module && $lesson->module->course_id === $course->id, 404);

        $enrollment = $course->enrollmentFor($user);
        $canPreview = $lesson->is_free_preview && $lesson->isUnlockedFor($enrollment);

        if ($enrollment && ! $this->enrollmentAllowsAccess($enrollment) && ! $canPreview) {
            $message = $enrollment->status === 'suspended'
                ? 'Your enrollment is paused. Please contact support.'
                : 'Your enrollment has expired. Please contact support.';

            return redirect()->route('courses.show', $course->slug)
                ->with('error', $message);
        }

        if (! $enrollment && ! $canPreview) {
            return redirect()->route('courses.show', $course->slug)
                ->with('error', 'Please enroll in this course to access this lesson.');
        }

        if (! $lesson->isUnlockedFor($enrollment)) {
            return redirect()->route('courses.show', $course->slug)
                ->with('error', $lesson->availabilityLabel($enrollment) ?? 'This lesson is not yet available.');
        }

        $course->load([
            'modules' => fn ($q) => $q->published()->orderBy('sort_order'),
            'modules.lessons' => fn ($q) => $q->published()->orderBy('sort_order')->select([
                'id',
                'module_id',
                'title',
                'slug',
                'content_type',
                'duration_minutes',
                'is_free_preview',
                'unlock_type',
                'unlock_at',
                'unlock_days_after_enrollment',
                'sort_order',
            ]),
        ]);

        $this->sequenceLocks = $enrollment && $this->enrollmentAllowsAccess($enrollment)
            ? $access->sequentialLocks($course, $user, $course->modules->flatMap(fn ($module) => $module->lessons->map(fn ($item) => ['id' => $item->id, 'title' => $item->title]))->values()->all())
            : [];

        $allLessons = collect();
        foreach ($course->modules as $module) {
            foreach ($module->lessons as $courseLesson) {
                $allLessons->push([
                    'id' => $courseLesson->id,
                    'title' => $courseLesson->title,
                    'slug' => $courseLesson->slug,
                    'module_title' => $module->title,
                    'content_type' => $courseLesson->content_type,
                    'duration_minutes' => $courseLesson->duration_minutes,
                    'is_free_preview' => $courseLesson->is_free_preview,
                    'can_access' => $this->canAccessLesson($courseLesson, $enrollment),
                    'is_locked' => ! $this->canAccessLesson($courseLesson, $enrollment),
                    'availability_label' => $this->availabilityLabel($courseLesson, $enrollment),
                ]);
            }
        }

        $currentIndex = $allLessons->search(fn ($item) => $item['id'] === $lesson->id);
        $prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['status' => 'not_started', 'progress_pct' => 0]
        );

        if ($progress->status === 'not_started') {
            $progress->update(['status' => 'in_progress']);
            $progress->refresh();
        }

        $notes = $user->notes()
            ->where('lesson_id', $lesson->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $isBookmarked = $user->bookmarks()
            ->where('bookmarkable_type', Lesson::class)
            ->where('bookmarkable_id', $lesson->id)
            ->exists();

        $lessonProgressMap = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $allLessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id')
            ->map(fn ($item) => [
                'status' => $item->status,
                'progress_pct' => $item->progress_pct,
            ]);

        $scormProgress = ScormProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return Inertia::render('Lessons/Show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'content_type' => $lesson->content_type,
                'text_content' => $lesson->text_content,
                'summary' => $lesson->summary,
                'key_terms' => $lesson->key_terms ?? [],
                'video_url' => $lesson->video_url,
                'video_duration_seconds' => $lesson->video_duration_seconds,
                'video_transcript' => $lesson->video_transcript,
                'video_chapters' => $lesson->video_chapters ?? [],
                'audio_url' => $lesson->audio_url,
                'audio_duration_seconds' => $lesson->audio_duration_seconds,
                'pdf_url' => $lesson->pdf_url,
                'slides_url' => $lesson->slides_url,
                'scorm_path' => $lesson->scorm_path,
                'scorm_version' => $lesson->scorm_version,
                'scorm_progress' => $scormProgress ? [
                    'lesson_status' => $scormProgress->cmi_core_lesson_status,
                    'score_raw' => $scormProgress->cmi_core_score_raw,
                    'lesson_location' => $scormProgress->cmi_core_lesson_location,
                    'suspend_data' => $scormProgress->suspend_data,
                ] : null,
                'duration_minutes' => $lesson->duration_minutes,
                'is_downloadable' => $lesson->is_downloadable,
                'module_title' => $lesson->module->title,
                'availability_label' => $lesson->availabilityLabel($enrollment),
                'is_activity' => $lesson->isActivity(),
            ],
            'activity' => app(LessonActivityService::class)->learnerPayload($lesson, $user),
            'sequential' => (bool) $course->sequential_lessons,
            'progress' => $progress,
            'notes' => $notes,
            'isBookmarked' => $isBookmarked,
            'allLessons' => $allLessons->values(),
            'currentIndex' => $currentIndex,
            'prevLesson' => $prevLesson,
            'nextLesson' => $nextLesson,
            'lessonProgress' => $lessonProgressMap,
            'modules' => $course->modules->map(function ($module) use ($enrollment) {
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $module->lessons->map(fn ($courseLesson) => [
                        'id' => $courseLesson->id,
                        'title' => $courseLesson->title,
                        'slug' => $courseLesson->slug,
                        'content_type' => $courseLesson->content_type,
                        'can_access' => $this->canAccessLesson($courseLesson, $enrollment),
                        'is_locked' => ! $this->canAccessLesson($courseLesson, $enrollment),
                        'availability_label' => $this->availabilityLabel($courseLesson, $enrollment),
                    ]),
                ];
            }),
        ]);
    }

    /**
     * Update lesson progress (video position, time spent, percentage).
     */
    public function updateProgress(Request $request, Lesson $lesson)
    {
        app(LearningAccessService::class)->assertLesson($lesson, $request->user());
        $validated = $request->validate([
            'progress_pct' => 'sometimes|integer|min:0|max:100',
            'video_position_sec' => 'sometimes|integer|min:0',
            'time_spent_seconds' => 'sometimes|integer|min:0',
        ]);

        app(CourseCompletionService::class)->progress($request->user(), $lesson, $validated);

        return back()->with('success', 'Progress updated.');
    }

    /**
     * Mark a lesson as complete.
     */
    public function complete(Lesson $lesson)
    {
        app(LearningAccessService::class)->assertLesson($lesson, Auth::user());
        abort_if($lesson->scorm_path, 422, 'Complete the interactive activity to finish this lesson.');
        abort_if($lesson->isActivity(), 422, $lesson->content_type === 'quiz'
            ? 'Pass the quiz to complete this lesson.' : 'Submit your work to complete this lesson.');
        app(CourseCompletionService::class)->progress(Auth::user(), $lesson, [], true);

        return back()->with('success', 'Lesson completed.');
    }

    /**
     * Toggle bookmark on a lesson.
     */
    public function toggleBookmark(Lesson $lesson)
    {
        app(LearningAccessService::class)->assertLesson($lesson, Auth::user(), true);
        $user = Auth::user();
        $existing = $user->bookmarks()
            ->where('bookmarkable_type', Lesson::class)
            ->where('bookmarkable_id', $lesson->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Bookmark removed.');
        }

        $user->bookmarks()->create([
            'bookmarkable_type' => Lesson::class,
            'bookmarkable_id' => $lesson->id,
        ]);

        return back()->with('success', 'Lesson bookmarked.');
    }

    /**
     * Generate a signed download URL for a lesson resource.
     */
    public function download(Lesson $lesson, string $type)
    {
        app(LearningAccessService::class)->assertLesson($lesson, Auth::user());
        abort_unless($lesson->is_downloadable, 403, 'Downloads are disabled for this lesson.');
        $url = match ($type) {
            'pdf' => $lesson->pdf_url,
            'slides' => $lesson->slides_url,
            'audio' => $lesson->audio_url,
            'video' => $lesson->video_url,
            default => null,
        };

        if (! $url) {
            return back()->with('error', 'File not available for download.');
        }

        if (preg_match('~/media/[a-f0-9-]{36}$~i', $url)) {
            return Inertia::location($url.'?download=1');
        }

        if (str_starts_with($url, 's3://') || str_starts_with($url, 'uploads/')) {
            $signedUrl = Storage::temporaryUrl($url, now()->addMinutes(30));

            return Inertia::location($signedUrl);
        }

        return Inertia::location($url);
    }

    /**
     * Create flashcards from lesson key terms.
     */
    public function createFlashcards(Lesson $lesson)
    {
        $user = Auth::user();
        app(LearningAccessService::class)->assertLesson($lesson, $user, true);
        $lesson->loadMissing('module.course');

        $course = $lesson->module->course;
        $enrollment = $course->enrollmentFor($user);
        $canPreview = $lesson->is_free_preview && $lesson->isUnlockedFor($enrollment);

        if (! $enrollment && ! $canPreview) {
            return back()->with('error', 'Please enroll in this course to create flashcards.');
        }

        if ($enrollment && ! $this->enrollmentAllowsAccess($enrollment) && ! $canPreview) {
            return back()->with('error', 'Your enrollment is not active for this course.');
        }

        if (! $lesson->isUnlockedFor($enrollment)) {
            return back()->with('error', $lesson->availabilityLabel($enrollment) ?? 'This lesson is not yet available.');
        }

        $keyTerms = $lesson->key_terms ?? [];
        if (! is_array($keyTerms) || empty($keyTerms)) {
            return back()->with('error', 'No key terms are available for this lesson.');
        }

        $cards = Flashcard::createFromKeyTerms($lesson, $keyTerms, $user->id);

        foreach ($cards as $card) {
            SpacedRepetitionItem::ensureExists($user->id, Flashcard::class, $card->id);
        }

        return back()->with('success', sprintf('Added %d flashcards to your review deck.', count($cards)));
    }

    /** @var array<int, string> lesson id => title of the lesson that must be completed first */
    private array $sequenceLocks = [];

    private function canAccessLesson(Lesson $lesson, $enrollment): bool
    {
        $canEnroll = $enrollment !== null && $this->enrollmentAllowsAccess($enrollment);

        return ($canEnroll || $lesson->is_free_preview) && $lesson->isUnlockedFor($enrollment) && ! isset($this->sequenceLocks[$lesson->id]);
    }

    private function availabilityLabel(Lesson $lesson, $enrollment): ?string
    {
        if (isset($this->sequenceLocks[$lesson->id]) && $lesson->isUnlockedFor($enrollment)) {
            return 'Complete “'.$this->sequenceLocks[$lesson->id].'” first';
        }

        if ($enrollment !== null && ! $this->enrollmentAllowsAccess($enrollment)) {
            return $enrollment->status === 'suspended' ? 'Enrollment paused' : 'Enrollment expired';
        }

        if ($enrollment === null && ! $lesson->is_free_preview) {
            return 'Enroll to unlock';
        }

        return $lesson->availabilityLabel($enrollment);
    }

    private function enrollmentAllowsAccess($enrollment): bool
    {
        return in_array($enrollment->status, ['active', 'completed'], true);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\UserNote;
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

        // Verify the user is enrolled
        $enrollment = $course->enrollmentFor($user);
        if (! $enrollment && ! $lesson->is_free_preview) {
            return redirect()->route('courses.show', $course->slug)
                ->with('error', 'Please enroll in this course to access this lesson.');
        }

        // Load lesson with module context
        $lesson->load('module');

        // Get all lessons in this course for navigation
        $course->load([
            'modules' => fn ($q) => $q->published()->orderBy('sort_order'),
            'modules.lessons' => fn ($q) => $q->published()->orderBy('sort_order')->select([
                'id', 'module_id', 'title', 'slug', 'content_type',
                'duration_minutes', 'is_free_preview', 'sort_order',
            ]),
        ]);

        // Build ordered flat list of lessons for prev/next navigation
        $allLessons = collect();
        foreach ($course->modules as $module) {
            foreach ($module->lessons as $l) {
                $allLessons->push([
                    'id'              => $l->id,
                    'title'           => $l->title,
                    'slug'            => $l->slug,
                    'module_title'    => $module->title,
                    'content_type'    => $l->content_type,
                    'duration_minutes' => $l->duration_minutes,
                    'is_free_preview' => $l->is_free_preview,
                ]);
            }
        }

        $currentIndex = $allLessons->search(fn ($l) => $l['id'] === $lesson->id);
        $prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        // Get or create lesson progress
        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['status' => 'not_started', 'progress_pct' => 0]
        );

        // Mark as in_progress if not_started
        if ($progress->status === 'not_started') {
            $progress->update(['status' => 'in_progress']);
        }

        // Get user notes for this lesson
        $notes = $user->notes()
            ->where('lesson_id', $lesson->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Check bookmark status
        $isBookmarked = $user->bookmarks()
            ->where('bookmarkable_type', Lesson::class)
            ->where('bookmarkable_id', $lesson->id)
            ->exists();

        // Get lesson progress for all lessons (for sidebar)
        $lessonProgressMap = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $allLessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id')
            ->map(fn ($p) => [
                'status'       => $p->status,
                'progress_pct' => $p->progress_pct,
            ]);

        return Inertia::render('Lessons/Show', [
            'course'        => [
                'id'    => $course->id,
                'title' => $course->title,
                'slug'  => $course->slug,
            ],
            'lesson'        => [
                'id'                    => $lesson->id,
                'title'                 => $lesson->title,
                'slug'                  => $lesson->slug,
                'content_type'          => $lesson->content_type,
                'text_content'          => $lesson->text_content,
                'summary'               => $lesson->summary,
                'key_terms'             => $lesson->key_terms ?? [],
                'video_url'             => $lesson->video_url,
                'video_duration_seconds' => $lesson->video_duration_seconds,
                'video_transcript'      => $lesson->video_transcript,
                'video_chapters'        => $lesson->video_chapters ?? [],
                'audio_url'             => $lesson->audio_url,
                'audio_duration_seconds' => $lesson->audio_duration_seconds,
                'pdf_url'               => $lesson->pdf_url,
                'slides_url'            => $lesson->slides_url,
                'duration_minutes'      => $lesson->duration_minutes,
                'is_downloadable'       => $lesson->is_downloadable,
                'module_title'          => $lesson->module->title,
            ],
            'progress'       => $progress,
            'notes'          => $notes,
            'isBookmarked'   => $isBookmarked,
            'allLessons'     => $allLessons->values(),
            'currentIndex'   => $currentIndex,
            'prevLesson'     => $prevLesson,
            'nextLesson'     => $nextLesson,
            'lessonProgress' => $lessonProgressMap,
            'modules'        => $course->modules->map(fn ($m) => [
                'id'     => $m->id,
                'title'  => $m->title,
                'lessons' => $m->lessons->map(fn ($l) => [
                    'id'    => $l->id,
                    'title' => $l->title,
                    'slug'  => $l->slug,
                ]),
            ]),
        ]);
    }

    /**
     * Update lesson progress (video position, time spent, percentage).
     */
    public function updateProgress(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'progress_pct'      => 'sometimes|integer|min:0|max:100',
            'video_position_sec' => 'sometimes|integer|min:0',
            'time_spent_seconds' => 'sometimes|integer|min:0',
        ]);

        $progress = LessonProgress::updateOrCreate(
            [
                'user_id'   => Auth::id(),
                'lesson_id' => $lesson->id,
            ],
            array_merge(
                ['status' => 'in_progress'],
                $validated
            )
        );

        // Recalculate course progress
        $this->recalculateCourseProgress($lesson);

        return back()->with('success', 'Progress updated.');
    }

    /**
     * Mark a lesson as complete.
     */
    public function complete(Lesson $lesson)
    {
        $progress = LessonProgress::updateOrCreate(
            [
                'user_id'   => Auth::id(),
                'lesson_id' => $lesson->id,
            ],
            [
                'status'       => 'completed',
                'progress_pct' => 100,
                'completed_at' => now(),
            ]
        );

        // Recalculate course progress
        $this->recalculateCourseProgress($lesson);

        return back()->with('success', 'Lesson completed! 🎉');
    }

    /**
     * Toggle bookmark on a lesson.
     */
    public function toggleBookmark(Lesson $lesson)
    {
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
            'bookmarkable_id'   => $lesson->id,
        ]);

        return back()->with('success', 'Lesson bookmarked!');
    }

    /**
     * Generate a signed download URL for a lesson resource.
     */
    public function download(Lesson $lesson, string $type)
    {
        $url = match ($type) {
            'pdf'    => $lesson->pdf_url,
            'slides' => $lesson->slides_url,
            'audio'  => $lesson->audio_url,
            'video'  => $lesson->video_url,
            default  => null,
        };

        if (! $url) {
            return back()->with('error', 'File not available for download.');
        }

        // If it's an S3 URL, generate a signed URL
        if (str_starts_with($url, 's3://') || str_starts_with($url, 'uploads/')) {
            $signedUrl = Storage::temporaryUrl(
                $url,
                now()->addMinutes(30)
            );
            return Inertia::location($signedUrl);
        }

        // External URL — redirect directly
        return Inertia::location($url);
    }

    /**
     * Recalculate course progress from individual lesson progress.
     */
    private function recalculateCourseProgress(Lesson $lesson): void
    {
        $module = $lesson->module;
        $course = $module->course;
        $user = Auth::user();

        $enrollment = $course->enrollmentFor($user);
        if (! $enrollment) {
            return;
        }

        $totalLessons = $course->lessons()->published()->count();
        if ($totalLessons === 0) {
            return;
        }

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $course->lessons()->published()->pluck('lessons.id'))
            ->where('status', 'completed')
            ->count();

        $progressPct = (int) round(($completedLessons / $totalLessons) * 100);

        $enrollment->update([
            'progress_pct' => $progressPct,
            'status'       => $progressPct >= 100 ? 'completed' : 'active',
            'completed_at' => $progressPct >= 100 ? now() : null,
        ]);
    }
}

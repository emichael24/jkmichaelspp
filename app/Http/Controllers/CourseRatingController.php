<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseRating;
use Illuminate\Http\Request;

class CourseRatingController extends Controller
{
    /**
     * Store or update a course rating.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:2000',
        ]);

        $course = Course::findOrFail($validated['course_id']);

        if (! $course->isEnrolledBy($request->user())) {
            return back()->with('error', 'Enroll in the course before leaving a rating.');
        }

        CourseRating::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'course_id' => $validated['course_id'],
            ],
            [
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        );

        $this->syncAverage($course);

        return back()->with('success', 'Rating submitted successfully.');
    }

    /**
     * Delete a rating.
     */
    public function destroy(Course $course)
    {
        CourseRating::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->delete();

        $this->syncAverage($course);

        return back()->with('success', 'Rating removed.');
    }

    /**
     * Get ratings for a course (JSON).
     */
    public function getRatings(Course $course)
    {
        $average = CourseRating::averageForCourse($course->id);
        $distribution = CourseRating::distributionForCourse($course->id);
        $total = array_sum($distribution);

        $reviews = CourseRating::where('course_id', $course->id)
            ->whereNotNull('review')
            ->with('user:id,first_name,last_name,avatar')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'average' => $average,
            'distribution' => $distribution,
            'total' => $total,
            'reviews' => $reviews,
        ]);
    }

    private function syncAverage(Course $course): void
    {
        $course->update([
            'avg_rating' => CourseRating::averageForCourse($course->id) ?? 0,
        ]);
    }
}

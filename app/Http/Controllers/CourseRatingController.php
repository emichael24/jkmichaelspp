<?php

namespace App\Http\Controllers;

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

        return back()->with('success', 'Rating submitted successfully.');
    }

    /**
     * Delete a rating.
     */
    public function destroy(int $courseId)
    {
        CourseRating::where('user_id', auth()->id())
            ->where('course_id', $courseId)
            ->delete();

        return back()->with('success', 'Rating removed.');
    }

    /**
     * Get ratings for a course (JSON).
     */
    public function getRatings(int $courseId)
    {
        $average = CourseRating::averageForCourse($courseId);
        $distribution = CourseRating::distributionForCourse($courseId);
        $total = array_sum($distribution);

        $reviews = CourseRating::where('course_id', $courseId)
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
}

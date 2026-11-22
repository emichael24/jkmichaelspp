<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Lesson;
use App\Services\LearningAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    public function index()
    {
        $bookmarks = Auth::user()->bookmarks()
            ->with(['bookmarkable' => fn ($q) => $q->select(['id', 'title', 'slug'])])
            ->latest()
            ->paginate(20);

        return inertia('Bookmarks/Index', [
            'bookmarks' => $bookmarks,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bookmarkable_type' => 'required|string|in:App\\Models\\Course,App\\Models\\Lesson',
            'bookmarkable_id' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ]);

        $target = $validated['bookmarkable_type']::findOrFail($validated['bookmarkable_id']);
        if ($target instanceof Lesson) {
            app(LearningAccessService::class)->assertLesson($target, Auth::user(), true);
        } else {
            abort_unless($target->is_published, 404);
        }
        $validated['user_id'] = Auth::id();

        // Prevent duplicates
        $existing = Bookmark::where('user_id', Auth::id())
            ->where('bookmarkable_type', $validated['bookmarkable_type'])
            ->where('bookmarkable_id', $validated['bookmarkable_id'])
            ->first();

        if ($existing) {
            return back()->with('info', 'Already bookmarked.');
        }

        Bookmark::create($validated);

        return back()->with('success', 'Bookmark added.');
    }

    public function destroy(Bookmark $bookmark)
    {
        if ($bookmark->user_id !== Auth::id()) {
            abort(403);
        }

        $bookmark->delete();

        return back()->with('success', 'Bookmark removed.');
    }
}

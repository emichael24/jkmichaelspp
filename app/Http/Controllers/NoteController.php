<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\UserNote;
use App\Services\LearningAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    public function index(int $lessonId)
    {
        app(LearningAccessService::class)->assertLesson(Lesson::findOrFail($lessonId), Auth::user(), true);
        $notes = Auth::user()->notes()
            ->where('lesson_id', $lessonId)
            ->latest()
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, int $lessonId)
    {
        app(LearningAccessService::class)->assertLesson(Lesson::findOrFail($lessonId), Auth::user(), true);
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'highlight_text' => 'nullable|string|max:1000',
            'position' => 'nullable|array',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['lesson_id'] = $lessonId;

        $note = UserNote::create($validated);

        return back()->with('success', 'Note saved.');
    }

    public function update(Request $request, UserNote $note)
    {
        if ($note->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'highlight_text' => 'nullable|string|max:1000',
            'position' => 'nullable|array',
        ]);

        $note->update($validated);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(UserNote $note)
    {
        if ($note->user_id !== Auth::id()) {
            abort(403);
        }

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}

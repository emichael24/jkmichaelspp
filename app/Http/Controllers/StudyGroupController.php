<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudyGroupController extends Controller
{
    /**
     * List study groups.
     */
    public function index(Request $request): Response
    {
        $query = StudyGroup::with(['course:id,title', 'creator:id,first_name,last_name'])
            ->withCount('members');

        if ($filter = $request->input('filter')) {
            if ($filter === 'mine') {
                $query->whereHas('members', fn($q) => $q->where('user_id', auth()->id()));
            } elseif ($filter === 'available') {
                $query->where('is_public', true);
            }
        }

        if ($courseId = $request->input('course_id')) {
            $query->forCourse($courseId);
        }

        $groups = $query->orderByDesc('created_at')->paginate(12);

        return Inertia::render('StudyGroups/Index', [
            'groups' => $groups,
            'filter' => $filter,
        ]);
    }

    /**
     * Show a study group.
     */
    public function show(int $id): Response
    {
        $group = StudyGroup::with([
            'course:id,title',
            'creator:id,first_name,last_name,avatar',
            'members:id,first_name,last_name,avatar',
        ])->withCount('members')->findOrFail($id);

        $isMember = $group->hasMember(auth()->id());

        return Inertia::render('StudyGroups/Show', [
            'group' => $group,
            'isMember' => $isMember,
        ]);
    }

    /**
     * Create a study group.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
            'max_members' => 'integer|min:2|max:50',
            'is_public' => 'boolean',
        ]);

        $group = StudyGroup::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        // Auto-join creator
        $group->addMember(auth()->id());

        return redirect()->route('study-groups.show', $group->id)->with('success', 'Study group created!');
    }

    /**
     * Join a study group.
     */
    public function join(int $id)
    {
        $group = StudyGroup::findOrFail($id);

        if (!$group->addMember(auth()->id())) {
            return back()->with('error', 'Unable to join. Group may be full or you are already a member.');
        }

        return back()->with('success', 'You have joined the study group!');
    }

    /**
     * Leave a study group.
     */
    public function leave(int $id)
    {
        $group = StudyGroup::findOrFail($id);
        $group->removeMember(auth()->id());

        return redirect()->route('study-groups.index')->with('success', 'You have left the study group.');
    }

    /**
     * Delete a study group (creator only).
     */
    public function destroy(int $id)
    {
        $group = StudyGroup::where('created_by', auth()->id())->findOrFail($id);
        $group->delete();

        return redirect()->route('study-groups.index')->with('success', 'Study group deleted.');
    }
}

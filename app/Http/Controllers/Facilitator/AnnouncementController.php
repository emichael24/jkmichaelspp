<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    /* ── List ── */
    public function index(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $announcements = Announcement::forCohort($cohort->id)
            ->with('author:id,first_name,last_name,avatar')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(15);

        return Inertia::render('Facilitator/Announcements/Index', [
            'cohort' => $cohort->load('course:id,title'),
            'announcements' => $announcements,
        ]);
    }

    /* ── Store ── */
    public function store(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_pinned' => 'nullable|boolean',
        ]);

        Announcement::create([
            'author_id' => Auth::id(),
            'cohort_id' => $cohort->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $validated['is_pinned'] ?? false,
            'published_at' => now(),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    /* ── Update ── */
    public function update(Request $request, Cohort $cohort, Announcement $announcement)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_pinned' => 'nullable|boolean',
        ]);

        $announcement->update($validated);

        return back()->with('success', 'Announcement updated.');
    }

    /* ── Delete ── */
    public function destroy(Cohort $cohort, Announcement $announcement)
    {
        $this->authorizeFacilitator($cohort);
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    /* ── helpers ── */
    private function authorizeFacilitator(Cohort $cohort): void
    {
        abort_unless(
            $cohort->facilitator_id === Auth::id() || Auth::user()->hasRole('platform_admin'),
            403
        );
    }
}

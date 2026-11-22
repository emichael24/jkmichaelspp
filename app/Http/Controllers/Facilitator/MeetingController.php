<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MeetingController extends Controller
{
    /* ── List meetings for a cohort ─────────────────── */

    public function index(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $meetings = Meeting::where('meetable_type', Cohort::class)
            ->where('meetable_id', $cohort->id)
            ->with(['organizer', 'attendees'])
            ->orderByDesc('scheduled_at')
            ->paginate(15);

        $members = $cohort->learners()->select('users.id', 'users.first_name', 'users.last_name')->get();

        return Inertia::render('Facilitator/Meetings/Index', [
            'cohort' => $cohort,
            'meetings' => $meetings,
            'members' => $members,
        ]);
    }

    /* ── Store ─────────────────────────────────────── */

    public function store(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:live_session,coaching,standup,review,webinar,bootcamp',
            'platform' => 'required|in:zoom,teams,google_meet,other',
            'meeting_url' => 'nullable|url|max:500',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'integer|min:5|max:480',
            'agenda' => 'nullable|string',
            'attendee_ids' => 'nullable|array',
            'attendee_ids.*' => 'exists:users,id',
        ]);

        $meeting = Meeting::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'organizer_id' => Auth::id(),
            'meetable_type' => Cohort::class,
            'meetable_id' => $cohort->id,
            'type' => $validated['type'],
            'platform' => $validated['platform'],
            'meeting_url' => $validated['meeting_url'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'agenda' => $validated['agenda'] ?? null,
        ]);

        // Invite attendees
        $attendeeIds = $validated['attendee_ids'] ?? [];
        foreach ($attendeeIds as $userId) {
            MeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'user_id' => $userId,
            ]);
        }

        return back()->with('success', 'Meeting scheduled.');
    }

    /* ── Update ────────────────────────────────────── */

    public function update(Request $request, Cohort $cohort, Meeting $meeting)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($meeting->meetable_type === Cohort::class && (int) $meeting->meetable_id === $cohort->id, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:live_session,coaching,standup,review,webinar,bootcamp',
            'platform' => 'required|in:zoom,teams,google_meet,other',
            'meeting_url' => 'nullable|url|max:500',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'integer|min:5|max:480',
            'agenda' => 'nullable|string',
            'status' => 'in:scheduled,in_progress,completed,cancelled',
        ]);

        $meeting->update($validated);

        return back()->with('success', 'Meeting updated.');
    }

    /* ── Mark attendance ───────────────────────────── */

    public function markAttendance(Request $request, Cohort $cohort, Meeting $meeting)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($meeting->meetable_type === Cohort::class && (int) $meeting->meetable_id === $cohort->id, 404);

        $validated = $request->validate([
            'attendees' => 'required|array',
            'attendees.*.user_id' => 'required|exists:users,id',
            'attendees.*.attended' => 'required|boolean',
        ]);

        foreach ($validated['attendees'] as $record) {
            MeetingAttendee::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $record['user_id']],
                ['attended' => $record['attended'], 'joined_at' => $record['attended'] ? now() : null],
            );
        }

        // Auto-complete the meeting
        $meeting->update(['status' => 'completed']);

        return back()->with('success', 'Attendance saved.');
    }

    /* ── Save post-meeting notes + action items ────── */

    public function saveNotes(Request $request, Cohort $cohort, Meeting $meeting)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($meeting->meetable_type === Cohort::class && (int) $meeting->meetable_id === $cohort->id, 404);

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'action_items' => 'nullable|array',
            'action_items.*.task' => 'required_with:action_items|string',
            'action_items.*.assignee_id' => 'nullable|exists:users,id',
            'action_items.*.due_date' => 'nullable|date',
            'replay_url' => 'nullable|url|max:500',
        ]);

        $meeting->update([
            'notes' => $validated['notes'] ?? null,
            'action_items' => $validated['action_items'] ?? null,
            'replay_url' => $validated['replay_url'] ?? null,
        ]);

        return back()->with('success', 'Notes saved.');
    }

    /* ── Cancel meeting ────────────────────────────── */

    public function destroy(Cohort $cohort, Meeting $meeting)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($meeting->meetable_type === Cohort::class && (int) $meeting->meetable_id === $cohort->id, 404);
        $meeting->update(['status' => 'cancelled']);

        return back()->with('success', 'Meeting cancelled.');
    }

    /* ── Auth helper ───────────────────────────────── */

    private function authorizeFacilitator(Cohort $cohort): void
    {
        $user = Auth::user();
        if ($cohort->facilitator_id !== $user->id && ! $user->hasRole('platform_admin')) {
            abort(403, 'Unauthorized');
        }
    }
}

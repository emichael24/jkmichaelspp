<?php

namespace App\Http\Controllers\Sponsor;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\InternshipEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MeetingController extends Controller
{
    /**
     * Meeting Hub — list all meetings for this sponsor.
     */
    public function index()
    {
        $userId = Auth::id();

        // Upcoming meetings
        $upcoming = Meeting::forOrganizer($userId)
            ->upcoming()
            ->with('attendeeUsers:id,first_name,last_name')
            ->limit(10)
            ->get()
            ->map(fn ($m) => $this->formatMeeting($m));

        // Past meetings
        $past = Meeting::forOrganizer($userId)
            ->where(function ($q) {
                $q->where('scheduled_at', '<', now())
                  ->orWhere('status', 'completed');
            })
            ->with('attendeeUsers:id,first_name,last_name')
            ->latest('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn ($m) => $this->formatMeeting($m));

        // Interns the sponsor supervises (for scheduling)
        $interns = InternshipEnrollment::where('sponsor_id', $userId)
            ->where('status', 'active')
            ->with('user:id,first_name,last_name,email')
            ->get()
            ->map(fn ($e) => [
                'id'    => $e->user_id,
                'name'  => $e->user->first_name . ' ' . $e->user->last_name,
                'email' => $e->user->email,
            ]);

        return Inertia::render('Sponsor/Meetings', [
            'upcoming' => $upcoming,
            'past'     => $past,
            'interns'  => $interns,
        ]);
    }

    /**
     * Store a new meeting.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'scheduled_at'     => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:15|max:180',
            'platform'         => 'required|in:zoom,teams,google_meet,in_person,other',
            'meeting_url'      => 'nullable|url|max:500',
            'agenda'           => 'nullable|string|max:2000',
            'attendee_ids'     => 'required|array|min:1',
            'attendee_ids.*'   => 'integer|exists:users,id',
        ]);

        $meeting = Meeting::create([
            'title'            => $validated['title'],
            'description'      => $validated['description'] ?? null,
            'organizer_id'     => Auth::id(),
            'type'             => 'sponsor_intern',
            'scheduled_at'     => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'],
            'platform'         => $validated['platform'],
            'meeting_url'      => $validated['meeting_url'] ?? null,
            'agenda'           => $validated['agenda'] ?? null,
            'status'           => 'scheduled',
        ]);

        // Add attendees
        foreach ($validated['attendee_ids'] as $attendeeId) {
            MeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'user_id'    => $attendeeId,
                'rsvp'       => 'pending',
                'attended'   => false,
            ]);
        }

        return redirect()->route('sponsor.meetings.index')
            ->with('success', 'Meeting scheduled successfully.');
    }

    /**
     * Mark attendance for a meeting.
     */
    public function markAttendance(Request $request, Meeting $meeting)
    {
        $request->validate([
            'attendees'              => 'required|array',
            'attendees.*.user_id'    => 'required|integer',
            'attendees.*.attended'   => 'required|boolean',
        ]);

        foreach ($request->attendees as $att) {
            MeetingAttendee::where('meeting_id', $meeting->id)
                ->where('user_id', $att['user_id'])
                ->update(['attended' => $att['attended']]);
        }

        return back()->with('success', 'Attendance recorded.');
    }

    /**
     * Save post-meeting notes and action items.
     */
    public function saveNotes(Request $request, Meeting $meeting)
    {
        $validated = $request->validate([
            'notes'        => 'nullable|string|max:5000',
            'action_items' => 'nullable|array',
            'action_items.*' => 'string|max:500',
        ]);

        $meeting->update([
            'notes'        => $validated['notes'] ?? null,
            'action_items' => $validated['action_items'] ?? [],
            'status'       => 'completed',
        ]);

        return back()->with('success', 'Meeting notes saved.');
    }

    private function formatMeeting(Meeting $m): array
    {
        return [
            'id'          => $m->id,
            'title'       => $m->title,
            'description' => $m->description,
            'date'        => $m->scheduled_at->format('M d, Y'),
            'time'        => $m->scheduled_at->format('h:i A'),
            'dateRaw'     => $m->scheduled_at->toISOString(),
            'duration'    => $m->duration_minutes,
            'platform'    => $m->platform,
            'url'         => $m->meeting_url,
            'agenda'      => $m->agenda,
            'notes'       => $m->notes,
            'action_items' => $m->action_items ?? [],
            'status'      => $m->status,
            'attendees'   => $m->attendeeUsers->map(fn ($u) => [
                'id'       => $u->id,
                'name'     => $u->first_name . ' ' . $u->last_name,
                'attended' => $u->pivot->attended ?? false,
                'rsvp'     => $u->pivot->rsvp ?? 'pending',
            ]),
        ];
    }
}

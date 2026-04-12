<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * List events for admin.
     */
    public function index(Request $request): Response
    {
        $query = Event::withCount('registrations');

        if ($type = $request->input('type')) {
            $query->ofType($type);
        }

        $events = $query->orderByDesc('scheduled_at')->paginate(15);

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'type' => $type,
        ]);
    }

    /**
     * Create event form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Events/Create');
    }

    /**
     * Store new event.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:webinar,bootcamp,industry_session,workshop',
            'speaker_name' => 'nullable|string|max:255',
            'speaker_bio' => 'nullable|string',
            'platform' => 'required|in:zoom,teams,google_meet,other',
            'meeting_url' => 'nullable|url|max:500',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'max_registrations' => 'nullable|integer|min:1',
            'price' => 'numeric|min:0',
            'is_published' => 'boolean',
        ]);

        Event::create($validated);

        return redirect()->route('admin.events.index')->with('success', 'Event created successfully.');
    }

    /**
     * Edit event form.
     */
    public function edit(int $id): Response
    {
        $event = Event::withCount('registrations')->findOrFail($id);

        return Inertia::render('Admin/Events/Edit', [
            'event' => $event,
        ]);
    }

    /**
     * Update event.
     */
    public function update(Request $request, int $id)
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:webinar,bootcamp,industry_session,workshop',
            'speaker_name' => 'nullable|string|max:255',
            'speaker_bio' => 'nullable|string',
            'platform' => 'required|in:zoom,teams,google_meet,other',
            'meeting_url' => 'nullable|url|max:500',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'max_registrations' => 'nullable|integer|min:1',
            'price' => 'numeric|min:0',
            'replay_url' => 'nullable|url|max:500',
            'is_published' => 'boolean',
        ]);

        $event->update($validated);

        return redirect()->route('admin.events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Delete event.
     */
    public function destroy(int $id)
    {
        Event::findOrFail($id)->delete();
        return redirect()->route('admin.events.index')->with('success', 'Event deleted.');
    }

    /**
     * View registrations for an event.
     */
    public function registrations(int $id): Response
    {
        $event = Event::findOrFail($id);
        $registrations = EventRegistration::where('event_id', $id)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('registered_at')
            ->paginate(25);

        return Inertia::render('Admin/Events/Registrations', [
            'event' => $event,
            'registrations' => $registrations,
        ]);
    }

    /**
     * Mark attendance.
     */
    public function markAttendance(Request $request, int $eventId)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        EventRegistration::where('event_id', $eventId)
            ->whereIn('user_id', $validated['user_ids'])
            ->update(['attended' => true]);

        return back()->with('success', 'Attendance recorded.');
    }
}

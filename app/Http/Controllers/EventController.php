<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * List events (calendar view).
     */
    public function index(Request $request): Response
    {
        $query = Event::published()->withCount('registrations');

        if ($type = $request->input('type')) {
            $query->ofType($type);
        }

        $tab = $request->input('tab', 'upcoming');

        if ($tab === 'past') {
            $query->past();
        } else {
            $query->upcoming();
        }

        $events = $query->paginate(12);

        // Get user's registered event IDs
        $registeredIds = EventRegistration::where('user_id', auth()->id())
            ->pluck('event_id')
            ->toArray();

        return Inertia::render('Events/Index', [
            'events' => $events,
            'registeredIds' => $registeredIds,
            'tab' => $tab,
            'type' => $type,
        ]);
    }

    /**
     * Show event detail.
     */
    public function show(int $id): Response
    {
        $event = Event::withCount('registrations')->findOrFail($id);
        $isRegistered = $event->isRegistered(auth()->id());
        $spotsLeft = $event->getSpotsLeft();

        return Inertia::render('Events/Show', [
            'event' => $event,
            'isRegistered' => $isRegistered,
            'spotsLeft' => $spotsLeft,
        ]);
    }

    /**
     * Register for an event.
     */
    public function register(int $id)
    {
        $event = Event::findOrFail($id);

        if ($event->isFull()) {
            return back()->with('error', 'This event is full.');
        }

        if ($event->isRegistered(auth()->id())) {
            return back()->with('error', 'You are already registered.');
        }

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'registered_at' => now(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Successfully registered for the event!');
    }

    /**
     * Cancel registration.
     */
    public function cancelRegistration(int $id)
    {
        EventRegistration::where('event_id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        return back()->with('success', 'Registration cancelled.');
    }
}

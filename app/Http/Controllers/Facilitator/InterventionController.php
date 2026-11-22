<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InterventionController extends Controller
{
    public const NUDGE_TEMPLATES = [
        'miss_you' => 'We miss you! Come back and continue your streak.',
        'mock_prep' => 'Your mock exam is tomorrow. Review your weak areas tonight.',
        'great_week' => 'Great progress this week! Keep it up.',
        'behind' => "You're falling behind schedule. Let's get back on track — reach out if you need help.",
        'streak_risk' => 'Your 7-day streak is at risk! Log in today to keep it alive.',
    ];

    /* ── Intervention Log ── */
    public function index(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $interventions = Intervention::forCohort($cohort->id)
            ->with([
                'facilitator:id,first_name,last_name',
                'learner:id,first_name,last_name,email,avatar',
            ])
            ->latest()
            ->paginate(20);

        return Inertia::render('Facilitator/Interventions/Index', [
            'cohort' => $cohort->load('course:id,title'),
            'interventions' => $interventions,
        ]);
    }

    /* ── Log new intervention ── */
    public function store(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'learner_id' => 'required|exists:users,id',
            'type' => 'required|in:nudge,coaching,escalation,plan_adjustment,rest_day',
            'reason' => 'required|string|max:1000',
            'action_taken' => 'required|string|max:1000',
        ]);

        Intervention::create([
            'facilitator_id' => Auth::id(),
            'learner_id' => $validated['learner_id'],
            'cohort_id' => $cohort->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'action_taken' => $validated['action_taken'],
            'status' => 'sent',
        ]);

        return back()->with('success', 'Intervention logged.');
    }

    /* ── Update status ── */
    public function updateStatus(Request $request, Cohort $cohort, Intervention $intervention)
    {
        $this->authorizeFacilitator($cohort);

        $request->validate([
            'status' => 'required|in:pending,sent,acknowledged,resolved',
        ]);

        $intervention->update(['status' => $request->status]);

        return back()->with('success', 'Status updated.');
    }

    /* ── Send nudge (quick shortcut) ── */
    public function sendNudge(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $request->validate([
            'learner_id' => 'required|exists:users,id',
            'template' => 'nullable|string',
            'message' => 'nullable|string|max:1000',
        ]);

        $message = $request->message
            ?? self::NUDGE_TEMPLATES[$request->template]
            ?? self::NUDGE_TEMPLATES['miss_you'];

        Intervention::create([
            'facilitator_id' => Auth::id(),
            'learner_id' => $request->learner_id,
            'cohort_id' => $cohort->id,
            'type' => 'nudge',
            'reason' => 'Facilitator-initiated nudge',
            'action_taken' => $message,
            'status' => 'sent',
        ]);

        // TODO: Dispatch push notification + email to learner

        return back()->with('success', 'Nudge sent.');
    }

    /* ── Nudge templates (JSON for frontend) ── */
    public function templates()
    {
        return response()->json(self::NUDGE_TEMPLATES);
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

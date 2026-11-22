<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Course;
use App\Models\User;
use App\Services\CohortHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CohortController extends Controller
{
    public function __construct(private CohortHealthService $healthService) {}

    /* ── List all cohorts for current facilitator ── */
    public function index(Request $request)
    {
        $cohorts = Cohort::forFacilitator(Auth::id())
            ->with('course:id,title,slug,thumbnail')
            ->withCount(['members' => fn ($q) => $q->active()->learners()])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Facilitator/Cohorts/Index', [
            'cohorts' => $cohorts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /* ── Create form ── */
    public function create()
    {
        $courses = Course::published()
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        return Inertia::render('Facilitator/Cohorts/Create', [
            'courses' => $courses,
        ]);
    }

    /* ── Store ── */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'max_learners' => 'nullable|integer|min:1|max:500',
            'status' => 'nullable|in:draft,active',
        ]);

        $validated['facilitator_id'] = Auth::id();
        $validated['organization_id'] = Auth::user()->organization_id;

        $cohort = Cohort::create($validated);

        return redirect()->route('facilitator.cohorts.show', $cohort)
            ->with('success', 'Cohort created successfully.');
    }

    /* ── Show / Health Dashboard ── */
    public function show(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $cohort->load('course:id,title,slug,thumbnail');

        $health = $this->healthService->healthScore($cohort);
        $atRisk = $this->healthService->atRiskLearners($cohort);
        $masteryHeatmap = $this->healthService->masteryHeatmap($cohort);
        $engagementMap = $this->healthService->engagementHeatmap($cohort);
        $mockDist = $this->healthService->mockScoreDistribution($cohort);

        $members = $cohort->members()
            ->active()
            ->with('user:id,first_name,last_name,email,avatar,last_login_at')
            ->get();

        return Inertia::render('Facilitator/Cohorts/Show', [
            'cohort' => $cohort,
            'health' => $health,
            'atRisk' => $atRisk,
            'masteryHeatmap' => $masteryHeatmap,
            'engagementHeatmap' => $engagementMap,
            'mockDistribution' => $mockDist,
            'members' => $members,
        ]);
    }

    /* ── Edit ── */
    public function edit(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $courses = Course::published()
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        return Inertia::render('Facilitator/Cohorts/Edit', [
            'cohort' => $cohort,
            'courses' => $courses,
        ]);
    }

    /* ── Update ── */
    public function update(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'max_learners' => 'nullable|integer|min:1|max:500',
            'status' => 'nullable|in:draft,active,completed,archived',
        ]);

        $cohort->update($validated);

        return redirect()->route('facilitator.cohorts.show', $cohort)
            ->with('success', 'Cohort updated.');
    }

    /* ── Add member ── */
    public function addMember(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'nullable|in:learner,co_facilitator',
        ]);

        $user = User::where('email', $request->email)->where('organization_id', $cohort->organization_id)->where('is_active', true)->firstOrFail();

        if ($cohort->members()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'User is already in this cohort.']);
        }

        if ($request->input('role', 'learner') === 'learner' && $cohort->isFull()) {
            return back()->withErrors(['email' => 'Cohort is full.']);
        }

        CohortMember::create([
            'cohort_id' => $cohort->id,
            'user_id' => $user->id,
            'role' => $request->input('role', 'learner'),
            'joined_at' => now(),
        ]);

        return back()->with('success', "{$user->first_name} added to cohort.");
    }

    /* ── Remove member ── */
    public function removeMember(Cohort $cohort, CohortMember $member)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($member->cohort_id === $cohort->id, 404);

        $member->update(['status' => 'removed']);

        return back()->with('success', 'Member removed.');
    }

    /* ── Learner 360 ── */
    public function learner360(Cohort $cohort, User $user)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($cohort->members()->where('user_id', $user->id)->where('status', 'active')->exists(), 404);

        $profile = $this->healthService->learner360($user->id, $cohort);

        return Inertia::render('Facilitator/Cohorts/Learner360', [
            'cohort' => $cohort->load('course:id,title'),
            'profile' => $profile,
        ]);
    }

    /* ── helpers ── */
    private function authorizeFacilitator(Cohort $cohort): void
    {
        abort_unless(
            $cohort->facilitator_id === Auth::id() || Auth::user()->hasRole('platform_admin'),
            403,
            'You do not have access to this cohort.'
        );
    }
}

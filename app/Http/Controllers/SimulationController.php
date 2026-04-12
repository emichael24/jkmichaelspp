<?php

namespace App\Http\Controllers;

use App\Models\Simulation;
use App\Models\SimulationAttempt;
use App\Services\SimulationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SimulationController extends Controller
{
    public function __construct(
        private SimulationService $simulationService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Simulation Listing
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $simulations = Simulation::with('course:id,title')
            ->published()
            ->when($request->course_id, fn ($q, $c) => $q->byCourse($c))
            ->when($request->type, fn ($q, $t) => $q->byType($t))
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->withCount('attempts')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Attach user attempt info
        $user = $request->user();
        $simulations->getCollection()->transform(function ($sim) use ($user) {
            $userAttempts = $sim->attemptsBy($user);
            $sim->user_attempts_count = $userAttempts->count();
            $sim->best_score = $userAttempts->completed()->max('total_score_pct');
            $sim->has_in_progress = $userAttempts->inProgress()->exists();
            return $sim;
        });

        return Inertia::render('Simulations/Index', [
            'simulations' => $simulations,
            'filters'     => $request->only(['course_id', 'type', 'search']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Simulation Detail (Start page)
    |--------------------------------------------------------------------------
    */

    public function show(Simulation $simulation)
    {
        $simulation->load('course:id,title,slug');
        $user = auth()->user();

        $previousAttempts = $simulation->attemptsBy($user)
            ->completed()
            ->latest()
            ->take(5)
            ->get(['id', 'total_score_pct', 'time_spent_seconds', 'completed_at']);

        $inProgressAttempt = $simulation->attemptsBy($user)
            ->inProgress()
            ->first();

        return Inertia::render('Simulations/Show', [
            'simulation'         => $simulation,
            'previousAttempts'   => $previousAttempts,
            'inProgressAttempt'  => $inProgressAttempt,
            'decisionPointCount' => $simulation->getDecisionNodeCount(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Start Simulation
    |--------------------------------------------------------------------------
    */

    public function start(Simulation $simulation)
    {
        $result = $this->simulationService->start($simulation, auth()->user());

        return Inertia::render('Simulations/Player', [
            'simulation' => $simulation,
            'attempt'    => $result['attempt'],
            'node'       => $result['node'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resume Simulation
    |--------------------------------------------------------------------------
    */

    public function resume(SimulationAttempt $attempt)
    {
        $this->authorize('update', $attempt);

        $result = $this->simulationService->resume($attempt);

        return Inertia::render('Simulations/Player', [
            'simulation' => $attempt->simulation,
            'attempt'    => $result['attempt'],
            'node'       => $result['node'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Make a Choice (AJAX)
    |--------------------------------------------------------------------------
    */

    public function choose(Request $request, SimulationAttempt $attempt)
    {
        $request->validate([
            'node_id'   => 'required|integer',
            'choice_id' => 'required|integer',
        ]);

        $result = $this->simulationService->makeChoice(
            $attempt,
            $request->node_id,
            $request->choice_id
        );

        return response()->json($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Simulation (AJAX)
    |--------------------------------------------------------------------------
    */

    public function complete(Request $request, SimulationAttempt $attempt)
    {
        $request->validate([
            'time_spent_seconds' => 'required|integer|min:0',
        ]);

        $result = $this->simulationService->complete($attempt, $request->time_spent_seconds);

        // Award XP via gamification service
        try {
            app(\App\Services\GamificationService::class)->awardXp(
                $attempt->user_id,
                'simulation_complete',
                ['simulation_id' => $attempt->simulation_id]
            );
        } catch (\Throwable) {
            // Non-critical, don't fail the completion
        }

        return response()->json($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Review Completed Attempt
    |--------------------------------------------------------------------------
    */

    public function report(SimulationAttempt $attempt)
    {
        $review = $this->simulationService->getReview($attempt);

        return Inertia::render('Simulations/Report', [
            'attempt'         => $review['attempt'],
            'simulation'      => $review['simulation'],
            'scores'          => $review['scores'],
            'totalScorePct'   => $review['total_score_pct'],
            'bestPracticePath' => $review['best_practice_path'],
            'yourPath'        => $review['your_path'],
            'pathComparison'  => $review['path_comparison'],
            'nodesVisited'    => $review['nodes_visited'],
        ]);
    }
}

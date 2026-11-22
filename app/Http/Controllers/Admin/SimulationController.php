<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Simulation;
use App\Models\SimulationChoice;
use App\Models\SimulationNode;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SimulationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Simulation CRUD (Admin)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $simulations = Simulation::with('course:id,title')
            ->withCount(['nodes', 'attempts'])
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->course_id, fn ($q, $c) => $q->byCourse($c))
            ->when($request->type, fn ($q, $t) => $q->byType($t))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $courses = Course::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/Simulations/Index', [
            'simulations' => $simulations,
            'courses' => $courses,
            'filters' => $request->only(['search', 'course_id', 'type']),
        ]);
    }

    public function create()
    {
        $courses = Course::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/Simulations/Create', [
            'courses' => $courses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:branching_scenario,role_play,case_study_lab,decision_room',
            'difficulty' => 'required|in:easy,medium,hard',
            'estimated_minutes' => 'required|integer|min:1',
            'context_narrative' => 'required|string',
            'is_published' => 'boolean',
        ]);

        $simulation = Simulation::create($validated);

        return redirect()->route('admin.simulations.show', $simulation)
            ->with('success', 'Simulation created. Now add nodes and choices.');
    }

    public function show(Simulation $simulation)
    {
        $simulation->load(['course:id,title', 'nodes.choices.nextNode']);

        return Inertia::render('Admin/Simulations/Show', [
            'simulation' => $simulation,
        ]);
    }

    public function edit(Simulation $simulation)
    {
        $simulation->load(['course:id,title', 'nodes.choices']);
        $courses = Course::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/Simulations/Edit', [
            'simulation' => $simulation,
            'courses' => $courses,
        ]);
    }

    public function update(Request $request, Simulation $simulation)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:branching_scenario,role_play,case_study_lab,decision_room',
            'difficulty' => 'required|in:easy,medium,hard',
            'estimated_minutes' => 'required|integer|min:1',
            'context_narrative' => 'required|string',
            'is_published' => 'boolean',
        ]);

        $simulation->update($validated);

        return redirect()->route('admin.simulations.show', $simulation)
            ->with('success', 'Simulation updated.');
    }

    public function destroy(Simulation $simulation)
    {
        $simulation->delete();

        return redirect()->route('admin.simulations.index')
            ->with('success', 'Simulation deleted.');
    }

    public function publish(Simulation $simulation)
    {
        $simulation->update(['is_published' => true]);

        return back()->with('success', 'Simulation published.');
    }

    public function unpublish(Simulation $simulation)
    {
        $simulation->update(['is_published' => false]);

        return back()->with('success', 'Simulation unpublished.');
    }

    /*
    |--------------------------------------------------------------------------
    | Node Management
    |--------------------------------------------------------------------------
    */

    public function storeNode(Request $request, Simulation $simulation)
    {
        $validated = $request->validate([
            'type' => 'required|in:story,decision,consequence,feedback,end',
            'content' => 'required|string',
            'media_url' => 'nullable|string|max:500',
            'is_start' => 'boolean',
            'is_best_practice' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // If marking as start, unset previous start node
        if ($request->boolean('is_start')) {
            $simulation->nodes()->where('is_start', true)->update(['is_start' => false]);
        }

        $node = $simulation->nodes()->create($validated);

        return back()->with('success', 'Node added.');
    }

    public function updateNode(Request $request, SimulationNode $node)
    {
        $validated = $request->validate([
            'type' => 'required|in:story,decision,consequence,feedback,end',
            'content' => 'required|string',
            'media_url' => 'nullable|string|max:500',
            'is_start' => 'boolean',
            'is_best_practice' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($request->boolean('is_start')) {
            $node->simulation->nodes()->where('id', '!=', $node->id)
                ->where('is_start', true)
                ->update(['is_start' => false]);
        }

        $node->update($validated);

        return back()->with('success', 'Node updated.');
    }

    public function destroyNode(SimulationNode $node)
    {
        $node->delete();

        return back()->with('success', 'Node deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Choice Management
    |--------------------------------------------------------------------------
    */

    public function storeChoice(Request $request, SimulationNode $node)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'next_node_id' => 'nullable|exists:simulation_nodes,id',
            'score_impact' => 'nullable|array',
            'sort_order' => 'integer',
        ]);

        $node->choices()->create($validated);

        return back()->with('success', 'Choice added.');
    }

    public function updateChoice(Request $request, SimulationChoice $choice)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'next_node_id' => 'nullable|exists:simulation_nodes,id',
            'score_impact' => 'nullable|array',
            'sort_order' => 'integer',
        ]);

        $choice->update($validated);

        return back()->with('success', 'Choice updated.');
    }

    public function destroyChoice(SimulationChoice $choice)
    {
        $choice->delete();

        return back()->with('success', 'Choice deleted.');
    }
}

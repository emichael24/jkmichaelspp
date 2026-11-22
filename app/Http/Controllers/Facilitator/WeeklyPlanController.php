<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortPlanItem;
use App\Models\CohortWeeklyPlan;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Services\CohortHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WeeklyPlanController extends Controller
{
    public function __construct(private CohortHealthService $healthService) {}

    /* ── Plan Builder page ── */
    public function index(Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $cohort->load('course:id,title');

        $plans = $cohort->weeklyPlans()
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        // Available items the facilitator can drag onto the plan
        $lessons = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $cohort->course_id))
            ->orderBy('title')
            ->get(['id', 'title', 'module_id']);

        // Quizzes for this course
        $quizzes = Quiz::where('quizzable_type', 'App\\Models\\Module')
            ->whereIn('quizzable_id', Module::where('course_id', $cohort->course_id)->pluck('id'))
            ->get(['id', 'title']);

        $suggestions = $this->healthService->suggestPlanItems($cohort);

        return Inertia::render('Facilitator/Plans/Builder', [
            'cohort' => $cohort,
            'plans' => $plans,
            'lessons' => $lessons,
            'quizzes' => $quizzes,
            'suggestions' => $suggestions,
        ]);
    }

    /* ── Create a new week ── */
    public function storeWeek(Request $request, Cohort $cohort)
    {
        $this->authorizeFacilitator($cohort);

        $validated = $request->validate([
            'week_number' => 'required|integer|min:1',
            'title' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $validated['cohort_id'] = $cohort->id;

        CohortWeeklyPlan::create($validated);

        return back()->with('success', 'Week added.');
    }

    /* ── Update week ── */
    public function updateWeek(Request $request, Cohort $cohort, CohortWeeklyPlan $plan)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($plan->cohort_id === $cohort->id, 404);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $plan->update($validated);

        return back()->with('success', 'Week updated.');
    }

    /* ── Delete week (+ items) ── */
    public function destroyWeek(Cohort $cohort, CohortWeeklyPlan $plan)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($plan->cohort_id === $cohort->id, 404);
        $plan->delete();

        return back()->with('success', 'Week removed.');
    }

    /* ── Add item to plan ── */
    public function addItem(Request $request, Cohort $cohort, CohortWeeklyPlan $plan)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($plan->cohort_id === $cohort->id, 404);

        $validated = $request->validate([
            'itemable_type' => 'required|string|in:App\\Models\\Lesson,App\\Models\\Quiz,App\\Models\\MockExam',
            'itemable_id' => 'required|integer',
            'is_mandatory' => 'nullable|boolean',
            'due_date' => 'nullable|date',
        ]);

        $validated['weekly_plan_id'] = $plan->id;
        $validated['sort_order'] = $plan->items()->max('sort_order') + 1;

        CohortPlanItem::create($validated);

        return back()->with('success', 'Item added to plan.');
    }

    /* ── Remove item ── */
    public function removeItem(Cohort $cohort, CohortPlanItem $item)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($item->weeklyPlan->cohort_id === $cohort->id, 404);
        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    /* ── Reorder items (drag-and-drop) ── */
    public function reorder(Request $request, Cohort $cohort, CohortWeeklyPlan $plan)
    {
        $this->authorizeFacilitator($cohort);
        abort_unless($plan->cohort_id === $cohort->id, 404);

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:cohort_plan_items,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            CohortPlanItem::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Items reordered.');
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

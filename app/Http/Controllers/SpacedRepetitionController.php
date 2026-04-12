<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\SpacedRepetitionItem;
use App\Services\SpacedRepetitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SpacedRepetitionController extends Controller
{
    public function __construct(
        private SpacedRepetitionService $srService,
    ) {}

    /**
     * Practice dashboard: due count, stats, quick-start.
     */
    public function dashboard()
    {
        $userId = Auth::id();
        $stats = $this->srService->getSessionStats($userId);

        $flashcardCount = Flashcard::where('user_id', $userId)->count();

        return Inertia::render('Practice/Dashboard', [
            'stats'          => $stats,
            'flashcardCount' => $flashcardCount,
        ]);
    }

    /**
     * Get due items for a review session.
     */
    public function review()
    {
        $userId = Auth::id();
        $items = $this->srService->getDueItems($userId, 20);
        $stats = $this->srService->getSessionStats($userId);

        // Transform items for the frontend
        $cards = $items->map(function (SpacedRepetitionItem $item) {
            $reviewable = $item->reviewable;

            if ($reviewable instanceof Flashcard) {
                return [
                    'item_id'  => $item->id,
                    'type'     => 'flashcard',
                    'front'    => $reviewable->front,
                    'back'     => $reviewable->back,
                    'domain'   => $reviewable->domain,
                    'source'   => $reviewable->source,
                    'ease'     => $item->ease_factor,
                    'interval' => $item->interval_days,
                    'reps'     => $item->repetitions,
                    'status'   => $item->status,
                ];
            }

            return null;
        })->filter()->values();

        return Inertia::render('Practice/Review', [
            'cards' => $cards,
            'stats' => $stats,
        ]);
    }

    /**
     * Submit a single review quality rating.
     */
    public function submitReview(Request $request, SpacedRepetitionItem $item)
    {
        if ($item->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'quality' => 'required|integer|min:0|max:5',
        ]);

        $updatedItem = $this->srService->submitReview($item, $validated['quality']);

        return back()->with('reviewResult', [
            'ease'         => $updatedItem->ease_factor,
            'interval'     => $updatedItem->interval_days,
            'next_review'  => $updatedItem->next_review_at,
            'status'       => $updatedItem->status,
        ]);
    }

    /**
     * API: Get due count (for nav badge).
     */
    public function dueCount()
    {
        $count = SpacedRepetitionItem::dueCount(Auth::id());

        return response()->json(['count' => $count]);
    }

    // ──────────────────────── Flashcard CRUD ────────────────────────

    /**
     * List user's flashcards.
     */
    public function flashcards(Request $request)
    {
        $flashcards = Flashcard::where('user_id', Auth::id())
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->when($request->domain, fn ($q, $d) => $q->where('domain', $d))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('front', 'like', "%{$s}%")
                   ->orWhere('back', 'like', "%{$s}%");
            }))
            ->with('course:id,title')
            ->latest()
            ->paginate(20);

        return Inertia::render('Practice/Flashcards', [
            'flashcards' => $flashcards,
            'filters'    => $request->only(['course_id', 'domain', 'source', 'search']),
        ]);
    }

    /**
     * Store a user-created flashcard.
     */
    public function storeFlashcard(Request $request)
    {
        $validated = $request->validate([
            'front'     => 'required|string|max:2000',
            'back'      => 'required|string|max:5000',
            'course_id' => 'nullable|exists:courses,id',
            'domain'    => 'nullable|string|max:255',
            'tags'      => 'nullable|array',
        ]);

        $flashcard = Flashcard::create([
            ...$validated,
            'user_id' => Auth::id(),
            'source'  => 'user_created',
        ]);

        // Auto-create SR item
        SpacedRepetitionItem::ensureExists(Auth::id(), Flashcard::class, $flashcard->id);

        return redirect()->route('practice.flashcards')
            ->with('success', 'Flashcard created!');
    }

    /**
     * Update a user-created flashcard.
     */
    public function updateFlashcard(Request $request, Flashcard $flashcard)
    {
        if ($flashcard->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'front'  => 'required|string|max:2000',
            'back'   => 'required|string|max:5000',
            'domain' => 'nullable|string|max:255',
            'tags'   => 'nullable|array',
        ]);

        $flashcard->update($validated);

        return back()->with('success', 'Flashcard updated.');
    }

    /**
     * Delete a flashcard and its SR item.
     */
    public function destroyFlashcard(Flashcard $flashcard)
    {
        if ($flashcard->user_id !== Auth::id()) {
            abort(403);
        }

        // Remove associated SR items
        $flashcard->spacedRepetitionItems()->delete();
        $flashcard->delete();

        return back()->with('success', 'Flashcard deleted.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\InternshipEnrollment;
use App\Models\PortfolioItem;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortfolioController extends Controller
{
    /**
     * My portfolio items.
     */
    public function index(Request $request)
    {
        $items = PortfolioItem::forUser($request->user()->id)
            ->ordered()
            ->with('enrollment.track')
            ->get();

        return Inertia::render('Portfolio/Index', [
            'items' => $items,
        ]);
    }

    /**
     * Add a portfolio item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:internship_enrollments,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|in:deliverable,presentation,report,case_study,reflection',
            'file_url' => 'required|url:http,https|max:500',
            'skills_demonstrated' => 'nullable|array',
            'skills_demonstrated.*' => 'string|max:100',
            'is_public' => 'boolean',
        ]);

        $enrollment = InternshipEnrollment::findOrFail($validated['enrollment_id']);

        if ($enrollment->user_id !== $request->user()->id) {
            abort(403);
        }

        $maxSort = PortfolioItem::forUser($request->user()->id)->max('sort_order') ?? 0;

        PortfolioItem::create([
            'user_id' => $request->user()->id,
            'enrollment_id' => $validated['enrollment_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'file_url' => $validated['file_url'],
            'skills_demonstrated' => $validated['skills_demonstrated'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('success', 'Portfolio item added.');
    }

    /**
     * Update a portfolio item.
     */
    public function update(Request $request, PortfolioItem $portfolioItem)
    {
        if ($portfolioItem->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|in:deliverable,presentation,report,case_study,reflection',
            'skills_demonstrated' => 'nullable|array',
            'is_public' => 'boolean',
        ]);

        $portfolioItem->update($validated);

        return back()->with('success', 'Portfolio item updated.');
    }

    /**
     * Delete a portfolio item.
     */
    public function destroy(Request $request, PortfolioItem $portfolioItem)
    {
        if ($portfolioItem->user_id !== $request->user()->id) {
            abort(403);
        }

        $portfolioItem->delete();

        return back()->with('success', 'Portfolio item removed.');
    }

    /**
     * Portfolio export (placeholder for PDF generation).
     */
    public function export(Request $request)
    {
        $items = PortfolioItem::forUser($request->user()->id)
            ->ordered()
            ->with('enrollment.track')
            ->get();

        // In production, this would generate a PDF
        return Inertia::render('Portfolio/Export', [
            'items' => $items,
            'user' => $request->user(),
        ]);
    }

    /**
     * Public portfolio view.
     */
    public function publicView(string $userUuid)
    {
        $user = User::where('uuid', $userUuid)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhereHas('organization', fn ($org) => $org->where('is_active', true)))
            ->firstOrFail();

        $items = PortfolioItem::forUser($user->id)
            ->public()
            ->ordered()
            ->select(['id', 'title', 'description', 'type', 'file_url', 'skills_demonstrated', 'sort_order'])
            ->get();

        return Inertia::render('Portfolio/Public', [
            'portfolioUser' => $user->only('first_name', 'last_name', 'avatar'),
            'items' => $items,
        ]);
    }
}

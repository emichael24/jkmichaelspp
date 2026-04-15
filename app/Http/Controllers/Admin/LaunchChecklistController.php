<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaunchChecklistItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaunchChecklistController extends Controller
{
    public function index(): Response
    {
        $items = LaunchChecklistItem::orderBy('gate')
            ->orderBy('id')
            ->get()
            ->groupBy('gate');

        return Inertia::render('Admin/LaunchChecklist/Index', [
            'items' => $items,
        ]);
    }

    public function update(Request $request, LaunchChecklistItem $item)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,complete,blocked',
            'owner_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        $item->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Checklist updated.');
    }
}

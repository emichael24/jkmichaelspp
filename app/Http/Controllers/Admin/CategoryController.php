<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('courses')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon'        => 'nullable|string|max:50',
            'parent_id'   => 'nullable|exists:categories,id',
            'is_active'   => 'boolean',
        ]);

        Category::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'icon'        => 'nullable|string|max:50',
            'parent_id'   => 'nullable|exists:categories,id',
            'is_active'   => 'boolean',
        ]);

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        if ($category->courses()->exists()) {
            return back()->with('error', 'Cannot delete a category that has courses.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:categories,id',
        ]);

        foreach ($validated['order'] as $position => $id) {
            Category::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Categories reordered.');
    }
}

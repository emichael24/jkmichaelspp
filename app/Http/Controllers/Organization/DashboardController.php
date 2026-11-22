<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $organization = $request->user()->organization;
        abort_unless($organization && $organization->is_active, 403, 'An active organization is required. Contact the platform administrator.');
        $filters = $request->validate(['search' => 'nullable|string|max:100']);
        $users = User::where('organization_id', $organization->id);
        $enrollments = CourseEnrollment::whereHas('user', fn ($query) => $query->where('organization_id', $organization->id));

        return Inertia::render('Organization/Dashboard', [
            'organization' => $organization->only(['name', 'max_seats']),
            'stats' => [
                'people' => (clone $users)->count(),
                'activePeople' => (clone $users)->where('is_active', true)->count(),
                'enrollments' => (clone $enrollments)->count(),
                'completed' => (clone $enrollments)->where('status', 'completed')->count(),
            ],
            'people' => $users->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                ->withCount('enrollments')->withCount(['enrollments as completed_count' => fn ($query) => $query->where('status', 'completed')])
                ->orderBy('first_name')->paginate(20, ['id', 'first_name', 'last_name', 'email', 'is_active'])->withQueryString(),
            'filters' => $filters,
        ]);
    }
}

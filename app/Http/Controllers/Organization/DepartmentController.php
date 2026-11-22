<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->organization;
        abort_unless($org?->is_active, 403);

        return Inertia::render('Organization/Departments', ['organization' => $org->only('name'),
            'departments' => $org->departments()->with(['manager' => fn ($q) => $q->where('organization_id', $org->id)->where('is_active', true)->select('id', 'first_name', 'last_name')])
                ->withCount(['users' => fn ($q) => $q->where('organization_id', $org->id)])->orderBy('name')->get(),
            'managers' => $org->users()->where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name'])]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, Department $department)
    {
        return $this->save($request, $department);
    }

    private function save(Request $request, ?Department $department = null)
    {
        $org = $request->user()->organization;
        abort_unless($org?->is_active, 403);
        abort_if($department && $department->organization_id !== $org->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'],
            'manager_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org->id)->where('is_active', true)->whereNull('deleted_at')]]);
        DB::transaction(function () use ($org, $department, $data) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $name = trim($data['name']);
            if ($name === '' || Department::where('organization_id', $org->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($department, fn ($q) => $q->where('id', '<>', $department->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Use a unique, non-empty department name.']);
            }
            if ($data['manager_id'] ?? null) {
                abort_unless(User::whereKey($data['manager_id'])->where('organization_id', $org->id)->where('is_active', true)->exists(), 409);
            }
            if ($department) {
                $department->update(['name' => $name, 'manager_id' => $data['manager_id'] ?? null]);
            } else {
                $org->departments()->create(['name' => $name, 'manager_id' => $data['manager_id'] ?? null]);
            }
        }, 3);

        return back()->with('success', 'Department saved. Manager assignment does not grant administrator access.');
    }

    public function destroy(Request $request, Department $department)
    {
        $org = $request->user()->organization;
        abort_unless($org?->is_active && $department->organization_id === $org->id, 404);
        DB::transaction(function () use ($org, $department) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            if ($department->users()->exists() || $department->children()->exists() || OrganizationInvitation::where('department_id', $department->id)->pending()->exists()) {
                throw ValidationException::withMessages(['department' => 'Move members and child departments, and revoke pending invitations before deleting this department.']);
            }
            $department->delete();
        }, 3);

        return back()->with('success', 'Department deleted.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['organization', 'roles', 'departments'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($request->role, fn ($q, $r) => $q->role($r))
            ->when($request->organization_id, fn ($q, $o) => $q->where('organization_id', $o))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest();

        return Inertia::render('Admin/Users/Index', [
            'users'         => $query->paginate(20)->withQueryString(),
            'roles'         => Role::all()->pluck('name'),
            'organizations' => Organization::active()->select('id', 'name')->get(),
            'filters'       => $request->only(['search', 'role', 'organization_id', 'is_active']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'roles'         => Role::all()->pluck('name'),
            'organizations' => Organization::active()->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:255', 'unique:users'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'password'        => ['required', 'string', 'min:8'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'role'            => ['required', 'string', 'exists:roles,name'],
            'is_active'       => ['boolean'],
            'department_ids'  => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ]);

        $user = User::create([
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'] ?? null,
            'password'        => Hash::make($validated['password']),
            'organization_id' => $validated['organization_id'] ?? null,
            'is_active'       => $validated['is_active'] ?? true,
        ]);

        $user->assignRole($validated['role']);

        if (! empty($validated['department_ids'])) {
            $user->departments()->sync($validated['department_ids']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user'          => $user->load(['organization', 'roles', 'departments']),
            'roles'         => Role::all()->pluck('name'),
            'organizations' => Organization::active()->select('id', 'name')->get(),
            'departments'   => $user->organization_id
                ? Department::where('organization_id', $user->organization_id)->get()
                : collect(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'           => ['nullable', 'string', 'max:20'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'role'            => ['required', 'string', 'exists:roles,name'],
            'is_active'       => ['boolean'],
            'department_ids'  => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ]);

        $user->update([
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'] ?? null,
            'organization_id' => $validated['organization_id'] ?? null,
            'is_active'       => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles([$validated['role']]);

        if (isset($validated['department_ids'])) {
            $user->departments()->sync($validated['department_ids']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete(); // soft delete

        return redirect()->route('admin.users.index')
            ->with('success', 'User deactivated successfully.');
    }

    public function importForm()
    {
        return Inertia::render('Admin/Users/Import', [
            'organizations' => Organization::active()->select('id', 'name')->get(),
            'roles'         => Role::all()->pluck('name'),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file'        => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'role'            => ['required', 'string', 'exists:roles,name'],
        ]);

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);
        $header = array_map('strtolower', array_map('trim', $header));

        $imported = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $data = array_combine($header, $row);

            if (User::where('email', $data['email'] ?? '')->exists()) {
                $errors[] = "Row " . ($i + 2) . ": Email {$data['email']} already exists.";
                continue;
            }

            try {
                $user = User::create([
                    'first_name'      => $data['first_name'] ?? '',
                    'last_name'       => $data['last_name'] ?? '',
                    'email'           => $data['email'] ?? '',
                    'phone'           => $data['phone'] ?? null,
                    'password'        => Hash::make($data['password'] ?? 'Changeme1!'),
                    'organization_id' => $request->organization_id,
                    'is_active'       => true,
                ]);

                $user->assignRole($request->role);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($i + 2) . ": {$e->getMessage()}";
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', "{$imported} users imported." . (count($errors) ? ' ' . count($errors) . ' errors.' : ''))
            ->with('import_errors', $errors);
    }
}

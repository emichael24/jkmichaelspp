<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSeatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'users' => $query->paginate(20)->withQueryString(),
            'roles' => Role::all()->pluck('name'),
            'organizations' => Organization::active()->select('id', 'name')->get(),
            'filters' => $request->only(['search', 'role', 'organization_id', 'is_active']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::all()->pluck('name'),
            'organizations' => Organization::active()->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['boolean'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->where('organization_id', $request->input('organization_id'))],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'organization_id' => $validated['organization_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
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
            'user' => $user->load(['organization', 'roles', 'departments']),
            'roles' => Role::all()->pluck('name'),
            'organizations' => Organization::active()->select('id', 'name')->get(),
            'departments' => $user->organization_id
                ? Department::where('organization_id', $user->organization_id)->get()
                : collect(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['boolean'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->where('organization_id', $request->input('organization_id'))],
        ]);

        $organizationChanged = (int) $user->organization_id !== (int) ($validated['organization_id'] ?? null);
        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'organization_id' => $validated['organization_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles([$validated['role']]);

        if ($organizationChanged || isset($validated['department_ids'])) {
            $user->departments()->sync($validated['department_ids'] ?? []);
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
            'roles' => Role::all()->pluck('name'),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $rows = [];
        $seen = [];
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = array_map(fn ($value) => strtolower(trim(ltrim($value, "\xEF\xBB\xBF"))), $header ?: []);
            if (array_diff(['first_name', 'last_name', 'email'], $header) || array_diff($header, ['first_name', 'last_name', 'email', 'phone']) || count($header) !== count(array_unique($header))) {
                throw ValidationException::withMessages(['csv_file' => 'Use first_name, last_name and email, with an optional phone column. Do not include passwords.']);
            }
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($header) || count($rows) >= 100) {
                    throw ValidationException::withMessages(['csv_file' => 'Use at most 100 complete rows. No accounts have been imported.']);
                }
                $data = array_map('trim', array_combine($header, $row));
                $data['email'] = mb_strtolower($data['email']);
                $validator = validator($data, ['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:255|unique:users', 'phone' => 'nullable|string|max:20']);
                if ($validator->fails() || isset($seen[$data['email']])) {
                    throw ValidationException::withMessages(['csv_file' => 'Correct row '.(count($rows) + 2).': '.($validator->fails() ? implode(' ', $validator->errors()->all()) : 'Duplicate email address.')]);
                }
                $seen[$data['email']] = true;
                $rows[] = $data;
            }
        } finally {
            fclose($handle);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['csv_file' => 'Add at least one person.']);
        }
        DB::transaction(function () use ($rows, $request) {
            if ($request->organization_id) {
                $organization = Organization::whereKey($request->organization_id)->lockForUpdate()->firstOrFail();
                app(OrganizationSeatService::class)->ensureAvailable($organization, count($rows));
            }
            foreach ($rows as $data) {
                // Imports never install a shared or administrator-known password.
                $user = User::create([...$data, 'password' => Hash::make(bin2hex(random_bytes(32))), 'organization_id' => $request->organization_id, 'is_active' => true]);
                $user->assignRole($request->role);
            }
        }, 3);

        return redirect()->route('admin.users.index')->with('success', count($rows).' accounts imported. Each person must use Forgot password to choose their password. No email was sent by this import.');
    }
}

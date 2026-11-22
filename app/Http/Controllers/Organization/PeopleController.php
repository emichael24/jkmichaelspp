<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\OrganizationInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PeopleController extends Controller
{
    private function organization(Request $request): Organization
    {
        $organization = $request->user()->organization;
        abort_unless($organization?->is_active, 403);

        return $organization;
    }

    public function index(Request $request)
    {
        $org = $this->organization($request);
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'department' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $org->id)]]);
        $people = User::where('organization_id', $org->id)->with(['departments' => fn ($q) => $q->where('organization_id', $org->id)->select('departments.id', 'name'), 'roles:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($search) => $search->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->whereHas('departments', fn ($d) => $d->where('departments.id', $id)))
            ->withCount('enrollments')->orderBy('first_name')->paginate(20, ['id', 'first_name', 'last_name', 'email', 'is_active'])->withQueryString();

        return Inertia::render('Organization/People', ['organization' => $org->only('id', 'name'), 'seats' => $org->seatUsage(), 'people' => $people, 'filters' => $filters,
            'departments' => $org->departments()->orderBy('name')->get(['id', 'name']),
            'invitations' => OrganizationInvitation::where('organization_id', $org->id)->where('status', 'pending')->with('department:id,name')->latest()->paginate(10, ['id', 'email', 'department_id', 'expires_at', 'created_at'], 'invitations_page')->withQueryString()]);
    }

    public function invite(Request $request, OrganizationInvitationService $service)
    {
        $org = $this->organization($request);
        $data = $request->validate(['email' => 'required|email:rfc|max:255', 'department_id' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $org->id)]]);
        $service->invite($request->user(), [$data], $data['department_id'] ?? null);

        return back()->with('success', 'Invitation queued for email delivery. A seat is reserved for seven days.');
    }

    public function import(Request $request, OrganizationInvitationService $service)
    {
        $org = $this->organization($request);
        $data = $request->validate(['file' => 'required|file|mimes:csv,txt|max:512', 'department_id' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $org->id)]]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $rows = [];
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = array_map(fn ($value) => strtolower(trim(ltrim($value, "\xEF\xBB\xBF"))), $header ?: []);
            if (! in_array('email', $header, true) || count($header) !== count(array_unique($header)) || array_diff($header, ['email', 'first_name', 'last_name'])) {
                throw ValidationException::withMessages(['file' => 'Use an email column, with optional first_name and last_name columns.']);
            }
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($header) || count($rows) >= 100) {
                    throw ValidationException::withMessages(['file' => 'Use at most 100 complete rows. No invitations have been created.']);
                }
                $values = array_map('trim', array_combine($header, $row));
                $validator = validator($values, ['email' => 'required|email:rfc|max:255', 'first_name' => 'nullable|string|max:100', 'last_name' => 'nullable|string|max:100']);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['file' => 'Correct row '.(count($rows) + 2).': '.implode(' ', $validator->errors()->all())]);
                }
                $rows[] = $values;
            }
        } finally {
            fclose($handle);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'Add at least one person.']);
        }
        $service->invite($request->user(), $rows, $data['department_id'] ?? null);

        return back()->with('success', count($rows).' invitations queued. Seats are reserved for seven days.');
    }

    public function revoke(Request $request, OrganizationInvitation $invitation)
    {
        $org = $this->organization($request);
        abort_unless($invitation->organization_id === $org->id, 404);
        DB::transaction(function () use ($org, $invitation) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $current = OrganizationInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'pending', 409);
            $current->update(['status' => 'revoked']);
        });

        return back()->with('success', 'Invitation revoked. The link no longer works and its seat is available.');
    }

    public function update(Request $request, User $member)
    {
        $org = $this->organization($request);
        abort_unless($member->organization_id === $org->id && ! $member->hasRole('platform_admin'), 404);
        $data = $request->validate(['is_active' => 'required|boolean', 'department_ids' => 'present|array|max:30',
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->where('organization_id', $org->id)]]);
        if ($member->id === $request->user()->id && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
        }
        DB::transaction(function () use ($org, $member, $data) {
            Organization::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $member = User::whereKey($member->id)->where('organization_id', $org->id)->lockForUpdate()->firstOrFail();
            abort_if($member->hasRole('platform_admin'), 404);
            $oldDepartments = $member->departments()->pluck('departments.id')->all();
            $member->update(['is_active' => $data['is_active']]);
            $member->departments()->sync($data['department_ids']);
            AuditLog::log('departments_assigned', User::class, $member->id, ['department_ids' => $oldDepartments], ['department_ids' => $data['department_ids']]);
            if (! $data['is_active']) {
                DB::table('sessions')->where('user_id', $member->id)->delete();
                $member->tokens()->delete();
                PushSubscription::where('user_id', $member->id)->delete();
                foreach ($org->departments()->where('manager_id', $member->id)->get() as $department) {
                    $department->update(['manager_id' => null]);
                }
            }
        }, 3);

        return back()->with('success', 'Member access and departments updated.');
    }
}

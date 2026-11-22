<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class OrganizationController extends Controller
{
    private const FIELDS = ['name', 'industry', 'size', 'contact_email', 'contact_phone', 'address', 'country', 'max_seats', 'is_active'];

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $organizations = Organization::query()
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('contact_email', 'like', "%{$term}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->withCount(['users as active_members_count' => fn ($q) => $q->where('is_active', true),
                'users as administrators_count' => fn ($q) => $q->where('is_active', true)->whereHas('roles', fn ($role) => $role->where('name', 'org_admin'))])
            ->addSelect(['reserved_seats_count' => OrganizationInvitation::selectRaw('COUNT(*)')->whereColumn('organization_id', 'organizations.id')->pending()])
            ->orderBy('name')->paginate(15, ['*'])->withQueryString();

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations->through(fn ($org) => $org->only(['id', 'name', 'contact_email', 'industry', 'country', 'max_seats', 'is_active', 'active_members_count', 'administrators_count', 'reserved_seats_count'])),
            'filters' => $filters,
            'summary' => ['total' => Organization::count(), 'active' => Organization::active()->count(),
                'members' => User::whereHas('organization')->where('is_active', true)->count(), 'seats' => (int) Organization::active()->sum('max_seats')],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Organizations/Form', ['organization' => null, 'seats' => null, 'administrators' => []]);
    }

    public function edit(Organization $organization)
    {
        return Inertia::render('Admin/Organizations/Form', [
            'organization' => $organization->only(['id', ...self::FIELDS]), 'seats' => $organization->seatUsage(),
            'administrators' => $organization->users()->whereHas('roles', fn ($role) => $role->where('name', 'org_admin'))->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'is_active', 'email_verified_at']),
        ]);
    }

    public function candidates(Request $request)
    {
        $data = $request->validate(['search' => 'required|string|min:2|max:100', 'organization_id' => ['nullable', Rule::exists('organizations', 'id')->whereNull('deleted_at')]]);

        return response()->json(['users' => $this->eligibleUsers($data['organization_id'] ?? null)
            ->where(fn ($q) => $q->where('first_name', 'like', '%'.$data['search'].'%')->orWhere('last_name', 'like', '%'.$data['search'].'%')->orWhere('email', 'like', '%'.$data['search'].'%'))
            ->orderBy('first_name')->limit(20)->get(['id', 'first_name', 'last_name', 'email', 'organization_id'])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $organization = DB::transaction(function () use ($data) {
            $organization = Organization::create([...array_intersect_key($data, array_flip(self::FIELDS)),
                'slug' => (Str::slug(Str::limit($data['name'], 180, '')) ?: 'organization').'-'.Str::lower(Str::random(12))]);
            $this->assignAdministrator($organization, $data);
            AuditLog::log('organization_created', Organization::class, $organization->id, null, $organization->only(self::FIELDS));

            return $organization;
        }, 3);

        return redirect()->route('admin.organizations.edit', $organization)->with('success', 'Organization created. You can now manage its users and learning workspace.');
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($organization, $data) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $seats = $organization->seatUsage();
            if ($data['max_seats'] < $seats['used'] + $seats['reserved']) {
                throw ValidationException::withMessages(['max_seats' => 'Allow at least '.($seats['used'] + $seats['reserved']).' seats for active members and pending invitations.']);
            }
            $before = $organization->only(self::FIELDS);
            $organization->update(array_intersect_key($data, array_flip(self::FIELDS)));
            $this->assignAdministrator($organization, $data);
            if ($before['is_active'] && ! $organization->is_active) {
                $organization->users()->whereDoesntHave('roles', fn ($q) => $q->where('name', 'platform_admin'))->chunkById(100, function ($users) {
                    DB::table('sessions')->whereIn('user_id', $users->modelKeys())->delete();
                    PushSubscription::whereIn('user_id', $users->modelKeys())->delete();
                    foreach ($users as $user) {
                        $user->tokens()->delete();
                    }
                });
            }
            AuditLog::log('organization_updated', Organization::class, $organization->id, $before, $organization->only(self::FIELDS));
        }, 3);

        return redirect()->route('admin.organizations.edit', $organization)->with('success', 'Organization details and access settings saved.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['contact_email' => mb_strtolower(trim((string) $request->input('contact_email'))),
            'country' => strtoupper(trim((string) $request->input('country'))),
            'admin_email' => mb_strtolower(trim((string) $request->input('admin_email')))]);

        return $request->validate([
            'name' => 'required|string|max:255', 'industry' => 'nullable|string|max:100', 'size' => ['nullable', Rule::in(['1-50', '51-200', '201-1000', '1000+'])],
            'contact_email' => 'required|email:rfc|max:255', 'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:2000', 'country' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'max_seats' => 'required|integer|min:1|max:1000000', 'is_active' => 'required|boolean',
            'admin_mode' => ['required', Rule::in(['none', 'existing', 'new'])],
            'admin_user_id' => 'exclude_unless:admin_mode,existing|required|integer',
            'admin_first_name' => 'exclude_unless:admin_mode,new|required|string|max:100',
            'admin_last_name' => 'exclude_unless:admin_mode,new|required|string|max:100',
            'admin_email' => ['exclude_unless:admin_mode,new', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['exclude_unless:admin_mode,new', 'required', 'confirmed', 'max:72', Password::min(12)->letters()->numbers()],
        ]);
    }

    private function eligibleUsers(?int $organizationId): Builder
    {
        return User::where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->where('name', 'platform_admin'))
            ->where(function ($query) use ($organizationId) {
                $query->where(fn ($q) => $q->whereNull('organization_id')->whereDoesntHave('roles', fn ($role) => $role->whereNotIn('name', ['learner', 'org_admin'])));
                if ($organizationId) {
                    $query->orWhere('organization_id', $organizationId);
                }
            });
    }

    private function assignAdministrator(Organization $organization, array $data): void
    {
        if ($data['admin_mode'] === 'none') {
            return;
        }
        if (! $organization->is_active) {
            throw ValidationException::withMessages(['admin_mode' => 'Activate the organization before assigning an administrator.']);
        }
        if ($data['admin_mode'] === 'existing') {
            $user = $this->eligibleUsers($organization->id)->whereKey($data['admin_user_id'])->lockForUpdate()->first();
            if (! $user) {
                throw ValidationException::withMessages(['admin_user_id' => 'Choose an active member of this organization or an unassigned learner. Accounts from other organizations and platform administrators cannot be assigned here.']);
            }
            if (! $user->organization_id) {
                if (OrganizationInvitation::where('organization_id', $organization->id)->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])->pending()->exists()) {
                    throw ValidationException::withMessages(['admin_user_id' => 'This person has a pending invitation. They must accept it or the invitation must be revoked before assignment.']);
                }
                $user->organization_id = $organization->id;
                $user->save();
                $user->departments()->detach();
            }
        } else {
            $user = User::create(['first_name' => $data['admin_first_name'], 'last_name' => $data['admin_last_name'],
                'email' => $data['admin_email'], 'password' => $data['admin_password'], 'organization_id' => $organization->id, 'is_active' => true]);
        }
        $alreadyAdmin = $user->hasRole('org_admin');
        $user->assignRole(Role::findOrCreate('org_admin', 'web'));
        if (! $alreadyAdmin) {
            AuditLog::log('organization_administrator_assigned', Organization::class, $organization->id, null, ['user_id' => $user->id]);
        }
    }
}

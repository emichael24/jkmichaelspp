<?php

namespace App\Services;

use App\Mail\OrganizationInvitationMail;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class OrganizationInvitationService
{
    public function invite(User $actor, array $rows, ?int $departmentId): void
    {
        abort_unless($actor->hasRole('org_admin') && $actor->organization_id, 403);
        DB::transaction(function () use ($actor, $rows, $departmentId) {
            $organization = Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            if ($departmentId) {
                abort_unless(Department::where('organization_id', $organization->id)->whereKey($departmentId)->exists(), 404);
            }
            app(OrganizationSeatService::class)->ensureAvailable($organization, count($rows));
            $seen = [];
            foreach ($rows as $index => $row) {
                $email = mb_strtolower(trim($row['email']));
                $existing = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
                if (isset($seen[$email]) || ($existing && ($existing->organization_id || $existing->trashed() || ! $existing->is_active || $existing->roles()->where('name', '<>', 'learner')->exists()))
                    || OrganizationInvitation::where('organization_id', $organization->id)->where('email', $email)->pending()->exists()) {
                    throw ValidationException::withMessages(['email' => 'Invitation '.($index + 1).' cannot be created. Check for duplicate invitations, existing members or an address that needs platform support.']);
                }
                $seen[$email] = true;
            }
            foreach ($rows as $row) {
                $token = bin2hex(random_bytes(32));
                $invite = OrganizationInvitation::create([
                    'organization_id' => $organization->id, 'invited_by' => $actor->id, 'department_id' => $departmentId,
                    'email' => mb_strtolower(trim($row['email'])), 'first_name' => $row['first_name'] ?? null, 'last_name' => $row['last_name'] ?? null,
                    'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
                ]);
                Mail::to($invite->email)->queue(new OrganizationInvitationMail($organization->name, route('organization.invitation.show', $token)));
            }
        }, 3);
    }

    public function resolve(string $token): OrganizationInvitation
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        $invitation = OrganizationInvitation::where('token_hash', hash('sha256', $token))->pending()->with('organization')->firstOrFail();
        abort_unless($invitation->organization?->is_active, 404);

        return $invitation;
    }

    public function accept(string $token, ?User $actor, array $data): User
    {
        $candidate = $this->resolve($token);

        return DB::transaction(function () use ($candidate, $actor, $data) {
            $organization = Organization::whereKey($candidate->organization_id)->lockForUpdate()->firstOrFail();
            abort_unless($organization->is_active, 404);
            $invitation = OrganizationInvitation::whereKey($candidate->id)->pending()->lockForUpdate()->firstOrFail();
            $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$invitation->email])->lockForUpdate()->first();
            if ($user) {
                abort_unless($actor && $actor->id === $user->id && ! $user->trashed() && $user->is_active, 403, 'Sign in with the invited account to accept this invitation.');
                abort_unless(! $user->organization_id && ! $user->roles()->where('name', '<>', 'learner')->exists(), 403, 'This account needs platform support before joining.');
            } else {
                abort_if($actor !== null, 403, 'Sign out and use the invited email address.');
                validator($data, ['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
                    'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]])->validate();
                $user = new User(['email' => $invitation->email, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'password' => Hash::make($data['password']), 'is_active' => true]);
            }
            // Consume this reservation inside the same organization lock before
            // the global User seat guard checks the newly occupied seat.
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            $user->organization_id = $organization->id;
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();
            $user->assignRole(Role::findOrCreate('learner', 'web'));
            if ($invitation->department_id) {
                abort_unless(Department::whereKey($invitation->department_id)->where('organization_id', $organization->id)->exists(), 409, 'The invitation department has changed. Ask your administrator for a new invitation.');
                $user->departments()->syncWithoutDetaching([$invitation->department_id]);
            }
            $invitation->update(['user_id' => $user->id]);

            return $user;
        }, 3);
    }
}

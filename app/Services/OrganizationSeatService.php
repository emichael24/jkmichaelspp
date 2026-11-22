<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrganizationSeatService
{
    public function reserved(Organization $organization): int
    {
        return Schema::hasTable('organization_invitations')
            ? OrganizationInvitation::where('organization_id', $organization->id)->pending()->count() : 0;
    }

    public function ensureAvailable(Organization $organization, int $needed = 1, ?int $exceptUser = null): void
    {
        $used = User::where('organization_id', $organization->id)->where('is_active', true)
            ->when($exceptUser, fn ($query) => $query->where('id', '<>', $exceptUser))->count();
        if (! $organization->is_active || $used + $this->reserved($organization) + $needed > $organization->max_seats) {
            throw ValidationException::withMessages(['organization_id' => 'No seats are available. Revoke an unused invitation, deactivate an account, or ask the platform administrator to increase your allocation.']);
        }
    }

    public function saveUser(User $user, callable $save): bool
    {
        $joining = ! $user->exists || $user->isDirty(['organization_id', 'is_active', 'deleted_at']);
        if (! $joining || ! $user->organization_id || ! ($user->is_active ?? true) || $user->deleted_at) {
            return $save();
        }

        return DB::transaction(function () use ($user, $save) {
            $organization = Organization::whereKey($user->organization_id)->lockForUpdate()->firstOrFail();
            $this->ensureAvailable($organization, 1, $user->exists ? $user->id : null);

            return $save();
        }, 3);
    }
}

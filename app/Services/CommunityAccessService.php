<?php

namespace App\Services;

use App\Models\Forum;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CommunityAccessService
{
    public function forums(User $user): Builder
    {
        $query = Forum::query();
        if ($user->hasRole('platform_admin')) {
            return $query;
        }

        return $query->where('organization_id', $user->organization_id)
            ->where(function (Builder $query) use ($user) {
                $query->where(fn ($general) => $general->where('is_general', true)->whereNull('course_id')->whereNull('cohort_id'))
                    ->orWhereHas('cohort', fn ($cohort) => $cohort->where('organization_id', $user->organization_id)
                        ->where(fn ($membership) => $membership->where('facilitator_id', $user->id)
                            ->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id)->where('status', 'active'))))
                    ->orWhere(fn ($courseForum) => $courseForum->whereNull('cohort_id')->whereHas('course', fn ($course) => $course
                        ->where('is_published', true)->where(fn ($access) => $access->where('author_id', $user->id)
                        ->orWhereHas('enrollments', fn ($enrollment) => $enrollment->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])))));
            });
    }

    public function forum(int $id, User $user): Forum
    {
        return $this->forums($user)->findOrFail($id);
    }

    public function canModerate(Forum $forum, User $user): bool
    {
        return $user->hasRole('platform_admin') || (
            $forum->organization_id === $user->organization_id && (
                ($user->organization_id !== null && $user->hasRole('org_admin')) ||
                $forum->cohort?->facilitator_id === $user->id ||
                $forum->course?->author_id === $user->id
            )
        );
    }

    public function groups(User $user): Builder
    {
        $query = StudyGroup::query();
        if ($user->hasRole('platform_admin')) {
            return $query;
        }

        return $query->where('organization_id', $user->organization_id)
            ->where(fn ($visibility) => $visibility->where('is_public', true)->orWhere('created_by', $user->id)
                ->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id)))
            ->where(fn ($course) => $course->whereNull('course_id')->orWhereHas('course', fn ($course) => $course->where('is_published', true)
                ->whereHas('enrollments', fn ($enrollment) => $enrollment->where('user_id', $user->id)->whereIn('status', ['active', 'completed']))));
    }

    public function peers(User $user): Builder
    {
        $query = User::query()->where('is_active', true);
        if (! $user->hasRole('platform_admin')) {
            $query->where(fn ($scope) => $scope->where('organization_id', $user->organization_id)
                ->orWhere(fn ($admin) => $admin->whereHas('roles', fn ($roles) => $roles->where('name', 'platform_admin')->where('guard_name', 'web'))->whereExists(fn ($messages) => $messages
                    ->selectRaw('1')->from('direct_messages')->whereColumn('direct_messages.sender_id', 'users.id')
                    ->where('direct_messages.recipient_id', $user->id)->whereNotNull('direct_messages.campaign_id'))));
        }

        return $query;
    }
}

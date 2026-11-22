<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Validation\Rule;

class CampaignAudienceService
{
    public static function rules(): array
    {
        return [
            'audience_filter' => 'nullable|array:source,role,organization_id,organization_members,course_id,cohort_id,contact_types,crm_organization_id',
            'audience_filter.source' => 'nullable|in:users,crm,all',
            'audience_filter.role' => 'nullable|string|exists:roles,name',
            'audience_filter.organization_id' => 'nullable|integer|exists:organizations,id',
            'audience_filter.organization_members' => 'nullable|boolean',
            'audience_filter.course_id' => 'nullable|integer|exists:courses,id',
            'audience_filter.cohort_id' => 'nullable|integer|exists:cohorts,id',
            'audience_filter.contact_types' => 'nullable|array|max:6',
            'audience_filter.contact_types.*' => ['string', 'distinct', Rule::in(CrmContactService::TYPES)],
            'audience_filter.crm_organization_id' => 'nullable|integer|exists:crm_organizations,id',
        ];
    }

    public function address(User|CrmContact $person, string $channel): string
    {
        if ($channel === 'inbox') {
            return (string) ($person instanceof User ? $person->id : $this->account($person)?->id);
        }

        return trim((string) ($channel === 'mail' ? $person->email : ($person instanceof User ? ($person->whatsapp_number ?: $person->phone) : ($person->whatsapp ?: $person->phone))));
    }

    public function key(string $address, string $channel): string
    {
        return $channel === 'mail' ? strtolower(trim($address)) : preg_replace('/\D/', '', preg_replace('/^00/', '', trim($address)));
    }

    public function account(CrmContact $contact): ?User
    {
        return $contact->user_id ? $contact->linkedUser : ($contact->email ? User::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($contact->email))])->first() : null);
    }

    public function eligible(User|CrmContact $person, string $channel, bool $marketing = true): bool
    {
        $address = $this->address($person, $channel);
        if ($channel !== 'inbox' && ($channel === 'mail' ? ! filter_var($address, FILTER_VALIDATE_EMAIL) : ! preg_match('/^[1-9][0-9]{7,14}$/', $this->key($address, $channel)))) {
            return false;
        }
        if ($person instanceof User) {
            if (! $person->is_active || ! $person->email_verified_at || ($person->organization_id && ! $person->organization?->is_active)) {
                return false;
            }

            return $channel === 'inbox' ? ($person->notificationPreferences?->announcements ?? true) : (! $marketing || (bool) $person->notificationPreferences?->getAttribute($channel === 'mail' ? 'marketing_email' : 'marketing_whatsapp'));
        }
        if ($person->trashed() || $person->status === 'inactive') {
            return false;
        }
        $account = $this->account($person);
        if (($person->user_id && ! $account) || ($account && (
            ! $account->is_active || ! $account->email_verified_at || ($account->organization_id && ! $account->organization?->is_active)
            || ($channel === 'inbox' ? ! ($account->notificationPreferences?->announcements ?? true)
                : ($marketing && ! $account->notificationPreferences?->getAttribute($channel === 'mail' ? 'marketing_email' : 'marketing_whatsapp')))
        ))) {
            return false;
        }

        return $channel === 'inbox' ? $account !== null : (! $marketing || (bool) $person->getAttribute($channel === 'mail' ? 'marketing_email' : 'marketing_whatsapp'));
    }

    /** Preview and launch share this streaming, deduplicated audience calculation. */
    public function each(array $filter, string $channel, ?callable $receive = null): array
    {
        $stats = ['matched' => 0, 'eligible' => 0, 'excluded' => 0, 'duplicates' => 0, 'sample' => []];
        $seen = [];
        $visit = function ($person) use (&$stats, &$seen, $channel, $receive) {
            $stats['matched']++;
            if (! $this->eligible($person, $channel)) {
                $stats['excluded']++;

                return;
            }
            $address = $this->address($person, $channel);
            $key = hash('sha256', $this->key($address, $channel));
            if (isset($seen[$key])) {
                $stats['duplicates']++;

                return;
            }
            $seen[$key] = true;
            $stats['eligible']++;
            if (count($stats['sample']) < 5) {
                $stats['sample'][] = ['name' => $person->full_name, 'recipient' => $address, 'source' => $person instanceof User ? 'Account' : 'CRM'];
            }
            if ($receive) {
                $receive($person, $address, $key);
            }
        };
        $source = $filter['source'] ?? 'users';
        if (in_array($source, ['users', 'all'], true)) {
            User::with(['notificationPreferences', 'organization'])
                ->when($filter['role'] ?? null, fn ($q, $role) => $q->role($role))
                ->when($filter['organization_members'] ?? false, fn ($q) => $q->whereNotNull('organization_id'))
                ->when($filter['organization_id'] ?? null, fn ($q, $id) => $q->where('organization_id', $id))
                ->when($filter['course_id'] ?? null, fn ($q, $id) => $q->whereHas('enrollments', fn ($q) => $q->where('course_id', $id)))
                ->when($filter['cohort_id'] ?? null, fn ($q, $id) => $q->whereHas('cohortMemberships', fn ($q) => $q->where('cohort_id', $id)))
                ->lazyById(200)->each($visit);
        }
        if (in_array($source, ['crm', 'all'], true)) {
            CrmContact::with(['linkedUser.notificationPreferences', 'linkedUser.organization'])
                ->when($filter['contact_types'] ?? [], fn ($q, $types) => $q->whereIn('type', $types))
                ->when($filter['crm_organization_id'] ?? null, fn ($q, $id) => $q->where('organization_id', $id))
                ->lazyById(200)->each($visit);
        }

        return $stats;
    }
}

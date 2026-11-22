<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmContact extends Model
{
    use RecordsDomainAudit;
    use SoftDeletes;

    protected $hidden = ['email_normalized'];

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value;
        $this->attributes['email_normalized'] = $value === null ? null : strtolower(trim($value));
    }

    protected $fillable = [
        'uuid', 'user_id', 'organization_id', 'first_name', 'last_name', 'email', 'phone', 'whatsapp',
        'company', 'job_title', 'type', 'status', 'source', 'notes', 'tags',
        'custom_fields', 'assigned_to', 'last_contacted_at', 'is_primary_decision_maker',
        'lead_score', 'engagement_level', 'lifecycle_stage', 'marketing_email', 'marketing_whatsapp',
        'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
    ];

    protected $casts = [
        'marketing_email' => 'boolean', 'marketing_whatsapp' => 'boolean',
        'tags' => 'array',
        'custom_fields' => 'array',
        'last_contacted_at' => 'datetime',
        'is_primary_decision_maker' => 'boolean',
    ];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function organization()
    {
        return $this->belongsTo(CrmOrganization::class, 'organization_id');
    }

    public function linkedUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function deals()
    {
        return $this->hasMany(CrmDeal::class, 'contact_id');
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class, 'contact_id')->latest();
    }
}

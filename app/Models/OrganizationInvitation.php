<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationInvitation extends Model
{
    use Traits\RecordsDomainAudit;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentApproval extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'status',
        'submitted_by',
        'reviewed_by',
        'review_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at'  => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function approvable()
    {
        return $this->morphTo();
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForContent($query, string $type, int $id)
    {
        return $query->where('approvable_type', $type)
                     ->where('approvable_id', $id);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status'       => 'approved',
            'reviewed_by'  => $reviewer->id,
            'review_notes' => $notes,
            'reviewed_at'  => now(),
        ]);
    }

    public function reject(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status'       => 'rejected',
            'reviewed_by'  => $reviewer->id,
            'review_notes' => $notes,
            'reviewed_at'  => now(),
        ]);
    }

    public function requestChanges(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status'       => 'changes_requested',
            'reviewed_by'  => $reviewer->id,
            'review_notes' => $notes,
            'reviewed_at'  => now(),
        ]);
    }
}

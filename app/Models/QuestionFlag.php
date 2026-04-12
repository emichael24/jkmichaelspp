<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionFlag extends Model
{
    protected $fillable = [
        'question_id',
        'flagged_by',
        'reason',
        'description',
        'status',
        'resolved_by',
        'resolution_notes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function flagger()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInReview($query)
    {
        return $query->where('status', 'in_review');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['open', 'in_review']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function resolve(User $resolver, string $notes, string $status = 'resolved'): void
    {
        $this->update([
            'status'           => $status,
            'resolved_by'      => $resolver->id,
            'resolution_notes' => $notes,
        ]);
    }

    public function dismiss(User $resolver, string $notes): void
    {
        $this->resolve($resolver, $notes, 'dismissed');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpacedRepetitionItem extends Model
{
    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'ease_factor',
        'interval_days',
        'repetitions',
        'next_review_at',
        'last_reviewed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ease_factor'      => 'decimal:2',
            'interval_days'    => 'integer',
            'repetitions'      => 'integer',
            'next_review_at'   => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDueNow($query)
    {
        return $query->where('next_review_at', '<=', now());
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['new', 'learning', 'review']);
    }

    public function scopeGraduated($query)
    {
        return $query->where('status', 'graduated');
    }

    /*
    |--------------------------------------------------------------------------
    | SM-2 Algorithm
    |--------------------------------------------------------------------------
    |
    | After each review, user rates quality (0-5):
    |  0: Complete blackout
    |  1: Incorrect but recognized
    |  2: Incorrect but easy to recall
    |  3: Correct with difficulty
    |  4: Correct with hesitation
    |  5: Perfect recall
    |
    | If quality >= 3:
    |   interval = previous_interval * ease_factor
    |   ease_factor = max(1.3, EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02)))
    | Else:
    |   repetitions = 0, interval = 1 day
    |
    */

    public function processReview(int $quality): void
    {
        $quality = max(0, min(5, $quality));

        if ($quality >= 3) {
            // Correct response
            if ($this->repetitions === 0) {
                $this->interval_days = 1;
            } elseif ($this->repetitions === 1) {
                $this->interval_days = 6;
            } else {
                $this->interval_days = (int) round($this->interval_days * $this->ease_factor);
            }

            $this->repetitions++;

            // Update ease factor
            $this->ease_factor = max(
                1.3,
                $this->ease_factor + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02))
            );

            // Determine status
            if ($this->interval_days >= 21 && $this->repetitions >= 5) {
                $this->status = 'graduated';
            } else {
                $this->status = 'review';
            }
        } else {
            // Incorrect response — reset
            $this->repetitions = 0;
            $this->interval_days = 1;
            $this->status = 'learning';

            // Decrease ease factor but not below 1.3
            $this->ease_factor = max(1.3, $this->ease_factor - 0.2);
        }

        $this->last_reviewed_at = now();
        $this->next_review_at = now()->addDays($this->interval_days);
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get or create an SR item for a user + reviewable.
     */
    public static function ensureExists(int $userId, string $type, int $id): self
    {
        return static::firstOrCreate(
            [
                'user_id'         => $userId,
                'reviewable_type' => $type,
                'reviewable_id'   => $id,
            ],
            [
                'ease_factor'    => 2.50,
                'interval_days'  => 1,
                'repetitions'    => 0,
                'next_review_at' => now(),
                'status'         => 'new',
            ]
        );
    }

    /**
     * Get the count of items due for a user.
     */
    public static function dueCount(int $userId): int
    {
        return static::forUser($userId)->active()->dueNow()->count();
    }
}

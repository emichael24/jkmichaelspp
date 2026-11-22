<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Simulation extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'type',
        'difficulty',
        'estimated_minutes',
        'context_narrative',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'estimated_minutes' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function nodes()
    {
        return $this->hasMany(SimulationNode::class)->orderBy('sort_order');
    }

    public function startNode()
    {
        return $this->hasOne(SimulationNode::class)->where('is_start', true);
    }

    public function attempts()
    {
        return $this->hasMany(SimulationAttempt::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getBestPracticePath(): array
    {
        return $this->nodes()
            ->where('is_best_practice', true)
            ->orderBy('sort_order')
            ->pluck('id')
            ->toArray();
    }

    public function getDecisionNodeCount(): int
    {
        return $this->nodes()->where('type', 'decision')->count();
    }

    public function attemptsBy(User $user)
    {
        return $this->attempts()->where('user_id', $user->id);
    }

    public function hasBeenAttemptedBy(User $user): bool
    {
        return $this->attemptsBy($user)->exists();
    }
}

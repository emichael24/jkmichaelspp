<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentVersion extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Published snapshots cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Published snapshots cannot be deleted.'));
    }

    protected $fillable = [
        'versionable_type',
        'versionable_id',
        'version_number',
        'data',
        'change_summary',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function versionable()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForContent($query, string $type, int $id)
    {
        return $query->where('versionable_type', $type)
            ->where('versionable_id', $id);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('version_number');
    }
}

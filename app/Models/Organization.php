<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'logo',
        'industry',
        'size',
        'contact_email',
        'contact_phone',
        'address',
        'country',
        'settings',
        'subscription_plan',
        'max_seats',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings'  => 'array',
            'is_active' => 'boolean',
            'max_seats' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasAvailableSeats(): bool
    {
        return $this->users()->active()->count() < $this->max_seats;
    }

    public function seatUsage(): array
    {
        $used = $this->users()->active()->count();

        return [
            'used'      => $used,
            'max'       => $this->max_seats,
            'available' => max(0, $this->max_seats - $used),
            'percent'   => $this->max_seats > 0 ? round(($used / $this->max_seats) * 100) : 0,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->uuid)) {
                $org->uuid = (string) Str::uuid();
            }
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name);
            }
        });
    }
}

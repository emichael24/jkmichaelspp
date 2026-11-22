<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Product extends Model
{
    protected $fillable = [
        'productable_type',
        'productable_id',
        'name',
        'slug',
        'price',
        'currency',
        'discount_price',
        'discount_expires_at',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'discount_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ── Relationships ──

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePurchasable($query)
    {
        return $query->active()->where(function ($query) {
            $query->whereHasMorph('productable', [Course::class], fn ($course) => $course->where('is_published', true))
                ->orWhereHasMorph('productable', [Bundle::class], fn ($bundle) => $bundle->whereHas('courses')
                    ->whereDoesntHave('courses', fn ($course) => $course->where('is_published', false)));
        });
    }

    public function scopeSearch($query, ?string $search)
    {
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query;
    }

    // ── Helpers ──

    public function getCurrentPrice(): float
    {
        if ($this->hasActiveDiscount()) {
            return (float) $this->discount_price;
        }

        return (float) $this->price;
    }

    public function hasActiveDiscount(): bool
    {
        return $this->discount_price !== null && $this->discount_price >= 0 && $this->discount_price < $this->price
            && $this->discount_expires_at
            && $this->discount_expires_at->isFuture();
    }

    public function getSavingsPercentage(): ?int
    {
        if (! $this->hasActiveDiscount()) {
            return null;
        }

        return (int) round((1 - $this->discount_price / $this->price) * 100);
    }
}

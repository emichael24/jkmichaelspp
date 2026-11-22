<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'product_id',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ── Relationships ──

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Validation ──

    public function isValid(?int $productId = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }
        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }
        if ($this->product_id && $this->product_id !== $productId) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $price): float
    {
        if ($this->type === 'percentage') {
            return min($price, max(0, round($price * ($this->value / 100), 2)));
        }

        return min(max(0, $this->value), $price);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}

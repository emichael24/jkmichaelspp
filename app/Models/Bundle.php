<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Bundle extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail',
    ];

    // ── Relationships ──

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'bundle_items');
    }

    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }

    // ── Helpers ──

    public function getCourseCount(): int
    {
        return $this->courses()->count();
    }

    public function getIndividualTotal(): float
    {
        return $this->courses->sum(function ($course) {
            $product = $course->product;
            return $product ? $product->getCurrentPrice() : 0;
        });
    }
}

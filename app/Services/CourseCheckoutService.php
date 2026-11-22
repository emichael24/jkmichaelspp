<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseCheckoutService
{
    public function offer(Course $course): array
    {
        $product = Product::where('productable_type', Course::class)->where('productable_id', $course->id)->orderByDesc('is_active')->orderBy('id')->first();

        return ['price' => $product ? $product->getCurrentPrice() : ($course->discount_price !== null ? min((float) $course->price, (float) $course->discount_price) : (float) $course->price),
            'currency' => $product?->currency ?? $course->currency, 'purchasable' => ! $product || $product->is_active];
    }

    public function product(Course $course): Product
    {
        return DB::transaction(function () use ($course) {
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($course->is_published && ! $course->superseded_at, 404);
            $existing = Product::where('productable_type', Course::class)->where('productable_id', $course->id)->orderByDesc('is_active')->orderBy('id')->first();
            if ($existing) {
                if (! $existing->is_active) {
                    throw ValidationException::withMessages(['checkout' => 'Checkout for this course is unavailable. Contact the learning team.']);
                }

                return $existing;
            }
            $price = $course->discount_price !== null ? min((float) $course->price, (float) $course->discount_price) : (float) $course->price;
            if ($price < 0) {
                throw ValidationException::withMessages(['checkout' => 'This course needs a pricing review. Contact the learning team.']);
            }

            return Product::create(['productable_type' => Course::class, 'productable_id' => $course->id,
                'name' => $course->title, 'slug' => 'course-'.$course->uuid, 'price' => $price, 'currency' => $course->currency, 'is_active' => true]);
        }, 3);
    }
}

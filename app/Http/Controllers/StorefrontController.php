<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('productable')
            ->purchasable()
            ->search($request->search);

        if ($request->type) {
            $query->where('productable_type', 'App\\Models\\'.ucfirst($request->type));
        }

        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->sort === 'price_asc') {
            $query->orderBy('price');
        } elseif ($request->sort === 'price_desc') {
            $query->orderByDesc('price');
        } else {
            $query->latest();
        }

        $products = $query->paginate(12)->through(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'currency' => $product->currency,
                'discount_price' => $product->discount_price,
                'has_discount' => $product->hasActiveDiscount(),
                'current_price' => $product->getCurrentPrice(),
                'savings_pct' => $product->getSavingsPercentage(),
                'type' => class_basename($product->productable_type),
                'productable' => $product->productable ? [
                    'id' => $product->productable->id,
                    'title' => $product->productable->title ?? $product->productable->name ?? '',
                    'thumbnail' => $product->productable->thumbnail ?? null,
                    'description' => $product->productable->description ?? null,
                ] : null,
            ];
        });

        $bundles = Bundle::with(['product', 'courses'])->whereHas('product', fn ($query) => $query->purchasable())->limit(12)->get()->map(function ($bundle) {
            return [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'thumbnail' => $bundle->thumbnail,
                'course_count' => $bundle->courses->count(),
                'product' => $bundle->product ? [
                    'price' => $bundle->product->price,
                    'current_price' => $bundle->product->getCurrentPrice(),
                    'currency' => $bundle->product->currency,
                    'has_discount' => $bundle->product->hasActiveDiscount(),
                ] : null,
            ];
        });

        return Inertia::render('Storefront/Index', [
            'products' => $products,
            'bundles' => $bundles,
            'filters' => $request->only('search', 'type', 'sort', 'min_price', 'max_price'),
        ]);
    }

    public function show(string $slug)
    {
        $product = Product::with('productable')
            ->where('slug', $slug)
            ->purchasable()
            ->firstOrFail();

        $related = Product::purchasable()
            ->where('id', '!=', $product->id)
            ->where('productable_type', $product->productable_type)
            ->limit(4)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'current_price' => $p->getCurrentPrice(),
                'currency' => $p->currency,
                'type' => class_basename($p->productable_type),
            ]);

        return Inertia::render('Storefront/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'currency' => $product->currency,
                'discount_price' => $product->discount_price,
                'has_discount' => $product->hasActiveDiscount(),
                'current_price' => $product->getCurrentPrice(),
                'savings_pct' => $product->getSavingsPercentage(),
                'type' => class_basename($product->productable_type),
                'productable' => collect($product->productable->toArray())->only(['id', 'title', 'name', 'description', 'thumbnail'])->all(),
            ],
            'related' => $related,
            'gateways' => app(PaymentService::class)->availableGateways(),
        ]);
    }
}

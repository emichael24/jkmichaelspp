<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Products (polymorphic: Course, Bundle, InternshipTrack) ───
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('productable_type');
            $table->unsignedBigInteger('productable_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->timestamp('discount_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['productable_type', 'productable_id']);
        });

        // ─── Bundles ───
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->timestamps();
        });

        // ─── Bundle Items (pivot) ───
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['bundle_id', 'course_id']);
        });

        // ─── Discount Codes ───
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Orders ───
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });

        // ─── Order Items ───
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamp('created_at')->useCurrent();
        });

        // ─── Payments ───
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('gateway', ['paystack', 'flutterwave', 'stripe', 'manual']);
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_response')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('gateway_reference');
            $table->index('order_id');
            $table->index('user_id');
            $table->index('status');
        });

        // ─── Notification Preferences ───
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('learning_reminders')->default(true);
            $table->boolean('internship_reminders')->default(true);
            $table->boolean('announcements')->default(true);
            $table->enum('frequency', ['realtime', 'daily_digest', 'weekly_digest'])->default('realtime');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('bundles');
        Schema::dropIfExists('products');
    }
};

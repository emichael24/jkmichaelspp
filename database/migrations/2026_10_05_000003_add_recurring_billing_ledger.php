<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_checkouts', function (Blueprint $t) {
            $t->string('reference', 80)->primary();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('plan_code');
            $t->string('email');
            $t->string('billing_period', 16);
            $t->unsignedInteger('billing_interval');
            $t->string('status', 24)->default('pending');
            $t->text('authorization_url')->nullable();
            $t->dateTime('expires_at');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->unsignedBigInteger('amount_minor')->nullable();
            $t->string('currency', 3)->nullable();
            $t->string('provider_customer_code')->nullable()->index();
            $t->string('provider_plan_code')->nullable();
            $t->text('provider_email_token')->nullable();
            $t->timestamp('last_paid_at')->nullable();
        });
        Schema::create('billing_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 100)->unique();
            $t->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->dateTime('paid_at');
            $t->dateTime('period_end');
            $t->timestamps();
        });
        Schema::create('billing_webhook_events', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->string('event', 80);
            $t->longText('payload');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('billing_receipts');
        Schema::dropIfExists('subscription_checkouts');
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropIndex(['provider_customer_code']));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn(['amount_minor', 'currency', 'provider_customer_code', 'provider_plan_code', 'provider_email_token', 'last_paid_at']));
    }
};

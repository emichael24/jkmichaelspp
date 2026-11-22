<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('active_mode', 10)->nullable();
            $table->text('credentials')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        DB::table('payment_gateway_settings')->insert(['provider' => 'paystack', 'created_at' => now(), 'updated_at' => now()]);
        $mode = str_starts_with((string) config('services.paystack.secret_key'), 'sk_live_') ? 'live' : 'test';
        foreach (['payments', 'paystack_transactions', 'subscription_checkouts', 'subscriptions', 'billing_webhook_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('payment_mode', 10)->nullable());
            // Preserve the environment of records created before an administrator can switch modes.
            $query = DB::table($name);
            if ($name === 'payments') $query->where('gateway', 'paystack');
            if ($name === 'subscriptions') $query->where('payment_provider', 'paystack');
            $query->update(['payment_mode' => $mode]);
        }
    }

    public function down(): void
    {
        foreach (['payments', 'paystack_transactions', 'subscription_checkouts', 'subscriptions', 'billing_webhook_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('payment_mode'));
        }
        Schema::dropIfExists('payment_gateway_settings');
    }
};

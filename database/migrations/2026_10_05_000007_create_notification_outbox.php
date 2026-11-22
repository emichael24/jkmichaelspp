<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('marketing_email')->default(false);
            $table->boolean('marketing_whatsapp')->default(false);
        });
        Schema::table('notifications', fn (Blueprint $table) => $table->char('event_key', 64)->nullable()->unique());
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('subscription');
            $table->timestamps();
        });
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('event_key', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('messaging_campaigns')->nullOnDelete();
            $table->foreignId('messaging_log_id')->nullable()->constrained('messaging_logs')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('category', 32);
            $table->text('payload');
            $table->string('status', 20)->default('pending');
            $table->char('group_key', 64)->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('available_at')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('error_code')->nullable();
            $table->string('provider_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('push_subscriptions');
        Schema::table('notifications', fn (Blueprint $table) => $table->dropUnique(['event_key']));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn('event_key'));
        Schema::table('notification_preferences', fn (Blueprint $table) => $table->dropColumn(['marketing_email', 'marketing_whatsapp']));
    }
};

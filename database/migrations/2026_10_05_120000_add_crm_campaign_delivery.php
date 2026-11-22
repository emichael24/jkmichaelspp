<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_campaigns', function (Blueprint $table) {
            $table->string('body_format', 10)->default('text');
            $table->string('channel', 20)->default('email')->change();
        });
        Schema::table('messaging_logs', fn (Blueprint $table) => $table->string('channel', 20)->change());
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->boolean('marketing_email')->default(false);
            $table->boolean('marketing_whatsapp')->default(false);
        });
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
        });
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->constrained('messaging_campaigns')->nullOnDelete();
            $table->uuid('delivery_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Keep queued CRM messages intact: rollback must not delete correspondence.
        if (DB::table('notification_deliveries')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Archive CRM delivery records before rolling back this migration.');
        }
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropUnique(['delivery_id']);
            $table->dropColumn('delivery_id');
        });
        Schema::table('crm_contacts', fn (Blueprint $table) => $table->dropColumn(['marketing_email', 'marketing_whatsapp']));
        Schema::table('messaging_campaigns', fn (Blueprint $table) => $table->dropColumn('body_format'));
    }
};

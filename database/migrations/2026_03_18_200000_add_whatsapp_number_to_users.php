<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Separated from general `phone` so a user's SMS/call number can differ from WA
            $table->string('whatsapp_number', 30)->nullable()->after('phone');
            // Which WA outbound session the admin has pinned for this user (null = use platform default)
            $table->string('whatsapp_session', 100)->nullable()->after('whatsapp_number');
            // Pairing metadata
            $table->timestamp('whatsapp_paired_at')->nullable()->after('whatsapp_session');
            $table->foreignId('whatsapp_paired_by')->nullable()->constrained('users')->nullOnDelete()->after('whatsapp_paired_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_paired_by']);
            $table->dropColumn(['whatsapp_number', 'whatsapp_session', 'whatsapp_paired_at', 'whatsapp_paired_by']);
        });
    }
};

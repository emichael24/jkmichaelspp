<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->index();
            $t->uuid('correlation_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropIndex(['organization_id']);
            $t->dropIndex(['correlation_id']);
        });
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropColumn(['organization_id', 'correlation_id']));
    }
};

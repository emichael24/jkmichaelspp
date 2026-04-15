<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('unlock_type', ['immediate', 'date', 'days_after_enrollment'])
                ->default('immediate')
                ->after('is_downloadable');
            $table->timestamp('unlock_at')->nullable()->after('unlock_type');
            $table->unsignedInteger('unlock_days_after_enrollment')->nullable()->after('unlock_at');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'unlock_type',
                'unlock_at',
                'unlock_days_after_enrollment',
            ]);
        });
    }
};

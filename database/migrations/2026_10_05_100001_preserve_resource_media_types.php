<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', fn (Blueprint $table) => $table->string('file_type', 127)->nullable()->change());
    }

    public function down(): void
    {
        // Preserve complete MIME types on code rollback; shrinking loses data.
    }
};

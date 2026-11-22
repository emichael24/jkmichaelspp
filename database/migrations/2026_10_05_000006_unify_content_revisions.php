<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('revision_of_id')->nullable()->constrained('courses')->restrictOnDelete();
            $table->timestamp('superseded_at')->nullable();
        });
        Schema::table('content_approvals', function (Blueprint $table) {
            $table->char('snapshot_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_approvals', fn (Blueprint $table) => $table->dropColumn('snapshot_hash'));
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn('superseded_at');
        });
    }
};

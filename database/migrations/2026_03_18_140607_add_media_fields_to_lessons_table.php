<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_provider')->nullable()->after('video_url')->comment('s3, vimeo, youtube, mux');
            $table->string('scorm_path')->nullable()->after('slides_url');
            $table->string('scorm_version')->nullable()->comment('1.2, 2004');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['video_provider', 'scorm_path', 'scorm_version']);
        });
    }
};

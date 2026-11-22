<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lesson formats grow beyond media (quiz, assignment, case study); a string
        // avoids an enum rebuild each time a format is added.
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('content_type', 32)->default('mixed')->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('sequential_lessons')->default(false);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->json('prompts')->nullable();
            $table->unsignedInteger('passing_score')->nullable();
            $table->boolean('allow_file_upload')->default(true);
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->json('answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', fn (Blueprint $table) => $table->dropColumn('answers'));
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['lesson_id']);
            $table->dropUnique(['lesson_id']);
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['lesson_id', 'prompts', 'passing_score', 'allow_file_upload']);
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('sequential_lessons'));
    }
};

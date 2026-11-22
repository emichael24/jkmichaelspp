<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', fn (Blueprint $t) => $t->json('grading_config')->nullable());
        Schema::table('question_responses', fn (Blueprint $t) => $t->decimal('score_fraction', 5, 4)->nullable());
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->timestamp('paused_at')->nullable();
            $t->unsignedInteger('remaining_seconds')->nullable();
            $t->timestamp('learning_processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $t) => $t->dropColumn('grading_config'));
        Schema::table('question_responses', fn (Blueprint $t) => $t->dropColumn('score_fraction'));
        Schema::table('quiz_attempts', fn (Blueprint $t) => $t->dropColumn(['paused_at', 'remaining_seconds', 'learning_processed_at']));
    }
};

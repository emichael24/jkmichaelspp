<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Analytics ──────────────────────────────

        Schema::create('user_mastery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('subtopic')->nullable();
            $table->decimal('accuracy_pct', 5, 2)->default(0);
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->decimal('speed_score', 5, 2)->default(0);
            $table->decimal('consistency_score', 5, 2)->default(0);
            $table->decimal('improvement_rate', 5, 2)->default(0);
            $table->enum('mastery_level', ['novice', 'developing', 'proficient', 'expert'])->default('novice');
            $table->unsignedInteger('total_attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'domain', 'subtopic'], 'user_mastery_unique');
        });

        Schema::create('error_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->enum('pattern_type', [
                'concept_confusion',
                'careless_fast',
                'overthinking_slow',
                'confidence_mismatch',
                'qualifier_misread',
                'elimination_failure',
            ]);
            $table->string('domain')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->json('example_question_ids')->nullable();
            $table->text('suggested_fix')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });

        Schema::create('readiness_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('score_pct', 5, 2);
            $table->enum('confidence_band', ['low', 'medium', 'high']);
            $table->enum('pass_likelihood', ['unlikely', 'possible', 'likely', 'very_likely'])->nullable();
            $table->json('domain_breakdown');
            $table->json('weak_areas')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'course_id']);
        });

        Schema::create('daily_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('lessons_completed')->default(0);
            $table->unsignedInteger('quizzes_completed')->default(0);
            $table->unsignedInteger('questions_answered')->default(0);
            $table->unsignedInteger('questions_correct')->default(0);
            $table->unsignedInteger('time_spent_minutes')->default(0);
            $table->unsignedInteger('xp_earned')->default(0);
            $table->unsignedInteger('simulations_completed')->default(0);
            $table->unsignedInteger('flashcards_reviewed')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        // Gamification ────────────────────────────

        Schema::create('user_gamification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_xp')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('current_streak_days')->default(0);
            $table->unsignedInteger('longest_streak_days')->default(0);
            $table->unsignedTinyInteger('streak_protection_tokens')->default(1);
            $table->date('last_activity_date')->nullable();
            $table->boolean('rest_day_used_this_week')->default(false);
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 500)->nullable();
            $table->enum('category', ['learning', 'mastery', 'streak', 'social', 'internship', 'completion']);
            $table->json('criteria');
            $table->unsignedInteger('xp_reward')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('earned_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['daily', 'weekly', 'cohort', 'special']);
            $table->json('criteria');
            $table->unsignedInteger('xp_reward')->default(0);
            $table->foreignId('badge_reward_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('target');
            $table->enum('status', ['active', 'completed', 'expired'])->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('xp_levels', function (Blueprint $table) {
            $table->unsignedInteger('level')->primary();
            $table->unsignedInteger('xp_required');
            $table->string('title', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_levels');
        Schema::dropIfExists('user_missions');
        Schema::dropIfExists('missions');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_gamification');
        Schema::dropIfExists('daily_activity_logs');
        Schema::dropIfExists('readiness_scores');
        Schema::dropIfExists('error_patterns');
        Schema::dropIfExists('user_mastery');
    }
};

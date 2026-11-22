<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('subtopic')->nullable();
            $table->enum('type', [
                'multiple_choice', 'multiple_select', 'true_false',
                'scenario', 'situational_judgment', 'formula',
                'fill_blank', 'ordering', 'matching',
            ]);
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert']);
            $table->enum('bloom_level', [
                'remember', 'understand', 'apply', 'analyze', 'evaluate', 'create',
            ])->nullable();
            $table->text('stem');
            $table->json('stem_media')->nullable();
            $table->json('options');
            $table->json('correct_answer_ids');
            $table->text('explanation_correct');
            $table->json('explanation_incorrect')->nullable();
            $table->text('exam_tip')->nullable();
            $table->foreignId('linked_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->unsignedInteger('time_estimate_seconds')->default(60);
            $table->unsignedInteger('exposure_count')->default(0);
            $table->decimal('correct_rate', 5, 4)->default(0);
            $table->decimal('avg_time_seconds', 8, 2)->default(0);
            $table->decimal('discrimination_index', 5, 4)->nullable();
            $table->enum('status', ['draft', 'review', 'approved', 'published', 'archived'])->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_id');
            $table->index('domain');
            $table->index('difficulty');
            $table->index('type');
            $table->index('status');
            $table->index('bloom_level');
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'lesson_check', 'module_quiz', 'domain_practice', 'adaptive', 'custom',
            ]);
            $table->unsignedInteger('question_count');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedTinyInteger('passing_score_pct')->default(70);
            $table->boolean('is_randomized')->default(true);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['quiz_id', 'question_id']);
        });

        Schema::create('mock_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['full_length', 'mini_mock', 'domain_focused']);
            $table->unsignedInteger('question_count');
            $table->unsignedInteger('time_limit_minutes');
            $table->unsignedTinyInteger('passing_score_pct')->default(65);
            $table->json('domain_distribution');
            $table->json('difficulty_distribution')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_exam_simulation')->default(false);
            $table->boolean('allow_pause')->default(true);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('quizzable');
            $table->enum('status', ['in_progress', 'paused', 'completed', 'abandoned'])->default('in_progress');
            $table->decimal('score_pct', 5, 2)->nullable();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_count');
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->json('domain_scores')->nullable();
            $table->dateTime('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            // morphs() already creates an index on [quizzable_type, quizzable_id]
        });

        Schema::create('question_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('selected_answer_ids');
            $table->boolean('is_correct');
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->enum('confidence_level', ['low', 'medium', 'high'])->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('question_id');
            $table->index('quiz_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_responses');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('mock_exams');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('questions');
    }
};

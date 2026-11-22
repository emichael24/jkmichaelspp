<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('NULL = system-generated');
            $table->text('front');
            $table->text('back');
            $table->string('domain')->nullable();
            $table->json('tags')->nullable();
            $table->enum('source', ['lesson_term', 'wrong_answer', 'user_created'])->default('user_created');
            $table->timestamps();

            $table->index(['course_id', 'domain']);
        });

        Schema::create('spaced_repetition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reviewable'); // Flashcard or Question
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->unsignedInteger('interval_days')->default(1);
            $table->unsignedInteger('repetitions')->default(0);
            $table->dateTime('next_review_at');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->enum('status', ['new', 'learning', 'review', 'graduated'])->default('new');
            $table->timestamps();

            $table->index(['user_id', 'next_review_at']);
            // morphs() already creates an index on [reviewable_type, reviewable_id]
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaced_repetition_items');
        Schema::dropIfExists('flashcards');
    }
};

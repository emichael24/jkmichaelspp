<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ───────── cohorts ───────── */
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('facilitator_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('max_learners')->default(50);
            $table->enum('status', ['draft', 'active', 'completed', 'archived'])->default('draft');
            $table->json('settings')->nullable(); // {allow_self_enroll, leaderboard_visible}
            $table->timestamps();
        });

        /* ───────── cohort_members ───────── */
        Schema::create('cohort_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['learner', 'co_facilitator'])->default('learner');
            $table->timestamp('joined_at')->useCurrent();
            $table->enum('status', ['active', 'completed', 'dropped', 'removed'])->default('active');
            $table->timestamps();

            $table->unique(['cohort_id', 'user_id']);
        });

        /* ───────── cohort_weekly_plans ───────── */
        Schema::create('cohort_weekly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('week_number');
            $table->string('title')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        /* ───────── cohort_plan_items ───────── */
        Schema::create('cohort_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_id')->constrained('cohort_weekly_plans')->cascadeOnDelete();
            $table->string('itemable_type'); // Lesson, Quiz, MockExam, Assignment
            $table->unsignedBigInteger('itemable_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['itemable_type', 'itemable_id']);
        });

        /* ───────── announcements ───────── */
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        /* ───────── interventions ───────── */
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facilitator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['nudge', 'coaching', 'escalation', 'plan_adjustment', 'rest_day']);
            $table->text('reason');
            $table->text('action_taken');
            $table->enum('status', ['pending', 'sent', 'acknowledged', 'resolved'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('cohort_plan_items');
        Schema::dropIfExists('cohort_weekly_plans');
        Schema::dropIfExists('cohort_members');
        Schema::dropIfExists('cohorts');
    }
};

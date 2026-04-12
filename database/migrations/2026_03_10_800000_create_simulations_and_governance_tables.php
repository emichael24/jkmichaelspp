<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Simulations ─────────────────────────────────────────────
        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['branching_scenario', 'role_play', 'case_study_lab', 'decision_room']);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedInteger('estimated_minutes')->default(15);
            $table->text('context_narrative');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // ── Simulation Nodes ────────────────────────────────────────
        Schema::create('simulation_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_id')->constrained('simulations')->cascadeOnDelete();
            $table->enum('type', ['story', 'decision', 'consequence', 'feedback', 'end']);
            $table->text('content');
            $table->string('media_url', 500)->nullable();
            $table->boolean('is_start')->default(false);
            $table->boolean('is_best_practice')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Simulation Choices ──────────────────────────────────────
        Schema::create('simulation_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('simulation_nodes')->cascadeOnDelete();
            $table->foreignId('next_node_id')->nullable()->constrained('simulation_nodes')->nullOnDelete();
            $table->string('label', 500);
            $table->json('score_impact')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Simulation Attempts ─────────────────────────────────────
        Schema::create('simulation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('simulation_id')->constrained('simulations')->cascadeOnDelete();
            $table->json('path_taken')->nullable();
            $table->json('choices_made')->nullable();
            $table->json('scores')->nullable();
            $table->decimal('total_score_pct', 5, 2)->nullable();
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->unsignedBigInteger('current_node_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'simulation_id']);
        });

        // ── Content Versions ────────────────────────────────────────
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');
            $table->unsignedInteger('version_number');
            $table->json('data');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['versionable_type', 'versionable_id']);
        });

        // ── Content Approvals ───────────────────────────────────────
        Schema::create('content_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'changes_requested'])->default('pending');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── Question Flags ──────────────────────────────────────────
        Schema::create('question_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('flagged_by')->constrained('users')->cascadeOnDelete();
            $table->enum('reason', ['incorrect_answer', 'unclear_stem', 'outdated', 'typo', 'duplicate', 'other']);
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_review', 'resolved', 'dismissed'])->default('open');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_flags');
        Schema::dropIfExists('content_approvals');
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('simulation_attempts');
        Schema::dropIfExists('simulation_choices');
        Schema::dropIfExists('simulation_nodes');
        Schema::dropIfExists('simulations');
    }
};

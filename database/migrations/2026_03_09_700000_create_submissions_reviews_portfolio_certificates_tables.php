<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Internship Submissions ──────────────────────────────────
        Schema::create('internship_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('internship_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('submission_number')->default(1);
            $table->text('content')->nullable();
            $table->json('file_urls')->nullable();
            $table->unsignedInteger('time_log_minutes')->nullable();
            $table->enum('status', ['submitted', 'in_review', 'approved', 'revision_requested'])->default('submitted');
            $table->dateTime('submitted_at');
            $table->timestamps();
        });

        // ── Internship Evidence ─────────────────────────────────────
        Schema::create('internship_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('internship_submissions')->cascadeOnDelete();
            $table->enum('type', ['screenshot', 'photo', 'screen_recording', 'document', 'other']);
            $table->string('file_url', 500);
            $table->string('file_type', 10)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->string('caption', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Sponsor Reviews ─────────────────────────────────────────
        Schema::create('sponsor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('internship_submissions')->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained('users')->cascadeOnDelete();
            $table->enum('decision', ['approved', 'revision_requested']);
            $table->json('rubric_scores')->nullable();
            $table->decimal('overall_score', 3, 1)->nullable();
            $table->text('feedback')->nullable();
            $table->string('feedback_audio_url', 500)->nullable();
            $table->json('risk_flags')->nullable();
            $table->dateTime('reviewed_at');
            $table->timestamps();
        });

        // ── Sponsor Scorecards ──────────────────────────────────────
        Schema::create('sponsor_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('internship_enrollments')->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained('users')->cascadeOnDelete();
            $table->enum('period_type', ['weekly', 'monthly']);
            $table->unsignedInteger('period_number');
            $table->json('scores');
            $table->decimal('overall_score', 3, 1);
            $table->text('comments')->nullable();
            $table->json('improvement_actions')->nullable();
            $table->boolean('ready_for_next')->default(false);
            $table->timestamps();
        });

        // ── Certificates ────────────────────────────────────────────
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('certifiable'); // Course | InternshipTrack
            $table->string('title');
            $table->string('certificate_number', 50)->unique();
            $table->dateTime('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('pdf_url', 500);
            $table->string('qr_code_url', 500);
            $table->json('metadata')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });

        // ── Portfolio Items ─────────────────────────────────────────
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('internship_enrollments')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['deliverable', 'presentation', 'report', 'case_study', 'reflection']);
            $table->string('file_url', 500);
            $table->json('skills_demonstrated')->nullable();
            $table->decimal('sponsor_score', 3, 1)->nullable();
            $table->boolean('is_public')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('sponsor_scorecards');
        Schema::dropIfExists('sponsor_reviews');
        Schema::dropIfExists('internship_evidence');
        Schema::dropIfExists('internship_submissions');
    }
};

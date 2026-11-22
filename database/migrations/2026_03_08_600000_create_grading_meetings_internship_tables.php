<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Assignments ──────────────────────────────────────────
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('instructions')->nullable();
            $table->json('rubric')->nullable();           // [{criterion, description, max_score}]
            $table->unsignedInteger('max_score')->default(100);
            $table->timestamp('due_date')->nullable();
            $table->boolean('allow_late')->default(false);
            $table->unsignedInteger('max_resubmissions')->default(1);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('submission_number')->default(1);
            $table->text('content')->nullable();
            $table->json('file_urls')->nullable();        // [S3 paths]
            $table->enum('status', ['submitted', 'in_review', 'graded', 'returned', 'resubmitted'])->default('submitted');
            $table->unsignedInteger('score')->nullable();
            $table->json('rubric_scores')->nullable();    // {criterion: score}
            $table->text('feedback')->nullable();
            $table->string('feedback_audio_url', 500)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->index(['assignment_id', 'user_id']);
        });

        // ── Meetings ─────────────────────────────────────────────
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('meetable');            // Cohort, InternshipEnrollment
            $table->enum('type', ['live_session', 'coaching', 'standup', 'review', 'webinar', 'bootcamp']);
            $table->enum('platform', ['zoom', 'teams', 'google_meet', 'other'])->default('zoom');
            $table->string('meeting_url', 500)->nullable();
            $table->string('meeting_id_external')->nullable();
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->text('agenda')->nullable();
            $table->text('notes')->nullable();
            $table->json('action_items')->nullable();     // [{task, assignee_id, due_date}]
            $table->string('replay_url', 500)->nullable();
            $table->longText('transcript')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('rsvp', ['pending', 'accepted', 'declined', 'tentative'])->default('pending');
            $table->boolean('attended')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        // ── Internship Tracks ────────────────────────────────────
        Schema::create('internship_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_weeks');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->foreignId('orientation_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->json('tools_required')->nullable();   // [tool names]
            $table->json('templates')->nullable();        // [{name, file_url}]
            $table->text('expectations')->nullable();
            $table->json('skills_covered')->nullable();   // [skill tags]
            $table->unsignedInteger('max_participants')->default(20);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('internship_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('internship_tracks')->cascadeOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_title')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['enrolled', 'active', 'completed', 'withdrawn', 'failed'])->default('enrolled');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'track_id']);
        });

        Schema::create('internship_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained('internship_tracks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('week_number');
            $table->json('deliverables')->nullable();     // [expected outputs]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('internship_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('internship_enrollments')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('internship_milestones')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['daily', 'weekly', 'monthly']);
            $table->enum('status', [
                'backlog', 'this_week', 'today', 'in_progress',
                'in_review', 'approved', 'needs_revision', 'done',
            ])->default('backlog');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_tasks');
        Schema::dropIfExists('internship_milestones');
        Schema::dropIfExists('internship_enrollments');
        Schema::dropIfExists('internship_tracks');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};

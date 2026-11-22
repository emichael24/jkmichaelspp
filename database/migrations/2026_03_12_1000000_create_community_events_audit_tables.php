<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forums
        Schema::create('forums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_general')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id']);
            $table->index(['cohort_id']);
        });

        // Forum Threads
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('reply_count')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['forum_id', 'is_pinned', 'created_at']);
            $table->index(['user_id']);
        });

        // Forum Replies
        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('parent_id')->nullable()->constrained('forum_replies')->nullOnDelete();
            $table->boolean('is_accepted_answer')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'created_at']);
        });

        // Direct Messages
        Schema::create('direct_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('attachment_url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sender_id', 'recipient_id', 'created_at']);
            $table->index(['recipient_id', 'read_at']);
        });

        // Study Groups
        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('max_members')->default(10);
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['course_id']);
        });

        // Study Group Members
        Schema::create('study_group_members', function (Blueprint $table) {
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['study_group_id', 'user_id']);
        });

        // Events
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['webinar', 'bootcamp', 'industry_session', 'workshop']);
            $table->string('thumbnail', 500)->nullable();
            $table->string('speaker_name')->nullable();
            $table->text('speaker_bio')->nullable();
            $table->enum('platform', ['zoom', 'teams', 'google_meet', 'other'])->default('zoom');
            $table->string('meeting_url', 500)->nullable();
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedInteger('max_registrations')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('replay_url', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['scheduled_at', 'is_published']);
            $table->index(['type']);
        });

        // Event Registrations
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('attended')->default(false);
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_id', 'user_id']);
        });

        // Career Pathways
        Schema::create('career_pathways', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('skills_required');
            $table->timestamps();
        });

        // Career Pathway Courses (pivot)
        Schema::create('career_pathway_courses', function (Blueprint $table) {
            $table->foreignId('pathway_id')->constrained('career_pathways')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);

            $table->primary(['pathway_id', 'course_id']);
        });

        // Course Ratings
        Schema::create('course_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'rating']);
        });

        // Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id']);
            $table->index(['action']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('course_ratings');
        Schema::dropIfExists('career_pathway_courses');
        Schema::dropIfExists('career_pathways');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('study_group_members');
        Schema::dropIfExists('study_groups');
        Schema::dropIfExists('direct_messages');
        Schema::dropIfExists('forum_replies');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forums');
    }
};

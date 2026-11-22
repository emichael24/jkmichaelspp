<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('preview_video', 500)->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->enum('type', ['self_paced', 'live', 'certified', 'bootcamp'])->default('self_paced');
            $table->decimal('duration_hours', 5, 1)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->json('exam_blueprint')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_enrollments')->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('is_published');
            $table->index('level');
            $table->index('type');
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->enum('content_type', ['text', 'video', 'audio', 'pdf', 'slides', 'mixed'])->default('mixed');
            $table->longText('text_content')->nullable();
            $table->text('summary')->nullable();
            $table->json('key_terms')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->unsignedInteger('video_duration_seconds')->nullable();
            $table->longText('video_transcript')->nullable();
            $table->json('video_chapters')->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->unsignedInteger('audio_duration_seconds')->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->string('slides_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedInteger('duration_minutes')->default(10);
            $table->boolean('is_free_preview')->default(false);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_downloadable')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['template', 'checklist', 'cheat_sheet', 'formula_sheet', 'case_study', 'playbook']);
            $table->text('description')->nullable();
            $table->string('file_url', 500);
            $table->string('file_type', 127)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('categories');
    }
};

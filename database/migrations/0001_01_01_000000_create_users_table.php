<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Organizations must be created before users (FK dependency)
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo', 500)->nullable();
            $table->string('industry', 100)->nullable();
            $table->enum('size', ['1-50', '51-200', '201-1000', '1000+'])->nullable();
            $table->string('contact_email');
            $table->string('contact_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('country', 3)->default('NGA');
            $table->json('settings')->nullable();
            $table->enum('subscription_plan', ['free', 'pro', 'enterprise'])->default('free');
            $table->unsignedInteger('max_seats')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('avatar', 500)->nullable();
            $table->string('timezone', 50)->default('Africa/Lagos');
            $table->string('locale', 10)->default('en');
            $table->enum('goal', ['certification', 'skill', 'internship'])->nullable();
            $table->date('exam_date')->nullable();
            $table->unsignedTinyInteger('daily_minutes')->default(15);
            $table->enum('learning_preference', ['read', 'watch', 'listen'])->default('read');
            $table->enum('experience_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->json('accessibility_prefs')->nullable();
            $table->json('notification_prefs')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['department_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
    }
};

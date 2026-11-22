<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchase_scope', 20)->default('individual')->index();
            $table->json('billing_details')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('licensed_course_id')->nullable()->constrained('courses')->restrictOnDelete();
            $table->string('product_name')->nullable();
        });
        Schema::create('organization_course_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'course_id']);
        });
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('organization_course_license_id')->nullable()->constrained('organization_course_licenses')->restrictOnDelete();
        });
        Schema::create('organization_course_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('organization_course_licenses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['license_id', 'user_id'], 'org_course_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_course_assignments');
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_course_license_id'));
        Schema::dropIfExists('organization_course_licenses');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('licensed_course_id');
            $table->dropColumn('product_name');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['purchase_scope']);
            $table->dropColumn(['purchase_scope', 'billing_details']);
        });
    }
};

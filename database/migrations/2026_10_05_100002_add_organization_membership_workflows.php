<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', fn (Blueprint $table) => $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete());
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->default('pending');
            $table->dateTime('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'expires_at'], 'organization_invitation_seats');
            $table->index(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
        Schema::table('departments', fn (Blueprint $table) => $table->dropConstrainedForeignId('manager_id'));
    }
};

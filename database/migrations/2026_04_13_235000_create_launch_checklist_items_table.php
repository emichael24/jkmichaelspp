<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('gate'); // beta, launch
            $table->string('area'); // product, qa, payments, ops
            $table->string('title');
            $table->enum('status', ['pending', 'in_progress', 'complete', 'blocked'])->default('pending');
            $table->string('owner_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('launch_checklist_items')->insert([
            [
                'gate' => 'beta',
                'area' => 'product',
                'title' => 'Registration, login, onboarding, enrollment flows verified end-to-end',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'beta',
                'area' => 'learning',
                'title' => 'Lesson completion, quiz attempts, mock attempts, and certificates verified',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'beta',
                'area' => 'commerce',
                'title' => 'Storefront checkout and order lifecycle verified',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'beta',
                'area' => 'qa',
                'title' => 'Critical-path automated tests green in CI',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'launch',
                'area' => 'payments',
                'title' => 'Payment webhooks idempotent and replay-safe',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'launch',
                'area' => 'security',
                'title' => 'Role access and permissions verified for all personas',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'launch',
                'area' => 'ops',
                'title' => 'Backups and restore procedures tested',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'launch',
                'area' => 'ops',
                'title' => 'Monitoring and alerting configured for production',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gate' => 'launch',
                'area' => 'uat',
                'title' => 'UAT signoff for learner, facilitator, sponsor, SME, admin journeys',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('launch_checklist_items');
    }
};

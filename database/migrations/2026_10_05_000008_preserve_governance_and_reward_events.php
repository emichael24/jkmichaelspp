<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('event_key', 64)->unique();
            $table->string('action', 50);
            $table->unsignedInteger('xp');
            $table->dateTime('created_at');
        });
        // Old approval screens did not create an independent review record.
        // Return those drafts for explicit resubmission; never invent a reviewer.
        DB::table('courses')->where('approval_status', 'pending_review')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('content_approvals')
                ->whereColumn('approvable_id', 'courses.id')->where('approvable_type', 'App\\Models\\Course')->where('status', 'pending'))
            ->update(['approval_status' => 'draft', 'submitted_for_review_at' => null,
                'rejection_reason' => 'Please resubmit this draft using the updated review workflow. The previous submission did not have a review record.']);
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_events');
    }
};

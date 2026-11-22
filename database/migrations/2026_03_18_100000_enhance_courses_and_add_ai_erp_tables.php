<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Enhance courses table ───────────────────────────────────────────
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('created_by_role', ['admin', 'facilitator'])->default('admin')->after('author_id');
            $table->enum('approval_status', ['draft', 'pending_review', 'approved', 'rejected'])->default('draft')->after('created_by_role');
            $table->timestamp('submitted_for_review_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('submitted_for_review_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->index('approval_status');
        });

        // ─── 2. AI Jobs ─────────────────────────────────────────────────────────
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('job_type', [
                'chat', 'lesson_summary', 'quiz_generation', 'content_repurpose',
                'tts', 'avatar_video', 'transcription', 'embeddings', 'insights',
            ]);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('input_payload')->nullable();
            $table->longText('output')->nullable();
            $table->string('output_file_path', 500)->nullable(); // for video/audio results
            $table->text('error_message')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedSmallInteger('processing_seconds')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'job_type']);
            $table->index('status');
        });

        // ─── 3. AI Chat Sessions ────────────────────────────────────────────────
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New Chat');
            $table->enum('context_type', ['general', 'lesson', 'course', 'exam_prep'])->default('general');
            $table->unsignedBigInteger('context_id')->nullable(); // lesson_id / course_id
            $table->json('messages'); // [{role, content, ts}]
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        // ─── 4. Voice Clones ────────────────────────────────────────────────────
        Schema::create('voice_clones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('voice_id'); // returned by TTS service
            $table->string('sample_audio_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── 5. CRM Contacts ────────────────────────────────────────────────────
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // linked learner account
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->enum('type', ['lead', 'prospect', 'student', 'alumni', 'partner'])->default('lead');
            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'lost', 'inactive'])->default('new');
            $table->string('source')->nullable(); // organic, referral, ads, event…
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('assigned_to');
        });

        // ─── 6. CRM Pipelines ───────────────────────────────────────────────────
        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('stages'); // [{name, order, probability_pct}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── 7. CRM Deals ───────────────────────────────────────────────────────
        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained('crm_pipelines')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('stage');
            $table->enum('status', ['open', 'won', 'lost'])->default('open');
            $table->text('notes')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('stage');
        });

        // ─── 8. CRM Activities ──────────────────────────────────────────────────
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['call', 'email', 'whatsapp', 'meeting', 'note', 'task']);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('direction', ['inbound', 'outbound'])->nullable();
            $table->enum('outcome', ['no_answer', 'left_voicemail', 'interested', 'not_interested', 'follow_up', 'completed'])->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'type']);
        });

        // ─── 9. Messaging Campaigns ─────────────────────────────────────────────
        Schema::create('messaging_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->enum('channel', ['email', 'whatsapp'])->default('email');
            $table->enum('type', ['broadcast', 'sequence', 'trigger'])->default('broadcast');
            $table->string('subject')->nullable(); // email subject line
            $table->longText('body'); // email HTML or WA text
            $table->string('media_url', 500)->nullable(); // WA media attachment
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled'])->default('draft');
            $table->json('audience_filter')->nullable(); // {role, course_id, cohort_id, tags…}
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('channel');
        });

        // ─── 10. Messaging Logs ─────────────────────────────────────────────────
        Schema::create('messaging_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('messaging_campaigns')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('recipient'); // email address or phone
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('external_message_id')->nullable(); // WA messageId
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'bounced', 'opened']);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('recipient');
        });

        // ─── 11. WhatsApp Sessions ──────────────────────────────────────────────
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique(); // returned by FlexiChurch API
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->enum('status', ['CONNECTED', 'WAITING_FOR_QR', 'CONNECTING', 'DISCONNECTED', 'ERROR'])->default('DISCONNECTED');
            $table->string('webhook_url', 500)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        // ─── 12. Paystack Transactions ──────────────────────────────────────────
        Schema::create('paystack_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('access_code')->nullable();
            $table->string('authorization_url', 500)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->enum('status', ['initiated', 'pending', 'success', 'failed', 'abandoned'])->default('initiated');
            $table->json('metadata')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('channel')->nullable(); // card, bank, ussd, qr, mobile_money, bank_transfer
            $table->string('ip_address')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index(['user_id', 'status']);
        });

        // ─── 13. AI Lesson Summaries ────────────────────────────────────────────
        Schema::create('ai_lesson_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('summary_type', ['brief', 'detailed', 'key_points', 'study_guide']);
            $table->longText('content');
            $table->string('language', 10)->default('en');
            $table->unsignedInteger('tokens_used')->default(0);
            $table->timestamps();

            $table->unique(['lesson_id', 'summary_type', 'language']);
        });

        // ─── 14. AI Generated Quizzes ───────────────────────────────────────────
        Schema::create('ai_generated_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->json('questions'); // [{question, options:[...], correct_index, explanation}]
            $table->unsignedTinyInteger('question_count')->default(5);
            $table->boolean('is_promoted')->default(false); // promoted = added to official quiz bank
            $table->timestamps();
        });

        // ─── 15. CRM Follow-up Sequences ────────────────────────────────────────
        Schema::create('crm_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger', ['contact_created', 'stage_changed', 'manual']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_sequences')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_offset')->default(0); // days after trigger
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_sequences')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->enum('status', ['active', 'completed', 'paused', 'unsubscribed'])->default('active');
            $table->timestamp('next_message_at')->nullable();
            $table->timestamps();

            $table->unique(['sequence_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sequence_enrollments');
        Schema::dropIfExists('crm_sequence_steps');
        Schema::dropIfExists('crm_sequences');
        Schema::dropIfExists('ai_generated_quizzes');
        Schema::dropIfExists('ai_lesson_summaries');
        Schema::dropIfExists('paystack_transactions');
        Schema::dropIfExists('whatsapp_sessions');
        Schema::dropIfExists('messaging_logs');
        Schema::dropIfExists('messaging_campaigns');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('voice_clones');
        Schema::dropIfExists('ai_chat_sessions');
        Schema::dropIfExists('ai_jobs');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['created_by_role', 'approval_status', 'submitted_for_review_at', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};

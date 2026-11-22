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
        // Add foreign keys and pipeline relationships missing previously
        Schema::table('crm_deals', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deals', 'pipeline_id')) {
                $table->foreignId('pipeline_id')->nullable()->constrained('crm_pipelines')->nullOnDelete();
            }

            // Re-adding due to some previous incomplete migrations, if any.
            if (! Schema::hasColumn('crm_deals', 'expected_close_date')) {
                $table->date('expected_close_date')->nullable();
            }
            if (! Schema::hasColumn('crm_deals', 'probability')) {
                $table->integer('probability')->default(50);
            }
            if (! Schema::hasColumn('crm_deals', 'lost_reason')) {
                $table->string('lost_reason')->nullable();
            }
        });

        if (! Schema::hasTable('crm_deal_stage_history')) {
            Schema::create('crm_deal_stage_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
                // Storing as JSON to keep old states, previous stage, etc
                $table->string('previous_stage')->nullable();
                $table->string('new_stage');
                $table->foreignId('moved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moved_at')->useCurrent();
                $table->integer('time_in_stage_seconds')->nullable();
            });
        }

        // Optionally organization/company concept for B2B CRM
        if (! Schema::hasTable('crm_organizations')) {
            Schema::create('crm_organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('domain')->nullable();
                $table->string('industry')->nullable();
                $table->integer('employee_count')->nullable();
                $table->string('annual_revenue')->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('crm_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_contacts', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_contacts', 'job_title')) {
                $table->string('job_title')->nullable();
            }
            if (! Schema::hasColumn('crm_contacts', 'is_primary_decision_maker')) {
                $table->boolean('is_primary_decision_maker')->default(false);
            }
        });

        Schema::table('crm_deals', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deals', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->dropForeign(['pipeline_id']);
            $table->dropColumn('pipeline_id');
            $table->dropColumn(['expected_close_date', 'probability', 'lost_reason']);
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'job_title', 'is_primary_decision_maker']);
        });

        Schema::dropIfExists('crm_organizations');
        Schema::dropIfExists('crm_deal_stage_history');
    }
};

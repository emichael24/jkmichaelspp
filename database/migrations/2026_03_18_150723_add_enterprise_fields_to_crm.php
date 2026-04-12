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
        Schema::table('crm_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_contacts', 'lead_score')) {
                $table->integer('lead_score')->default(0)->index();
            }
            if (!Schema::hasColumn('crm_contacts', 'engagement_level')) {
                $table->string('engagement_level')->nullable(); // Cold, Warm, Hot
            }
            if (!Schema::hasColumn('crm_contacts', 'lifecycle_stage')) {
                $table->string('lifecycle_stage')->default('subscriber'); // subscriber, lead, mql, sql, opportunity, customer, evangelist
            }
        });

        Schema::table('crm_deals', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_deals', 'weighted_value')) {
                $table->decimal('weighted_value', 15, 2)->default(0.00)->after('value'); // value * probability
            }
            if (!Schema::hasColumn('crm_deals', 'win_reason')) {
                $table->text('win_reason')->nullable()->after('lost_reason');
            }
            if (!Schema::hasColumn('crm_deals', 'competitors')) {
                $table->string('competitors')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['lead_score', 'engagement_level', 'lifecycle_stage']);
        });

        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropColumn(['weighted_value', 'win_reason', 'competitors']);
        });
    }
};

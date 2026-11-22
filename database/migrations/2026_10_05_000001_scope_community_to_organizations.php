<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['forums', 'study_groups'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
        DB::table('study_groups')->orderBy('id')->chunkById(200, function ($groups) {
            foreach ($groups as $group) {
                DB::table('study_groups')->where('id', $group->id)->update([
                    'organization_id' => DB::table('users')->where('id', $group->created_by)->value('organization_id'),
                ]);
            }
        });
        DB::table('forums')->whereNotNull('cohort_id')->orderBy('id')->chunkById(200, function ($forums) {
            foreach ($forums as $forum) {
                DB::table('forums')->where('id', $forum->id)->update([
                    'organization_id' => DB::table('cohorts')->where('id', $forum->cohort_id)->value('organization_id'),
                ]);
            }
        });
    }

    public function down(): void
    {
        foreach (['forums', 'study_groups'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        }
    }
};

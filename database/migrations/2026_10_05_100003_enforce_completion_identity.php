<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'certificates' => ['user_id', 'certifiable_type', 'certifiable_id'],
            'internship_submissions' => ['task_id', 'user_id', 'submission_number'],
        ] as $table => $columns) {
            $duplicates = DB::table($table)->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1');
            foreach ($columns as $column) {
                $duplicates->whereNotNull($column);
            }
            if ($duplicates->exists()) {
                throw new RuntimeException("Resolve duplicate {$table} identities before migration. Existing records have been preserved.");
            }
        }
        Schema::table('certificates', fn (Blueprint $table) => $table->unique(['user_id', 'certifiable_type', 'certifiable_id'], 'certificate_earned_identity'));
        Schema::table('internship_submissions', fn (Blueprint $table) => $table->unique(['task_id', 'user_id', 'submission_number'], 'internship_submission_version'));
        Schema::table('internship_tracks', fn (Blueprint $table) => $table->json('review_rubric')->nullable());
    }

    public function down(): void
    {
        // MariaDB can use the compound unique indexes to support these foreign keys.
        // Restore independent indexes before removing the unique constraints.
        if (! Schema::hasIndex('internship_submissions', 'internship_task_lookup')) {
            Schema::table('internship_submissions', fn (Blueprint $table) => $table->index('task_id', 'internship_task_lookup'));
        }
        if (! Schema::hasIndex('certificates', 'certificate_user_lookup')) {
            Schema::table('certificates', fn (Blueprint $table) => $table->index('user_id', 'certificate_user_lookup'));
        }
        Schema::table('internship_tracks', fn (Blueprint $table) => $table->dropColumn('review_rubric'));
        Schema::table('internship_submissions', fn (Blueprint $table) => $table->dropUnique('internship_submission_version'));
        Schema::table('certificates', fn (Blueprint $table) => $table->dropUnique('certificate_earned_identity'));
    }
};

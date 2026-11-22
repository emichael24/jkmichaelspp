<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $deliveryTypes = ['self_paced', 'live', 'certified', 'bootcamp'];
        // The original authoring form stored pricing/audience labels in the
        // delivery-type field. Preserve that evidence without changing pricing.
        $commercialTypes = ['free', 'premium', 'enterprise'];
        if (DB::table('courses')->whereNotIn('type', [...$deliveryTypes, ...$commercialTypes])->orWhereNull('type')->exists()) {
            throw new RuntimeException('Unknown course classification requires review before migration.');
        }
        if (! Schema::hasColumn('courses', 'legacy_commercial_type')) {
            Schema::table('courses', fn (Blueprint $table) => $table->string('legacy_commercial_type', 32)->nullable());
        }
        DB::transaction(function () use ($commercialTypes) {
            foreach ($commercialTypes as $type) {
                if (DB::table('courses')->where('type', $type)->whereNotNull('legacy_commercial_type')->where('legacy_commercial_type', '<>', $type)->exists()) {
                    throw new RuntimeException('Conflicting legacy course classification requires review.');
                }
                DB::table('courses')->where('type', $type)->update(['legacy_commercial_type' => $type, 'type' => 'self_paced']);
            }
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE courses MODIFY type ENUM('self_paced','live','certified','bootcamp') NOT NULL DEFAULT 'self_paced'");
        }
    }

    public function down(): void
    {
        // Keep the preserved classification during code rollback. Restoring old
        // invalid enum values would break the canonical schema and new authors.
    }
};

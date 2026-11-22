<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            // Keep historical student/alumni values; application validation owns the vocabulary.
            $table->string('type', 30)->default('lead')->change();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 100)->nullable();
        });
        // Serialize bounded CRM writes so two administrators cannot import the same email.
        Schema::create('crm_contact_write_locks', fn (Blueprint $table) => $table->unsignedTinyInteger('id')->primary());
        DB::table('crm_contact_write_locks')->insert(['id' => 1]);
        Schema::create('crm_contact_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('row_count');
            $table->string('content_hash', 64);
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('crm_contacts')->where('type', 'customer')->exists()) {
            throw new RuntimeException('Customer contacts exist. Preserve them before reverting this migration.');
        }
        Schema::dropIfExists('crm_contact_imports');
        Schema::dropIfExists('crm_contact_write_locks');
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->enum('type', ['lead', 'prospect', 'student', 'alumni', 'partner'])->default('lead')->change();
            $table->dropColumn(['address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country']);
        });
    }
};

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
            $table->string('email_normalized')->nullable()->index();
        });
        DB::table('crm_contacts')->whereNotNull('email')->update(['email_normalized' => DB::raw('LOWER(TRIM(email))')]);
        Schema::create('crm_contact_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('content_hash', 64);
            $table->string('default_type', 30);
            $table->json('headers');
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->unsignedInteger('record_number')->default(1);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->string('error_message')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('crm_contact_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('import_id')->constrained('crm_contact_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('record_number');
            $table->string('kind', 30);
            $table->text('message');
            $table->unique(['import_id', 'record_number']);
        });
    }

    public function down(): void
    {
        if (DB::table('crm_contact_import_batches')->exists()) {
            throw new RuntimeException('Preserve contact import checkpoints and results before reverting.');
        }
        Schema::dropIfExists('crm_contact_import_errors');
        Schema::dropIfExists('crm_contact_import_batches');
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropIndex(['email_normalized']);
            $table->dropColumn('email_normalized');
        });
    }
};

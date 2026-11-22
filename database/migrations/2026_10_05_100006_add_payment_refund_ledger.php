<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 30)->default('requested')->index();
            $table->string('provider_refund_id')->nullable()->unique();
            $table->text('reason');
            $table->json('receipt')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reconciled_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('source_order_id')->nullable()->constrained('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_order_id'));
        Schema::dropIfExists('payment_refunds');
    }
};

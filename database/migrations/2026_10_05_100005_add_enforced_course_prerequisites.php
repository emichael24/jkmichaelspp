<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('courses')->restrictOnDelete();
            $table->primary(['course_id', 'prerequisite_id']);
        });
        Schema::create('learning_rule_locks', fn (Blueprint $table) => $table->unsignedTinyInteger('id')->primary());
        DB::table('learning_rule_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('learning_rule_locks');
    }
};

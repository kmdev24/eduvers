<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('track_strand_id')->nullable()->constrained()->nullOnDelete(); // null = core subject
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['grade_level_id', 'track_strand_id', 'academic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};

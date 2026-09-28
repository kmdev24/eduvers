<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which sections a quiz is published to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();

            $table->unique(['quiz_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_section');
    }
};

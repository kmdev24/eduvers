<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('track_strand_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['name', 'grade_level_id', 'track_strand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};

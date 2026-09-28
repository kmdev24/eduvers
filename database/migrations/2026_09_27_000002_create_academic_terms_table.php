<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // e.g. "1st Term"
            $table->unsignedTinyInteger('term_number');     // SHS: 1–3
            $table->string('academic_year', 9)->nullable(); // e.g. "2026-2027"
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();

            $table->unique(['academic_year', 'term_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};

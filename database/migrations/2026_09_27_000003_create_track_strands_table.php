<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_strands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // e.g. "HRS", "ICT", "STEM"
            $table->enum('category', ['techpro', 'academic', 'college'])->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_strands');
    }
};

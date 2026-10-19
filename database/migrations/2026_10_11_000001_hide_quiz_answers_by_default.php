<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quizzes used to show the correct answers to a student as soon as they submitted,
 * which is how answers reach the classmates who have not taken it yet. Hiding them
 * is now the default; a teacher can still show them per quiz, which is the right
 * way round because revealing is the choice that carries a cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('reveal_answers')->default(false)->change();
        });

        // Existing quizzes were never a deliberate choice - they inherited the old default
        DB::table('quizzes')->update(['reveal_answers' => false]);
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('reveal_answers')->default(true)->change();
        });

        DB::table('quizzes')->update(['reveal_answers' => true]);
    }
};

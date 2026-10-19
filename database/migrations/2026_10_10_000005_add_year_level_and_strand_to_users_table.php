<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student's year level and strand/course now belong to the student, not only to their
 * section. Until now both were read off the section, so a student admitted into "1st Year
 * BSIT" but not yet placed in a section had no year level at all — and nothing stopped
 * them being enrolled into a Grade 11 section by mistake.
 *
 * With a section, these stay in step with it; without one, they stand on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('grade_level_id')->nullable()->after('section_id')->constrained()->nullOnDelete();
            $table->foreignId('track_strand_id')->nullable()->after('grade_level_id')->constrained()->nullOnDelete();
        });

        // Students already in a section inherit that section's level and strand.
        // One statement per section: a school has a handful, and it runs anywhere.
        // Plain tables, not models: a later change to a model must not break this on a fresh install.
        DB::table('sections')->orderBy('id')->each(fn ($section) => DB::table('users')
            ->where('section_id', $section->id)
            ->update([
                'grade_level_id'  => $section->grade_level_id,
                'track_strand_id' => $section->track_strand_id,
            ]));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['grade_level_id']);
            $table->dropForeign(['track_strand_id']);
            $table->dropColumn(['grade_level_id', 'track_strand_id']);
        });
    }
};

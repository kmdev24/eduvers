<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A class (subject × section) now exists as soon as the subject and the section do, so
 * students are enrolled without waiting for an admin to pick a teacher for every section.
 * The teacher becomes an attribute of the class instead of the thing that creates it.
 *
 * Deleting a teacher now empties the slot rather than deleting the class, which would
 * have taken the subject away from a whole section.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Key and index come off first: TiDB refuses to retype a column that carries them
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->dropForeign(['teacher_id']));
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->dropIndex(['teacher_id']));
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->foreignId('teacher_id')->nullable()->change());
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->foreign('teacher_id')->references('id')->on('users')->nullOnDelete());
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->index('teacher_id'));

        // Existing subjects reach the sections that may take them: same year level, and same
        // strand unless it is a core subject. Plain tables, not Subject::syncClasses(), so a
        // later change to the model can't break this on a fresh install.
        DB::table('subjects')->orderBy('id')->each(function ($subject) {
            $missing = DB::table('sections')
                ->where('grade_level_id', $subject->grade_level_id)
                ->when($subject->track_strand_id, fn ($q) => $q->where('track_strand_id', $subject->track_strand_id))
                ->whereNotIn('id', DB::table('subject_teacher')->where('subject_id', $subject->id)->select('section_id'))
                ->pluck('id');

            DB::table('subject_teacher')->insert($missing->map(fn ($sectionId) => [
                'subject_id' => $subject->id, 'section_id' => $sectionId, 'teacher_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ])->all());
        });
    }

    public function down(): void
    {
        // A class with no teacher cannot exist again, so those rows go
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->dropForeign(['teacher_id']));
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->dropIndex(['teacher_id']));

        DB::table('subject_teacher')->whereNull('teacher_id')->delete();

        Schema::table('subject_teacher', fn (Blueprint $t) => $t->foreignId('teacher_id')->nullable(false)->change());
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete());
        Schema::table('subject_teacher', fn (Blueprint $t) => $t->index('teacher_id'));
    }
};

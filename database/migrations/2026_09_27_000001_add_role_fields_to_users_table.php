<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Laravel's default `users` table (0001_01_01_000000_create_users_table)
 * with EduVers role fields. The student -> section FK is added later in
 * 2026_09_27_000006, after `sections` exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['developer', 'teacher', 'student'])
                  ->default('student')
                  ->after('password')
                  ->index();

            $table->enum('teacher_type', ['full_time', 'part_time', 'movers'])
                  ->nullable()
                  ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'teacher_type']);
        });
    }
};

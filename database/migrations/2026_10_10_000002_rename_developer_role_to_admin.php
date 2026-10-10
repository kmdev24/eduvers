<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "developer" role is now "admin". The column becomes a plain string, like
 * class_enrollments.status: the UserRole enum already decides what is valid, and a
 * database enum would have to be altered again on every future role change.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The index comes off first: TiDB (production) refuses to retype an indexed column
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['role']));
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('student')->change());
        Schema::table('users', fn (Blueprint $table) => $table->index('role'));

        DB::table('users')->where('role', 'developer')->update(['role' => 'admin']);

        // The seeded account follows the role, unless that address is already taken (email is unique)
        if (! DB::table('users')->where('email', 'admin@eduvers.test')->exists()) {
            DB::table('users')->where('email', 'developer@eduvers.test')->update(['email' => 'admin@eduvers.test']);
        }

        // ... and its name, while it is still the seeded one
        DB::table('users')->where('email', 'admin@eduvers.test')->where('name', 'EduVers Developer')->update(['name' => 'EduVers Admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'admin@eduvers.test')->where('name', 'EduVers Admin')->update(['name' => 'EduVers Developer']);

        if (! DB::table('users')->where('email', 'developer@eduvers.test')->exists()) {
            DB::table('users')->where('email', 'admin@eduvers.test')->update(['email' => 'developer@eduvers.test']);
        }

        DB::table('users')->where('role', 'admin')->update(['role' => 'developer']);

        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['role']));
        Schema::table('users', fn (Blueprint $table) => $table->enum('role', ['developer', 'teacher', 'student'])->default('student')->change());
        Schema::table('users', fn (Blueprint $table) => $table->index('role'));
    }
};

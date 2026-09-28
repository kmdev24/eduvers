<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns track_strands.category from a fixed ENUM into a plain string so new
 * categories (e.g. TVL) are managed in App\Enums\TrackCategory without
 * further schema changes.
 */
return new class extends Migration
{
    // SQLite rebuilds the table to change a column; foreign keys must be
    // switched off outside a transaction for that to work safely.
    public $withinTransaction = false;

    public function up(): void
    {
        // PostgreSQL implements enum() as VARCHAR + CHECK constraint; drop the
        // constraint so new categories (e.g. "tvl") are accepted.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE track_strands DROP CONSTRAINT IF EXISTS track_strands_category_check');
        }

        Schema::disableForeignKeyConstraints();

        Schema::table('track_strands', function (Blueprint $table) {
            $table->string('category', 20)->change();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('track_strands', function (Blueprint $table) {
            $table->enum('category', ['techpro', 'academic', 'college'])->change();
        });

        Schema::enableForeignKeyConstraints();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school calendar: give a term the dates it runs between, and the system can work out
 * which semester is running by itself instead of waiting for someone to tick "current".
 * Both are nullable — a term with no dates still falls back to the is_current flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('academic_year');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on']);
        });
    }
};

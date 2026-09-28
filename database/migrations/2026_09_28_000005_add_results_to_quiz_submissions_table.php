<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            // Number of questions at the time of submission, so scores stay
            // correct even if the quiz is edited later.
            $table->unsignedSmallInteger('total_items')->default(0)->after('score');
            $table->boolean('passed')->default(false)->after('total_items');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->dropColumn(['total_items', 'passed']);
        });
    }
};

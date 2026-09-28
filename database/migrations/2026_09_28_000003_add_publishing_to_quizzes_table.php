<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('academic_term_id')
                  ->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('passing_score')->default(75)->after('description'); // percent
            $table->boolean('reveal_answers')->default(true)->after('passing_score');
            $table->boolean('is_published')->default(false)->after('reveal_answers')->index();
            $table->timestamp('published_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropIndex(['is_published']);
            $table->dropColumn(['passing_score', 'reveal_answers', 'is_published', 'published_at']);
        });
    }
};

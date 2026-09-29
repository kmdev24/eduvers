<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('academic_term_id')
                  ->constrained('users')->nullOnDelete();
            $table->string('original_filename')->nullable()->after('file_path');
            $table->unsignedInteger('file_size')->nullable()->after('original_filename'); // bytes
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn(['original_filename', 'file_size']);
        });
    }
};

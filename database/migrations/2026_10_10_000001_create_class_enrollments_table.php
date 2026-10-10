<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student joining one class (subject × section) outside their own section,
 * requested by a teacher or by the student, approved by an admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_teacher_id')->constrained('subject_teacher')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'subject_teacher_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
    }
};

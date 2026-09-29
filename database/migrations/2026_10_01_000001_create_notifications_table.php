<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard notifications table (same as `php artisan make:notifications-table`),
 * used by the "database" channel for the in-app bell. Works on MySQL/TiDB, PostgreSQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if an earlier `php artisan notifications:table` already created it
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell asks "unread for this user, newest first" on every page
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

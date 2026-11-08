<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('show_entry_watch_statuses', function (Blueprint $table): void {
            // The watch-progress stream reads every status of one user on each poll; the unique index leads with the entry.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('show_entry_watch_statuses', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('show_watch_progress', function (Blueprint $table): void {
            $table->unsignedInteger('position_seconds')->nullable()->after('episode_id');
            $table->unsignedInteger('duration_seconds')->nullable()->after('position_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('show_watch_progress', function (Blueprint $table): void {
            $table->dropColumn(['position_seconds', 'duration_seconds']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->unsignedInteger('video_duration_seconds')->nullable()->after('video_file_size');
            // Set while the episode waits for the length scan, so a scan can resume after the drive comes back
            $table->timestamp('video_duration_queued_at')->nullable()->index()->after('video_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex(['video_duration_queued_at']);
            $table->dropColumn(['video_duration_seconds', 'video_duration_queued_at']);
        });
    }
};

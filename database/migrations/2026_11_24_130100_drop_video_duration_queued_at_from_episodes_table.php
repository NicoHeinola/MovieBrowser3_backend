<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Episodes no longer wait in a queue for their length. An upload or an import reads the length of its new file, and
     * the video file check reads the lengths that are not known.
     */
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table): void {
            $table->dropIndex(['video_duration_queued_at']);
            $table->dropColumn('video_duration_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table): void {
            $table->timestamp('video_duration_queued_at')->nullable()->index();
        });
    }
};

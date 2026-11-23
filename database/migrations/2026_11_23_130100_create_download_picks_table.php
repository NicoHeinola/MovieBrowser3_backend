<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A file of a download picked for an episode. Only picked files have a row; the rest are never downloaded.
     */
    public function up(): void
    {
        Schema::create('download_picks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('download_id')->constrained('downloads')->cascadeOnDelete();
            // The file's index in the torrent, as qBittorrent numbers them
            $table->unsignedInteger('file_index');
            // Its path inside the torrent
            $table->text('path');
            $table->unsignedBigInteger('size_bytes');
            // An episode waits for one file at most
            $table->foreignId('episode_id')->unique()->constrained('episodes')->cascadeOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['download_id', 'file_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_picks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A torrent the app handed to qBittorrent, found there by its info hash. Its live progress stays in qBittorrent.
     */
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table): void {
            $table->id();
            $table->string('hash', 40)->unique();
            // Null when added without one: qBittorrent's name for the torrent shows instead
            $table->string('name')->nullable();
            $table->text('magnet_url');
            // The show its files go into, set once they're picked. Deleting the show keeps the download to cancel.
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->string('status');
            $table->string('error_code')->nullable();
            // Set once the torrent left qBittorrent after finishing, so the sync stops asking about it
            $table->timestamp('client_removed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the qBittorrent connection and the folder downloads land in. All empty: downloads stay off until an admin
     * sets them up.
     */
    public function up(): void
    {
        $rows = [
            ['key' => 'qbittorrent_url', 'type' => 'string'],
            ['key' => 'qbittorrent_username', 'type' => 'string'],
            ['key' => 'qbittorrent_password', 'type' => 'secret'],
            ['key' => 'download_path', 'type' => 'string'],
        ];

        DB::table('settings')->insert(array_map(fn (array $row): array => [
            ...$row,
            'value' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows));
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['qbittorrent_url', 'qbittorrent_username', 'qbittorrent_password', 'download_path'])->delete();
    }
};

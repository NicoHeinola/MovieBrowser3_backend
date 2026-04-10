<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert([
            [
                'key' => 'banner_default_videos',
                'value' => json_encode([]),
                'type' => 'json',
            ],
            [
                'key' => 'banner_default_backgrounds',
                'value' => json_encode([]),
                'type' => 'json',
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'banner_default_videos',
            'banner_default_backgrounds',
        ])->delete();
    }
};

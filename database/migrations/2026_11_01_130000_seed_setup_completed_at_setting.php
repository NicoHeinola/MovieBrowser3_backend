<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the setting the first-login setup wizard sets when it's done. Installs that already have a VLC path are
     * treated as set up, so their admins don't get the wizard.
     */
    public function up(): void
    {
        $vlcPath = DB::table('settings')->where('key', 'vlc_media_player_path')->value('value');

        DB::table('settings')->insert([
            'key' => 'setup_completed_at',
            'value' => filled($vlcPath) ? now()->toJSON() : '',
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'setup_completed_at')->delete();
    }
};

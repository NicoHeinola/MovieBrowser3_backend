<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app calls the folder that holds all videos the video base folder, and a change of it a video base folder
     * move. This gives the table, the setting and the stored error codes that name.
     */
    public function up(): void
    {
        Schema::rename('video_relocations', 'video_base_folder_moves');

        $this->renameMoveErrorCodes('video_relocation', 'video_base_folder_move');
        $this->renameSetting('video_base_path', 'video_base_folder');
        $this->renameCheckErrorCode('video_base_path_unavailable', 'video_base_folder_unavailable');
    }

    public function down(): void
    {
        $this->renameCheckErrorCode('video_base_folder_unavailable', 'video_base_path_unavailable');
        $this->renameSetting('video_base_folder', 'video_base_path');
        $this->renameMoveErrorCodes('video_base_folder_move', 'video_relocation');

        Schema::rename('video_base_folder_moves', 'video_relocations');
    }

    private function renameMoveErrorCodes(string $from, string $to): void
    {
        foreach (['failed', 'revert_failed'] as $failure) {
            DB::table('video_base_folder_moves')
                ->where('error_code', "{$from}_{$failure}")
                ->update(['error_code' => "{$to}_{$failure}"]);
        }
    }

    private function renameSetting(string $from, string $to): void
    {
        DB::table('settings')->where('key', $from)->update(['key' => $to]);
    }

    private function renameCheckErrorCode(string $from, string $to): void
    {
        DB::table('video_file_checks')->where('error_code', $from)->update(['error_code' => $to]);
    }
};

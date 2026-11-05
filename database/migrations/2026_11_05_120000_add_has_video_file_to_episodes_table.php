<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table): void {
            // True until the first check, so nothing is flagged as missing before anything was looked at
            $table->boolean('has_video_file')->default(true)->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table): void {
            $table->dropColumn('has_video_file');
        });
    }
};

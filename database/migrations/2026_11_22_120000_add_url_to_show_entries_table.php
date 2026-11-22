<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('show_entries', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('name')
                ->comment('An outside page about this entry, such as its MyAnimeList page');
        });
    }

    public function down(): void
    {
        Schema::table('show_entries', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};

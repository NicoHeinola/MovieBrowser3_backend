<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The video file check reads the lengths too, so there is no scan of its own any more.
     */
    public function up(): void
    {
        Schema::drop('video_duration_scans');
    }

    public function down(): void
    {
        Schema::create('video_duration_scans', function (Blueprint $table): void {
            $table->id();
            $table->boolean('running')->nullable()->unique();
            $table->unsignedInteger('read')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
};

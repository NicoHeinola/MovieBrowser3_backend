<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_duration_scans', function (Blueprint $table): void {
            $table->id();
            // True while running, null afterwards: a unique index allows many nulls but one true, so only one scan runs
            $table->boolean('running')->nullable()->unique();
            $table->unsignedInteger('read')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_duration_scans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_relocations', function (Blueprint $table): void {
            $table->id();
            // True while running, null afterwards: a unique index allows many nulls but one true, so only one can run
            $table->boolean('running')->nullable()->unique();
            // Set once the move failed and the moved files are going back to from_path
            $table->boolean('reverting')->default(false);
            $table->string('from_path');
            $table->string('to_path');
            $table->unsignedInteger('moved')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('error_code')->nullable();
            // The video that could not be moved, e.g. because a player had it open
            $table->string('error_path')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_relocations');
    }
};

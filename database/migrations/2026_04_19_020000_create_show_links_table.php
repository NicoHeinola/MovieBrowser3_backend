<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('target_show_id')->constrained('shows')->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['source_show_id', 'target_show_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_links');
    }
};

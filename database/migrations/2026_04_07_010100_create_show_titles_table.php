<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_titles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_titles');
    }
};

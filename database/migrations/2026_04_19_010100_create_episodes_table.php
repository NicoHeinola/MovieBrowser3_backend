<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_entry_id')->constrained('show_entries')->cascadeOnDelete();
            $table->string('name');
            $table->string('filename');
            $table->unsignedInteger('sequence_number');
            $table->timestamps();

            $table->unique(['show_entry_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['show_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_entries');
    }
};

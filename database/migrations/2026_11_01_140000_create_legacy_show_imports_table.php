<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_show_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_show_id')->unique()->comment('The show id in MovieBrowser2');
            $table->foreignId('show_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_show_imports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The MovieBrowser2 import is gone, and with it the need to know which show came from which.
     */
    public function up(): void
    {
        Schema::dropIfExists('legacy_show_imports');
    }

    public function down(): void
    {
        Schema::create('legacy_show_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_show_id')->unique();
            $table->foreignId('show_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }
};

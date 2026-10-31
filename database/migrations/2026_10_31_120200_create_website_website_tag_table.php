<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_website_tag', function (Blueprint $table) {
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['website_id', 'website_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_website_tag');
    }
};

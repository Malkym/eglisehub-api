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
        Schema::create('media_categories', function (Blueprint $table) {
            $table->foreignId('media_id')->constrained('medias')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['media_id', 'categorie_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_categories');
    }
};

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
    Schema::create('medias', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
        $table->string('nom_original');
        $table->string('nom_fichier');
        $table->string('chemin');
        $table->string('url');
        $table->enum('type', ['image', 'video', 'audio', 'document'])->default('image');
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('taille')->nullable();
        $table->string('categorie')->nullable();
        $table->string('alt_text')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};

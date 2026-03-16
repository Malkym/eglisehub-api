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
    Schema::create('pages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
        $table->string('titre');
        $table->string('slug');
        $table->longText('contenu')->nullable();
        $table->string('image_hero')->nullable();
        $table->boolean('dans_menu')->default(true);
        $table->integer('ordre_menu')->default(0);
        $table->enum('statut', ['publie', 'brouillon'])->default('brouillon');
        $table->string('meta_titre')->nullable();
        $table->text('meta_description')->nullable();
        $table->timestamps();

        $table->unique(['ministere_id', 'slug']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};

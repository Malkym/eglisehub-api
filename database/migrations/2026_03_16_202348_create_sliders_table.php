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
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
            $table->string('titre');
            $table->text('sous_titre')->nullable();
            $table->string('image');
            $table->string('url_image')->nullable();
            $table->string('bouton_texte')->nullable();
            $table->string('bouton_lien')->nullable();
            $table->enum('position_texte', ['gauche', 'centre', 'droite'])->default('centre');
            $table->string('couleur_texte')->default('#FFFFFF');
            $table->string('couleur_fond')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};

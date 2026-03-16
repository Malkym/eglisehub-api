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
        Schema::create('taggables', function (Blueprint $table) {
            $table->id(); // Ajoute un ID auto-increment (optionnel mais recommandé)
            
            // Déclare d'abord la colonne sans contrainte
            $table->unsignedBigInteger('tag_id');
            
            // Crée les colonnes polymorphiques
            $table->morphs('taggable'); // crée taggable_id (BIGINT) et taggable_type (STRING)
            
            // Timestamps
            $table->timestamps();
            
            // Puis ajoute les contraintes et index APRÈS avoir créé toutes les colonnes
            $table->foreign('tag_id')
                  ->references('id')
                  ->on('tags')
                  ->onDelete('cascade');
            
            // Index pour les performances
            $table->index(['taggable_id', 'taggable_type']);
            
            // Clé unique pour éviter les doublons
            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};

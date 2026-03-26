<?php
// database/migrations/2026_03_19_create_article_notes_table.php

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
        Schema::create('article_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                  ->constrained('articles')
                  ->cascadeOnDelete();
            $table->unsignedTinyInteger('note')
                  ->comment('Note de 1 à 5 étoiles')
                  ->check('note >= 1 AND note <= 5');
            $table->string('ip', 45)->nullable()
                  ->comment('Adresse IP du votant');
            $table->string('session_id')->nullable()
                  ->comment('Session ID pour éviter les votes multiples');
            $table->timestamps();

            // Index pour les performances
            $table->index(['article_id', 'ip']);
            $table->index(['article_id', 'session_id']);
            
            // Empêcher le vote multiple depuis la même IP/session pour un article
            $table->unique(['article_id', 'ip', 'session_id'], 'article_note_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_notes');
    }
};
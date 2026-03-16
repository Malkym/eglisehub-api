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
    Schema::create('evenements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
        $table->string('titre');
        $table->text('description')->nullable();
        $table->string('image')->nullable();
        $table->dateTime('date_debut');
        $table->dateTime('date_fin')->nullable();
        $table->string('lieu')->nullable();
        $table->string('adresse_lieu')->nullable();
        $table->enum('type', ['culte', 'conference', 'seminaire', 'autre'])->default('autre');
        $table->enum('statut', ['a_venir', 'en_cours', 'termine', 'annule'])->default('a_venir');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};

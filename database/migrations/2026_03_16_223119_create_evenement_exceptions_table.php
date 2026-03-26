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
        Schema::create('evenement_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->date('date_exception'); // La date annulée ou modifiée
            $table->enum('type', ['annulation', 'modification'])->default('annulation');
            $table->string('titre_modifie')->nullable();
            $table->text('description_modifiee')->nullable();
            $table->time('heure_debut_modifiee')->nullable();
            $table->time('heure_fin_modifiee')->nullable();
            $table->string('lieu_modifie')->nullable();
            $table->text('raison')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evenement_exceptions');
    }
};

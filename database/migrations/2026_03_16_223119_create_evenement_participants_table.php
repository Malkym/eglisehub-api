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
        Schema::create('evenement_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->string('nom');
            $table->string('email');
            $table->string('telephone')->nullable();
            $table->integer('nombre_places')->default(1);
            $table->enum('statut', ['inscrit', 'confirme', 'present', 'absent', 'annule'])->default('inscrit');
            $table->boolean('checkin')->default(false);
            $table->timestamp('checkin_le')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evenement_participants');
    }
};

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
    Schema::create('ministeres', function (Blueprint $table) {
        $table->id();
        $table->string('nom');
        $table->string('slug')->unique();
        $table->string('sous_domaine')->unique();
        $table->text('description')->nullable();
        $table->string('logo')->nullable();
        $table->string('couleur_primaire')->default('#1E3A8A');
        $table->string('couleur_secondaire')->default('#FFFFFF');
        $table->string('email_contact')->nullable();
        $table->string('telephone')->nullable();
        $table->string('adresse')->nullable();
        $table->string('ville')->nullable();
        $table->string('pays')->default('République centrafricaine');
        $table->string('facebook_url')->nullable();
        $table->string('youtube_url')->nullable();
        $table->string('whatsapp')->nullable();
        $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministeres');
    }
};

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
    Schema::create('messages_contact', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
        $table->string('nom_expediteur');
        $table->string('email');
        $table->string('telephone')->nullable();
        $table->string('sujet')->nullable();
        $table->text('message');
        $table->enum('statut', ['non_lu', 'lu', 'repondu'])->default('non_lu');
        $table->timestamp('lu_le')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages_contact');
    }
};

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
        Schema::create('article_commentaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('article_commentaires')->nullOnDelete();
            $table->string('nom_auteur');
            $table->string('email');
            $table->text('contenu');
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->string('ip')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_commentaires');
    }
};

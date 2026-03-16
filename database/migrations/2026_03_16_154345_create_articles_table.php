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
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('titre');
        $table->string('slug');
        $table->text('resume')->nullable();
        $table->longText('contenu')->nullable();
        $table->string('image_une')->nullable();
        $table->string('categorie')->nullable();
        $table->enum('statut', ['publie', 'brouillon'])->default('brouillon');
        $table->timestamp('date_publication')->nullable();
        $table->timestamps();

        $table->unique(['ministere_id', 'slug']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

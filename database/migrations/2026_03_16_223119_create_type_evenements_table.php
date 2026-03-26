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
        Schema::create('type_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
            $table->string('nom');
            $table->string('couleur')->default('#1E3A8A');
            $table->string('icone')->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_evenements');
    }
};

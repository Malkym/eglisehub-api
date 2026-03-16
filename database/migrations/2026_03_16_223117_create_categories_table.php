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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
            $table->string('nom');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('type')->default('article'); // article, media, event
            $table->string('couleur')->default('#6B7280');
            $table->string('icone')->nullable();
            $table->timestamps();

            $table->unique(['ministere_id', 'slug', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

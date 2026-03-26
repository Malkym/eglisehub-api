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
        Schema::create('sessions_visiteurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
            $table->string('session_id')->nullable();
            $table->string('page_visitee');
            $table->string('ip')->nullable();
            $table->string('pays')->nullable();
            $table->string('ville')->nullable();
            $table->string('navigateur')->nullable();
            $table->string('appareil')->nullable(); // mobile, desktop, tablet
            $table->string('os')->nullable();
            $table->string('referrer')->nullable();
            $table->integer('duree_secondes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions_visiteurs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worship_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministere_id')->constrained()->onDelete('cascade');
            $table->enum('jour', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->boolean('is_highlight')->default(false);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
            
            $table->index(['ministere_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worship_schedules');
    }
};
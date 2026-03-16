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
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ministere_id')->constrained('ministeres')->cascadeOnDelete();
        $table->string('cle')->index();
        $table->text('valeur')->nullable();
        $table->timestamps();

        $table->unique(['ministere_id', 'cle']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

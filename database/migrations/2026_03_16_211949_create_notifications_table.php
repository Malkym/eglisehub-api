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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // info, success, warning, error
            $table->string('titre');
            $table->text('message');
            $table->string('lien')->nullable(); // URL de l'action liée
            $table->string('module')->nullable(); // quel module a généré la notif
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

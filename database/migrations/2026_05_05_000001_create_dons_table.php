<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministere_id')->constrained()->cascadeOnDelete();
            $table->string('nom_donateur');
            $table->string('email_donateur')->nullable();
            $table->string('telephone', 20);
            $table->decimal('montant', 12, 2);
            $table->enum('type_don', ['don', 'dime', 'offrande']);
            $table->enum('operateur', ['orange', 'moov', 'airtel']);
            $table->string('reference_paiement')->nullable();
            $table->enum('statut', ['en_attente', 'confirme', 'echoue'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dons');
    }
};

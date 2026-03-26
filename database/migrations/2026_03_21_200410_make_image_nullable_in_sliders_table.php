<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            // Rendre image nullable
            $table->string('image')->nullable()->change();
            $table->string('url_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            // Revenir en arrière (attention: peut échouer si des valeurs NULL existent)
            $table->string('image')->nullable(false)->change();
            $table->string('url_image')->nullable(false)->change();
        });
    }
};
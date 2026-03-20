<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les colonnes à la table sliders
        Schema::table('sliders', function (Blueprint $table) {
            $table->enum('type_media', ['image', 'video'])->default('image');
            $table->string('video_path')->nullable();
            $table->string('video_thumbnail')->nullable();
            $table->integer('video_size')->nullable();
            $table->string('video_mime_type')->nullable();
        });

        // Ajouter les colonnes à la table evenements
        Schema::table('evenements', function (Blueprint $table) {
            $table->enum('type_media', ['image', 'video'])->default('image');
            $table->string('video_path')->nullable();
            $table->string('video_thumbnail')->nullable();
            $table->integer('video_size')->nullable();
            $table->string('video_mime_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->dropColumn(['type_media', 'video_path', 'video_thumbnail', 'video_size', 'video_mime_type']);
        });

        Schema::table('evenements', function (Blueprint $table) {
            $table->dropColumn(['type_media', 'video_path', 'video_thumbnail', 'video_size', 'video_mime_type']);
        });
    }
};
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
    Schema::table('articles', function (Blueprint $table) {
        // Type d'article : texte, lien externe, vidéo YouTube, audio
        $table->enum('type_contenu', [
            'texte',
            'lien_externe',
            'video_youtube',
            'audio',
            'mixte'
        ])->default('texte')->after('statut');

        // URL si l'article est un lien ou une vidéo
        $table->string('url_externe')->nullable()->after('type_contenu');

        // ID YouTube si c'est une vidéo YouTube (ex: dQw4w9WgXcQ)
        $table->string('youtube_id')->nullable()->after('url_externe');

        // Durée de lecture/écoute (ex: "12 min", "1h30")
        $table->string('duree')->nullable()->after('youtube_id');

        // Auteur externe si l'article vient d'ailleurs
        $table->string('auteur_externe')->nullable()->after('duree');

        // Nombre de vues
        $table->unsignedBigInteger('vues')->default(0)->after('auteur_externe');

        // Mis en avant
        $table->boolean('en_avant')->default(false)->after('vues');
    });
}

public function down(): void
{
    Schema::table('articles', function (Blueprint $table) {
        $table->dropColumn([
            'type_contenu', 'url_externe', 'youtube_id',
            'duree', 'auteur_externe', 'vues', 'en_avant'
        ]);
    });
}
};

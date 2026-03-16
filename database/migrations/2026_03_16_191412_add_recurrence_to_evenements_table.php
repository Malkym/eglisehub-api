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
    Schema::table('evenements', function (Blueprint $table) {

        // Catégorie de l'événement
        $table->enum('categorie', [
            'ponctuel',      // conférence, concert, retraite
            'recurrent',     // culte chaque dimanche
            'permanent',     // groupe de prière, jeunes, femmes
            'saison',        // carême, jeûne, période spéciale
        ])->default('ponctuel')->after('type');

        // Règle de récurrence (pour les événements récurrents)
        $table->enum('frequence', [
            'aucune',
            'quotidien',
            'hebdomadaire',
            'bimensuel',    // toutes les 2 semaines
            'mensuel',
            'annuel',
        ])->default('aucune')->after('categorie');

        // Jours de la semaine pour les récurrents (ex: ["dimanche","mercredi"])
        $table->json('jours_semaine')->nullable()->after('frequence');

        // Heure fixe de l'événement récurrent (ex: "09:00")
        $table->time('heure_debut')->nullable()->after('jours_semaine');
        $table->time('heure_fin')->nullable()->after('heure_debut');

        // Date de fin de la récurrence (null = permanent)
        $table->date('date_fin_recurrence')->nullable()->after('heure_fin');

        // Lien streaming (YouTube Live, Zoom, etc.)
        $table->string('lien_streaming')->nullable()->after('date_fin_recurrence');

        // En ligne ou présentiel
        $table->enum('mode', ['presentiel', 'en_ligne', 'hybride'])
              ->default('presentiel')->after('lien_streaming');

        // Capacité maximale (null = illimité)
        $table->unsignedInteger('capacite_max')->nullable()->after('mode');

        // Inscription requise
        $table->boolean('inscription_requise')->default(false)->after('capacite_max');

        // Événement gratuit ou payant
        $table->boolean('est_gratuit')->default(true)->after('inscription_requise');
        $table->decimal('prix', 10, 2)->nullable()->after('est_gratuit');
        $table->string('devise', 10)->default('XAF')->after('prix');
    });
}

public function down(): void
{
    Schema::table('evenements', function (Blueprint $table) {
        $table->dropColumn([
            'categorie', 'frequence', 'jours_semaine',
            'heure_debut', 'heure_fin', 'date_fin_recurrence',
            'lien_streaming', 'mode', 'capacite_max',
            'inscription_requise', 'est_gratuit', 'prix', 'devise',
        ]);
    });
}
};

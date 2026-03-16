<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Evenement extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id',
        'titre',
        'description',
        'image',
        'date_debut',
        'date_fin',
        'lieu',
        'adresse_lieu',
        'type',
        'categorie',
        'frequence',
        'jours_semaine',
        'heure_debut',
        'heure_fin',
        'date_fin_recurrence',
        'lien_streaming',
        'mode',
        'capacite_max',
        'inscription_requise',
        'est_gratuit',
        'prix',
        'devise',
        'statut',
    ];

    protected $casts = [
        'date_debut'           => 'datetime',
        'date_fin'             => 'datetime',
        'date_fin_recurrence'  => 'date',
        'jours_semaine'        => 'array',   // JSON auto-décodé
        'inscription_requise'  => 'boolean',
        'est_gratuit'          => 'boolean',
        'prix'                 => 'decimal:2',
    ];

    public function participants()
    {
        return $this->hasMany(EvenementParticipant::class);
    }

    public function exceptions()
    {
        return $this->hasMany(EvenementException::class);
    }

    public function typeEvenement()
    {
        return $this->belongsTo(TypeEvenement::class, 'type_id');
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}

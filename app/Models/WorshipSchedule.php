<?php
// app/Models/WorshipSchedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorshipSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id',
        'jour',
        'heure_debut',
        'heure_fin',
        'is_highlight',
        'note',
        'is_active',
        'ordre'
    ];

    protected $casts = [
        'is_highlight' => 'boolean',
        'is_active' => 'boolean',
        'heure_debut' => 'string',
        'heure_fin' => 'string',
    ];

    // Relation avec le ministère
    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    // Scope pour les horaires actifs
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope pour un ministère spécifique
    public function scopeForMinistere($query, $ministereId)
    {
        return $query->where('ministere_id', $ministereId);
    }

    // Accesseur pour le libellé du jour en français
    public function getJourLabelAttribute()
    {
        $jours = [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
        ];
        return $jours[$this->jour] ?? $this->jour;
    }
}
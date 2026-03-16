<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvenementException extends Model
{
    protected $table = 'evenement_exceptions';

    protected $fillable = [
        'evenement_id', 'date_exception', 'type',
        'titre_modifie', 'description_modifiee',
        'heure_debut_modifiee', 'heure_fin_modifiee',
        'lieu_modifie', 'raison',
    ];

    protected $casts = [
        'date_exception' => 'date',
    ];

    public function evenement()
    {
        return $this->belongsTo(Evenement::class);
    }
}
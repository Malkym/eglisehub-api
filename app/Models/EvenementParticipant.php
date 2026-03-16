<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvenementParticipant extends Model
{
    protected $table = 'evenement_participants';

    protected $fillable = [
        'evenement_id', 'nom', 'email', 'telephone',
        'nombre_places', 'statut', 'checkin', 'checkin_le', 'notes',
    ];

    protected $casts = [
        'checkin'    => 'boolean',
        'checkin_le' => 'datetime',
    ];

    public function evenement()
    {
        return $this->belongsTo(Evenement::class);
    }
}
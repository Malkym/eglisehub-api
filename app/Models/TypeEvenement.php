<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeEvenement extends Model
{
    protected $table = 'type_evenements';

    protected $fillable = [
        'ministere_id', 'nom', 'couleur',
        'icone', 'description', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    public function evenements()
    {
        return $this->hasMany(Evenement::class, 'type_id');
    }
}
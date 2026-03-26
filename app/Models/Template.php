<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'nom', 'description', 'apercu',
        'fichier_html', 'structure', 'type', 'actif',
    ];

    protected $casts = [
        'structure' => 'array',
        'actif'     => 'boolean',
    ];
}
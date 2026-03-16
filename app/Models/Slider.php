<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id', 'titre', 'sous_titre', 'image', 'url_image',
        'bouton_texte', 'bouton_lien', 'position_texte',
        'couleur_texte', 'couleur_fond', 'ordre', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    // Ajouter l'URL complète de l'image
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}
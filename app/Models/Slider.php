<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        'ordre' => 'integer',
    ];

    protected function urlImage(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // Essaie url_image d'abord, sinon image
                $path = $value ?: $this->getRawOriginal('image');
                return Article::buildStorageUrl($path);
            },
        );
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}

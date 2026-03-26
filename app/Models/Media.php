<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'medias';

    protected $fillable = [
        'ministere_id',
        'nom_original',
        'nom_fichier',
        'chemin',
        'url',
        'type',
        'mime_type',
        'taille',
        'categorie',
        'alt_text',
        'visible',
    ];

    protected $casts = [
        'visible' => 'boolean',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    // Accesseur pour l'URL complète
    public function getFullUrlAttribute()
    {
        return $this->url ? Storage::url($this->url) : null;
    }

    // Scope pour les médias visibles
    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }
}
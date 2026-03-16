<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Media extends Model
{
    protected $table = 'medias';

    use HasFactory;

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
    ];

    public function categories()
    {
        return $this->belongsToMany(Categorie::class, 'media_categories', 'media_id', 'categorie_id');
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Media extends Model
{
    use HasFactory;

    protected $table = 'medias';

    protected $fillable = [
        'ministere_id', 'nom_original', 'nom_fichier', 'type',
        'chemin', 'url', 'taille', 'mime_type', 'categorie', 'statut',
    ];

    protected $casts = [
        'taille' => 'integer',
    ];

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Article::buildStorageUrl($value),
        );
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}

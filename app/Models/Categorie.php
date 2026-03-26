<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    protected $fillable = [
        'ministere_id', 'nom', 'slug',
        'description', 'type', 'couleur', 'icone',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_categories', 'categorie_id', 'article_id');
    }

    public function medias()
    {
        return $this->belongsToMany(Media::class, 'media_categories', 'categorie_id', 'media_id');
    }
}
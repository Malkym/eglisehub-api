<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id',
        'titre',
        'slug',
        'contenu',
        'image_hero',
        'dans_menu',
        'ordre_menu',
        'statut',
        'meta_titre',
        'meta_description',
    ];

    protected $casts = [
        'dans_menu' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(PageSection::class)->orderBy('ordre');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}

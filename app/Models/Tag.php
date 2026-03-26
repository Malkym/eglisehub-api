<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id', 'nom', 'slug', 'couleur',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    // Relation polymorphique
    public function articles()
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }

    public function pages()
    {
        return $this->morphedByMany(Page::class, 'taggable');
    }
}
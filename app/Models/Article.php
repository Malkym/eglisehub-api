<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id', 'user_id', 'titre', 'slug', 'resume', 'contenu',
        'image_une', 'categorie', 'type_contenu', 'url_externe', 'youtube_id',
        'duree', 'auteur_externe', 'vues', 'en_avant', 'statut', 'date_publication',
    ];

    protected $casts = [
        'date_publication' => 'datetime',
        'en_avant'         => 'boolean',
        'vues'             => 'integer',
    ];

    protected function imageUne(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => self::buildStorageUrl($value),
            set: fn ($value) => $value,
        );
    }

    public static function buildStorageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        $base = rtrim(config('app.url'), '/');
        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'storage/')) {
            $path = 'storage/' . $path;
        }
        return $base . '/' . $path;
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function commentaires()
    {
        return $this->hasMany(ArticleCommentaire::class)
            ->whereNull('parent_id')
            ->where('statut', 'approuve');
    }

    public function tousCommentaires()
    {
        return $this->hasMany(ArticleCommentaire::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Categorie::class, 'article_categories', 'article_id', 'categorie_id');
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

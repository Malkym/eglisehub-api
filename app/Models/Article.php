<?php
// app/Models/Article.php

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
        'commentaires_actifs',
    ];

    protected $casts = [
        'date_publication' => 'datetime',
        'en_avant'         => 'boolean',
        'vues'             => 'integer',
        'commentaires_actifs' => 'boolean',
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

    // ===== RELATIONS EXISTANTES =====
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

    // ===== NOUVELLES RELATIONS POUR LES NOTES =====
    
    /**
     * Relation avec les notes de l'article
     */
    public function notes()
    {
        return $this->hasMany(ArticleNote::class);
    }

    /**
     * Calculer la note moyenne de l'article
     */
    public function getAverageRatingAttribute()
    {
        return $this->notes()->avg('note') ?? 0;
    }

    /**
     * Compter le nombre total de votes
     */
    public function getRatingCountAttribute()
    {
        return $this->notes()->count();
    }

    /**
     * Vérifier si un visiteur a déjà voté
     */
    public function hasUserRated(?string $ip, ?string $sessionId): bool
    {
        if (!$ip && !$sessionId) return false;
        
        $query = $this->notes();
        
        if ($ip) {
            $query->where('ip', $ip);
        }
        
        if ($sessionId) {
            $query->orWhere('session_id', $sessionId);
        }
        
        return $query->exists();
    }

    /**
     * Ajouter une note à l'article
     */
    public function addRating(int $note, ?string $ip, ?string $sessionId)
    {
        return $this->notes()->create([
            'note' => $note,
            'ip' => $ip,
            'session_id' => $sessionId,
        ]);
    }

    // ===== SCOPES UTILES =====
    
    /**
     * Scope pour les articles publiés
     */
    public function scopePublies($query)
    {
        return $query->where('statut', 'publie')
                     ->where('date_publication', '<=', now());
    }

    /**
     * Scope pour filtrer par catégorie
     */
    public function scopeDeCategorie($query, string $categorie)
    {
        return $query->where('categorie', $categorie);
    }

    /**
     * Scope pour les articles mis en avant
     */
    public function scopeEnAvant($query)
    {
        return $query->where('en_avant', true);
    }

    /**
     * Scope pour les témoignages (articles de catégorie témoignage)
     */
    public function scopeTemoignages($query)
    {
        return $query->where('categorie', 'temoignage');
    }

    /**
     * Scope pour les enseignements
     */
    public function scopeEnseignements($query)
    {
        return $query->where('categorie', 'enseignement');
    }

    /**
     * Scope pour trier par note moyenne
     */
    public function scopeTrieParNote($query, $direction = 'desc')
    {
        return $query->withAvg('notes', 'note')
                     ->orderBy('notes_avg_note', $direction);
    }

    /**
     * Scope pour trier par nombre de votes
     */
    public function scopeTrieParVotes($query, $direction = 'desc')
    {
        return $query->withCount('notes')
                     ->orderBy('notes_count', $direction);
    }
}
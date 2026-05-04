<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    protected $appends = ['average_rating', 'rating_count'];

    public function getAverageRatingAttribute(): float
    {
        return round($this->notes()->avg('note') ?? 0, 1);
    }

    public function getRatingCountAttribute(): int
    {
        return $this->notes()->count();
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

    public function getImageUneUrlAttribute(): ?string
    {
        return self::buildStorageUrl($this->image_une);
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

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notes()
    {
        return $this->hasMany(ArticleNote::class);
    }

    public function hasUserRated(?string $ip, ?string $sessionId): bool
    {
        if (!$ip && !$sessionId) return false;

        return $this->notes()
            ->where(function ($query) use ($ip, $sessionId) {
                if ($ip) {
                    $query->orWhere('ip', $ip);
                }
                if ($sessionId) {
                    $query->orWhere('session_id', $sessionId);
                }
            })
            ->exists();
    }

    public function addRating(int $note, ?string $ip, ?string $sessionId)
    {
        return $this->notes()->create([
            'note'       => $note,
            'ip'         => $ip,
            'session_id' => $sessionId,
        ]);
    }

    public function scopePublies($query)
    {
        return $query->where('statut', 'publie')
            ->where('date_publication', '<=', now());
    }

    public function scopeDeCategorie($query, string $categorie)
    {
        return $query->where('categorie', $categorie);
    }

    public function scopeEnAvant($query)
    {
        return $query->where('en_avant', true);
    }

    public function scopeTemoignages($query)
    {
        return $query->where('categorie', 'temoignage');
    }

    public function scopeEnseignements($query)
    {
        return $query->where('categorie', 'seignement');
    }

    public function scopeTrieParNote($query, string $direction = 'desc')
    {
        return $query->withAvg('notes', 'note')
            ->orderBy('notes_avg_note', $direction);
    }

    public function scopeTrieParVotes($query, string $direction = 'desc')
    {
        return $query->withCount('notes')
            ->orderBy('notes_count', $direction);
    }
}
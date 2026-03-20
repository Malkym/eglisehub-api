<?php
// app/Models/ArticleNote.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleNote extends Model
{
    protected $table = 'article_notes';

    protected $fillable = [
        'article_id',
        'note',
        'ip',
        'session_id',
    ];

    protected $casts = [
        'note' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'article noté
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Scope pour filtrer par IP
     */
    public function scopeParIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }

    /**
     * Scope pour filtrer par session
     */
    public function scopeParSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}
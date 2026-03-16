<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleCommentaire extends Model
{
    protected $table = 'article_commentaires';

    protected $fillable = [
        'article_id', 'parent_id', 'nom_auteur',
        'email', 'contenu', 'statut', 'ip',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    // Réponses à ce commentaire
    public function reponses()
    {
        return $this->hasMany(ArticleCommentaire::class, 'parent_id');
    }

    // Commentaire parent
    public function parent()
    {
        return $this->belongsTo(ArticleCommentaire::class, 'parent_id');
    }
}
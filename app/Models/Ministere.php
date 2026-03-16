<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ministere extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'type',
        'slug',
        'sous_domaine',
        'description',
        'logo',
        'couleur_primaire',
        'couleur_secondaire',
        'email_contact',
        'telephone',
        'adresse',
        'ville',
        'pays',
        'facebook_url',
        'youtube_url',
        'whatsapp',
        'statut',
    ];

    // Un ministère a plusieurs utilisateurs
    public function utilisateurs()
    {
        return $this->hasMany(User::class, 'ministere_id');
    }

    // Un ministère a plusieurs pages
    public function pages()
    {
        return $this->hasMany(Page::class, 'ministere_id');
    }

    public function categories()
    {
        return $this->hasMany(Categorie::class);
    }

    public function typeEvenements()
    {
        return $this->hasMany(TypeEvenement::class);
    }

    public function sessionsVisiteurs()
    {
        return $this->hasMany(SessionVisiteur::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    // Un ministère a plusieurs articles
    public function articles()
    {
        return $this->hasMany(Article::class, 'ministere_id');
    }

    // Un ministère a plusieurs événements
    public function evenements()
    {
        return $this->hasMany(Evenement::class, 'ministere_id');
    }

    // Un ministère a plusieurs médias
    public function medias()
    {
        return $this->hasMany(Media::class, 'ministere_id');
    }

    // Un ministère a plusieurs messages de contact
    public function messages()
    {
        return $this->hasMany(MessageContact::class, 'ministere_id');
    }

    // Un ministère a plusieurs paramètres
    public function settings()
    {
        return $this->hasMany(Setting::class, 'ministere_id');
    }
}

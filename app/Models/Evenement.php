<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Evenement extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id',
        'titre',
        'description',
        'image',
        'date_debut',
        'date_fin',
        'lieu',
        'adresse_lieu',
        'type',
        'categorie',
        'frequence',
        'jours_semaine',
        'heure_debut',
        'heure_fin',
        'date_fin_recurrence',
        'lien_streaming',
        'mode',
        'capacite_max',
        'inscription_requise',
        'est_gratuit',
        'prix',
        'devise',
        'statut',
        'intervenant',
        'theme',
        'type_media',
        'video_path',
        'video_thumbnail',
        'video_size',
        'video_mime_type',
    ];

    protected $casts = [
        'date_debut'           => 'datetime',
        'date_fin'             => 'datetime',
        'date_fin_recurrence'  => 'date',
        'jours_semaine'        => 'array',
        'inscription_requise'  => 'boolean',
        'est_gratuit'          => 'boolean',
        'prix'                 => 'decimal:2',
        'type_media'           => 'string',
    ];

    public function participants()
    {
        return $this->hasMany(EvenementParticipant::class);
    }

    public function exceptions()
    {
        return $this->hasMany(EvenementException::class);
    }

    public function typeEvenement()
    {
        return $this->belongsTo(TypeEvenement::class, 'type_id');
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    // Accesseur pour l'URL de l'image
    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    // Accesseur pour l'URL de la vidéo
    public function getVideoUrlAttribute()
    {
        return $this->video_path ? Storage::url($this->video_path) : null;
    }

    // Accesseur pour l'URL de la miniature vidéo
    public function getVideoThumbnailUrlAttribute()
    {
        return $this->video_thumbnail ? Storage::url($this->video_thumbnail) : null;
    }
}